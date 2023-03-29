<?php

namespace Drupal\va_custom\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface as DependencyInjectionContainerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\node\Entity\Node;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a form to define a personal values.
 */
class PvaResultForm extends FormBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The Drupal database.
   *
   * @var Drupal\Core\Database\Connection
   */
  protected $database;

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
   * The Request Stack service.
   *
   * @var Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    AccountInterface $currentUser,
    MessengerInterface $messenger,
    Connection $database,
    EntityTypeManagerInterface $entityTypeManager,
    LanguageManagerInterface $languageManager,
    RequestStack $requestStack,
  ) {
    $this->currentUser = $currentUser;
    $this->messenger = $messenger;
    $this->database = $database;
    $this->entityTypeManager = $entityTypeManager;
    $this->languageManager = $languageManager;
    $this->requestStack = $requestStack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(DependencyInjectionContainerInterface $container): self {
    return new static(
      $container->get('current_user'),
      $container->get('messenger'),
      $container->get('database'),
      $container->get('entity_type.manager'),
      $container->get('language_manager'),
      $container->get('request_stack'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'pva_result_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $currentRequest = $this->requestStack->getCurrentRequest();
    $test_id = $currentRequest->query->get('id');

    $current_user = $this->currentUser();

    // Get the current user object.
    $current_user_id = $current_user->id();

    // Get the username of the current user.
    $current_user_name = $current_user->getAccount()->name;

    if (!empty($test_id)) {
      // Call the results.
      $results = $this->getTestResult($current_user_id, $test_id);
    }
    else {
      // Call the results.
      $results = $this->getTestResult($current_user_id);
    }

    if (empty($results)) {
      return $form['bad-results'] = [
        '#type' => 'texfield',
        '#title' => $this->t('No results found for your user'),
        '#prefix' => '<div class="bad-result">',
        '#suffix' => '</div>',
      ];
    }

    $results = reset($results);

    // Title to result.
    $current_user_name = ucfirst($current_user_name);
    if ($results->type_evaluation == 'personal') {
      $result_text = ' your Personal Values (PV)';
    }
    else {
      $result_text = ' your Cultural Values (CV)';
    }

    $form['results'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#prefix' => '<div class="title-results">',
      '#suffix' => '</div>',
      '#value' => $current_user_name . $this->t($result_text),
    ];

    // Langcode evaluation test.
    $langcode = $results->langcode;

    // Current langcode.
    $current_langcode = $this->currentLanguage();

    // When user change the language at the result page.
    if ($langcode != $current_langcode) {
      $langcode = $current_langcode;
    }

    $consciousnessAndValues = [];
    foreach ($results as $result => $value) {

      if (str_starts_with($result, "nid_")) {

        $node = $this->getStorageNode();
        $node = $node->load($value);
        // If the node didn't have translation, get the default language.
        $field = $this->getTranslationField($node, $langcode);
        if ($field == FALSE) {
          $field = $node;
        }
        $pva_limiting_factor = $field->get('pva_limiting_factor')->getValue()[0]['value'];
        $pva_consciousness = ucfirst(
          $field->get('pva_consciousness')->getValue()[0]['value']
        );
        $valueTitleFactor = [
          ucfirst($field->getTitle()) => [
            $pva_limiting_factor,
          ],
        ];

        $consciousnessAndValues[$pva_consciousness][] = $valueTitleFactor;

      }
    }

    $form['parent-triangle'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['parent-triangle'],
      ],
    ];

    $cons_titles = $this->getAllConsciousness();

    $form['parent-triangle']['title'] = [
      '#type' => 'container',
      '#tag' => 'div',
      '#attributes' => [
        'class' => ['triangle-title-parent'],
      ],
    ];

    $valuesToLastColumn = [];
    foreach ($cons_titles as $cons_title) {
      $form['parent-triangle']['title'][$cons_title] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t($cons_title),
        '#attributes' => [
          'class' => ['triangle-title'],
        ],
      ];

      // Just add the border crossing the triangle.
      $form['parent-triangle']['title'][$cons_title]['container-values'] = [
        '#type' => 'container',
        '#tag' => 'div',
        '#attributes' => [
          'class' => ['container-values', strtolower($cons_title) .
            '-container-class'],
          'id' => strtolower($cons_title),
        ],
      ];

      if (isset($consciousnessAndValues[$cons_title])) {
        $values = $consciousnessAndValues[$cons_title];
        foreach ($values as $keys => $value) {
          $valuesToLastColumn[] = array_keys($value)[0];
          $field_value = array_keys($value)[0];
          if ($field_value == 'caution') {
            $field_value;
          }
          $factor_limiting = 'not-limiting';
          if ($value[$field_value][0] == 1) {
            $factor_limiting = 'limiting-factor';
            $field_value = '(L) ' . $field_value;
          }

          $form['parent-triangle']['title'][$cons_title]['container-values'][$field_value] =
          [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#attributes' => [
              'class' => [
                'values-inline', strtolower($cons_title) . '-value-class',
              ],
              'title' => $this->t($field_value),
              'role' => $factor_limiting,
            ],
          ];
        }
      }
    }

    // Third column.
    $form['parent-triangle']['values']['value'] = [
      '#type' => 'container',
      '#tag' => 'div',
      '#attributes' => [
        'class' => ['container-third-column'],
      ],
    ];

    foreach ($valuesToLastColumn as $valueToLastColumn) {
      $form['parent-triangle']['values']['value'][$valueToLastColumn] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t($valueToLastColumn),
        '#attributes' => [
          'class' => ['third-column'],
        ],
      ];
    }
    

    $redirect_to_explanation = '<a href="/explanation">';
    if ($this->currentLanguage() == 'pt-br') {
      $redirect_to_explanation = '<a href="/pt-br/explanation">';
    }

    $form['explanation-link'] = [
      '#markup' => $redirect_to_explanation . $this->t('What are the stages of consciousness?') .'</a>',
      '#prefix' => '<div class="explanation-result">',
      '#suffix' => '</div>',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getTestResult(int $current_user_id, int $test_id = 0): array {
    // Retrieve data from the database using Drupal's database API.
    $query = $this->database->select('va_evaluation', 'cpe');
    $query->fields('cpe');
    // Add a WHERE clause to the query to filter by user.
    $query->condition('cpe.user', $current_user_id, '=');

    if ($test_id != 0) {
      $query->condition('cpe.id', $test_id, '=');
    }
    // Add an ORDER BY clause to sort by create time in descending order.
    $query->orderBy('cpe.create_time', 'DESC');

    if (!$test_id == 0) {
      // Limit the number of results to 1.
      $query->range(0, 1);
    }

    return $query->execute()->fetchAll();

  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {

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
  public function getStorageNode(): object {
    return $this->entityTypeManager->getStorage('node');
  }

  /**
   * {@inheritdoc}
   */
  public function getTranslationField(Node $node, string $langcode): Node|bool {
    if ($node->hasTranslation($langcode)) {
      return $node->getTranslation($langcode);
    }

    return FALSE;
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
  protected function getAllConsciousness(): array {
    // Specify the entity type and bundle.
    $entity_type = 'node';
    $bundle = 'pva';

    // Specify the field name.
    $field_name = 'pva_consciousness';

    // Retrieve the field settings.
    $field_settings = $this->entityTypeManager
      ->getStorage('field_config')
      ->loadByProperties([
        'field_name' => $field_name,
        'entity_type' => $entity_type,
        'bundle' => $bundle,
      ]);

    // Retrieve the options for the field.
    $options = $field_settings[$entity_type . '.' . $bundle . '.' . $field_name]->getSettings()['allowed_values'];
    $consciousnessOptions = [];
    foreach ($options as $option => $value) {
      $consciousnessOptions[] .= ucfirst($option);
    }

    return array_reverse($consciousnessOptions);
  }

}
