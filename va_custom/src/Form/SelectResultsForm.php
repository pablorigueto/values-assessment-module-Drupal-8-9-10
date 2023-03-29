<?php

namespace Drupal\va_custom\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 *
 */
class SelectResultsForm extends FormBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The Drupal database.
   *
   * @var Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The Language Manager Interface.
   *
   * @var Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs a PatentMarkingForm object.
   */
  public function __construct(
    AccountInterface $currentUser,
    Connection $database,
    LanguageManagerInterface $languageManager,
  ) {
    $this->currentUser = $currentUser;
    $this->database = $database;
    $this->languageManager = $languageManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('current_user'),
      $container->get('database'),
      $container->get('language_manager'),
    );
  }

  /**
   *
   */
  public function getFormId(): string {
    return 'select_results_form';
  }

  /**
   *
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $current_user = $this->currentUser();

    // Get the current user object.
    $current_user_id = $current_user->id();

    $previous_results = $this->getTestResult($current_user_id);

    $start_test = $this->t("Start a new test");
    $previous_test = $this->t("Select a previous test");

    if (!empty($previous_results)) {
      $options = ['' => $previous_test];
      foreach ($previous_results as $previous_result) {
        $options[$previous_result->id] =
          ucfirst($previous_result->type_evaluation) . ' ' .
          $previous_result->create_time;
      }

      $form['select_field'] = [
        '#type' => 'select',
        '#title' => $this->t('You have previous tests'),
        '#options' => $options,
        '#ajax' => [
          'callback' => [$this, 'redirectToOldTest'],
          'event' => 'change',
        ],
      ];
    }

    $redirect_to_new_test = '<a href="/">';
    if ($this->currentLanguage() == 'pt-br') {
      $redirect_to_new_test = '<a href="/pt-br/">';
    }

    // Link to homepage through the my account page.
    $form['markup'] = [
      '#markup' => '
        <div class="user-account-links" id="user_account_links">' .
      '<ul>
          <li>' .
          $redirect_to_new_test . $start_test . '</a>
          </li>
        </ul>
      </div>',
    ];

    return $form;
  }

  /**
   * Select company change.
   *
   * @param array $form
   *   The form definition.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An AJAX response to replace content after change the select.
   */
  public function redirectToOldTest(array &$form, FormStateInterface $form_state): AjaxResponse {

    $test_id = $form_state->getValue('select_field');
    $url = Url::fromRoute('va_custom.pva_results', [], ['query' => ['id' => $test_id]]);
    $response = new AjaxResponse();
    $response->addCommand(new RedirectCommand($url->toString()));
    return $response;

  }

  /**
   * {@inheritdoc}
   */
  public function currentUser(): object {
    return $this->currentUser;
  }

  /**
   * {@inheritdoc}
   */
  public function getTestResult(int $current_user_id): array {
    // Retrieve data from the database using Drupal's database API.
    $query = $this->database->select('va_evaluation', 'cpe');
    $query->fields('cpe');
    // Add a WHERE clause to the query to filter by user.
    $query->condition('cpe.user', $current_user_id, '=');
    // Add an ORDER BY clause to sort by create time in descending order.
    $query->orderBy('cpe.create_time', 'DESC');
    // Limit the number of results to 1.
    // $query->range(0, 1);.
    return $query->execute()->fetchAll();

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * Search code submit.
   *
   * @param array $form
   *   The form definition.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function searchCode(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function currentLanguage(): string {
    return $this->languageManager->getCurrentLanguage()->getId();
  }

}
