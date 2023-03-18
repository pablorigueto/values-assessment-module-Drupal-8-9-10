<?php

namespace Drupal\va_custom\Form;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityViewBuilderInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface as DependencyInjectionContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;

/**
 * Provides a form to define a personal values.
 */
class PvaForm extends FormBase {

  /**
   * The prefix for groups.
   *
   * @var integer
   */
  const VALUES_TIME = 10;

  /**
   * The entity view builder.
   *
   * @var \Drupal\Core\Entity\EntityViewBuilderInterface
   */
  protected $entityViewBuilder;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The Entity Type Manager Interface.
   *
   * @var Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Language Manager Interface.
   *
   * @var Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The Request Stack service.
   *
   * @var Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The Alias ManagerInterface.
   *
   * @var Drupal\path_alias\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * The entity repository service.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The Drupal database.
   *
   * @var Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    EntityViewBuilderInterface $entity_view_builder,
    MessengerInterface $messenger,
    EntityTypeManagerInterface $entityTypeManager,
    LanguageManagerInterface $languageManager,
    AccountInterface $currentUser,
    RequestStack $requestStack,
    AliasManagerInterface $aliasManager,
    EntityRepositoryInterface $entity_repository,
    Connection $database,
  ) {
    $this->entityViewBuilder = $entity_view_builder;
    $this->messenger = $messenger;
    $this->entityTypeManager = $entityTypeManager;
    $this->languageManager = $languageManager;
    $this->currentUser = $currentUser;
    $this->requestStack = $requestStack;
    $this->aliasManager = $aliasManager;
    $this->entityRepository = $entity_repository;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(DependencyInjectionContainerInterface $container): self {
    return new static(
      $container->get('entity_type.manager')->getViewBuilder('node'),
      $container->get('messenger'),
      $container->get('entity_type.manager'),
      $container->get('language_manager'),
      $container->get('current_user'),
      $container->get('request_stack'),
      $container->get('path_alias.manager'),
      $container->get('entity.repository'),
      $container->get('database'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'va_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array|bool {

    // Type of Evaluation.
    $evaluation_type = $this->getQueryUrl();
    // To'do remove this hard code to get the url query.
    $currentLanguage = $this->currentLanguage();
    $fieldDetails = $this->getFieldDetails($evaluation_type, $currentLanguage);
    // Add a text above the rendered nodes.
    $form = [
      'intro_container' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => 'intro-container',
        ],
        'intro_label' => [
          '#type' => 'item',
          '#markup' => $fieldDetails['title'],
        ],
        'intro' => [
          '#type' => 'markup',
          '#markup' => $fieldDetails['description'],
          '#attributes' => [
            'data-id' => $evaluation_type,
          ],
        ],
      ],
    ];

    $nodes = $this->getAllNodes();
    // Early return to nodes.
    if (empty($nodes)) {
      return FALSE;
    }

    // To store all the value published.
    foreach ($nodes as $node) {
      $getTranslation = $this->getTranslationField($node, $currentLanguage);

      // If don't find translation, move to the next one.
      if (!$getTranslation) {
        continue;
      }

      // Get the type, All, Personal or Organizational.
      $field_type_Value = $getTranslation->get('pva_type_field')->value;
      if ($field_type_Value == 'all' || ($field_type_Value == $evaluation_type)) {
        $title = ucfirst($getTranslation->getTitle());
        $titleUnderScore = trim(str_replace(' ', '_', $title));
        $all_pv[$titleUnderScore] = [
          '#type' => 'textfield',
          '#title' => $title,
          '#attributes' => [
            'class' => ['value-item-class'],
            'data-id' => $node->id(),
            'id' => $node->id(),
          ],
        ];
      }
    }

    // Add the rendered fields to a parent div.
    $form[$evaluation_type] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['pv-wrapper'],
      ],
      'inputs' => $all_pv,
    ];

    $form['selected-popup'] = [
      '#type' => 'fieldset',
      '#prefix' => '<div class="div-selected-popup popup-invisible">',
      '#suffix' => '</div>',
      '#title' => $this->t('Selected Popup'),
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Finish'),
      '#attributes' => [
        'disabled' => 'disabled',
        'name' => 'pva-submit',
        'data-id' => $evaluation_type,
      ],
      '#prefix' => '<div class="pv-submit-class">',
      '#suffix' => '</div>',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {

    $allnId = $this->getAllSelectedValues($form_state);
    // Check if the number of numeric values is equal to 10.
    // It's a just double check, on JS we had the similar validation.
    if (!$allnId) {
      $form_state->setErrorByName('pva-submit',
      $this->t('Do you have to choice ' . self::VALUES_TIME . ' items.'));
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {

    // Get the triggering element.
    $triggering_element = $form_state->getTriggeringElement();

    // Get the evaluation type of the data-id attribute.
    $evaluation_type = $triggering_element['#attributes']['data-id'];

    $allnId = $this->getAllSelectedValues($form_state);

    // Save the form data to the database.
    $fields = [
      'create_time' => $this->currentDateTime(),
      'user' => $this->currentUser()->id(),
      'langcode' => $this->currentLanguage(),
      'type_evaluation' => $evaluation_type,
    ];

    foreach ($allnId as $key => $value) {
      $fields['nid_' . $key] = $value;
    }

    $this->database->insert('va_evaluation')
      ->fields($fields)
      ->execute();

    $this->messenger->addMessage($this->t('Thank you! Here your evaluation detailed'));

    // Build the redirect URL with the current language.
    $redirect_url = Url::fromRoute('va_custom.va_results')->toString();

    // Redirect the user to the new URL.
    $response = new RedirectResponse($redirect_url);
    $response->send();

  }

  /**
   * {@inheritdoc}
   */
  public function currentDateTime(): string {
    // Create a new DrupalDateTime object for the current date and time.
    $date_time = new DrupalDateTime();
    // Set the timezone if necessary.
    $date_time->setTimezone(new \DateTimezone('UTC'));
    // Format the date and time as a string in the desired format.
    return $date_time->format('m-d-Y H:i:s');
  }

  /**
   * {@inheritdoc}
   */
  public function getAllNodes(): array {
    // Load the nodes.
    $node_storage = $this->getStorageNode();
    return $node_storage->loadByProperties([
      'type' => 'pva',
      'status' => 1,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getTranslationField($node, $langcode): Node|bool {
    if ($node->hasTranslation($langcode)) {
      return $node->getTranslation($langcode);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getStorageNode(): object {
    return $this->entityTypeManager->getStorage('node');
  }

  /**
   * {@inheritdoc}
   */
  public function getAllSelectedValues($form_state): array {
    $values = $form_state->getValues();
    $x = 0;
    // Loop through the form values and do something with each one.
    foreach ($values as $value) {
      // That's means the node id is storaged here.
      if (!is_numeric($value)) {
        continue;
      }
      if (!isset($nIdSelect[$x])) {
        $nIdSelect[$x] = $value;
      }
      else {
        $nIdSelect[$x] .= $value;
      }
      $x++;
    }
    return $nIdSelect;
  }

  /**
   * {@inheritdoc}
   */
  public function currentLanguage(): string {
    return $this->languageManager->getCurrentLanguage()->getId();
  }

  /**
   * {@inheritdoc}
   */
  protected function currentUser(): object {
    return $this->currentUser;
  }

  /**
   * {@inheritdoc}
   */
  protected function getQueryUrl(): mixed {
    $currentRequest = $this->requestStack->getCurrentRequest();
    $currentUrlQuery = $currentRequest->query->get('tp');
    if (empty($currentUrlQuery)) {
      $response = new RedirectResponse('/home');
      $response->send();
      return FALSE;
    }

    if ($currentUrlQuery == 'psn') {
      return 'personal';
    }
    return 'organizational';
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccessPermission(): bool {
    $account = $this->currentUser();
    if (!$account->isAuthenticated()) {
      throw new AccessDeniedHttpException();
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function getFieldDetails($type, $language): mixed {
    $path = $this->aliasManager->getPathByAlias('/home');
    if (empty($path)) {
      return FALSE;
    }
    $path_args = explode('/', $path);
    $nId = end($path_args);
    $node = $this->getStorageNode()->load($nId);
    return $this->getFieldTypeEvaluation($type, $node, $language);

  }

  /**
   * {@inheritdoc}
   */
  protected function getFieldTypeEvaluation($type, $node, $language): array {
    $link_field = $this->getTranslationField($node, $language)
      ->get('field_' . $type)->getValue();
    $link_title = $link_field[0]['title'];
    $description = $this->getTranslationField($node, $language)
      ->get('field_' . $type . '_summary')->getValue();
    $description = $description[0]['value'];
    return [
      'title' => $link_title,
      'description' => $description,
    ];
  }

}
