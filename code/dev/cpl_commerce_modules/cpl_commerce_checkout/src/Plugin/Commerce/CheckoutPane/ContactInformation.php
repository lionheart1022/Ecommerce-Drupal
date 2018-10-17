<?php

namespace Drupal\cpl_commerce_checkout\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\ContactInformation as ContactInformationBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the contact information pane.
 *
 * @CommerceCheckoutPane(
 *   id = "cpl_commerce_checkout_contact_information",
 *   label = @Translation("CPL - Contact information"),
 *   display_label = @Translation("Contact information"),
 *   default_step = "order_information",
 *   wrapper_element = "fieldset",
 * )
 */
class ContactInformation extends ContactInformationBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The user entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $userStorage;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    CheckoutFlowInterface $checkout_flow,
    EntityTypeManagerInterface $entity_type_manager,
    AccountInterface $current_user,
    LanguageManagerInterface $language_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $checkout_flow, $entity_type_manager);

    $this->currentUser = $current_user;
    $this->languageManager = $language_manager;
    $this->userStorage = $this->entityTypeManager->getStorage('user');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, CheckoutFlowInterface $checkout_flow = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $checkout_flow,
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $parent = parent::defaultConfiguration();
    $default = [
      'auto_register' => TRUE,
    ];
    return $parent + $default;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationSummary() {
    $summary = parent::buildConfigurationSummary();
    $summary .= '<br>';

    if (!empty($this->configuration['auto_register'])) {
      $summary .= $this->t('Automatic registration: Yes');
    }
    else {
      $summary .= $this->t('Automatic registration: No');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['auto_register'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Automatically register anonymous users'),
      '#description' => $this->t('Creates new accounts for anonymous users.'),
      '#default_value' => $this->configuration['auto_register'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['auto_register'] = !empty($values['auto_register']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    parent::validatePaneForm($pane_form, $form_state, $complete_form);

    if ($this->currentUser->isAnonymous()) {
      $values = $form_state->getValue($pane_form['#parents']);
      if ($this->userNotUnique($values['email'])) {
        $vars = [
          ':url' => Url::fromUri('internal:/user/login')->toString(),
        ];
        $form_state->setError($pane_form['email'], $this->t('An account already exists with this email address. Please <a href=":url">login</a> to complete your checkout or use a different email address.', $vars));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    parent::submitPaneForm($pane_form, $form_state, $complete_form);
    if (!empty($this->configuration['auto_register'])) {
      $this->ensureUserNotAnonymousSubmit($pane_form, $form_state, $complete_form);
    }
  }

  /**
   * Make sure that the Order is created for logged in User.
   *
   * @param array $pane_form
   *   Pane form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   * @param array $complete_form
   *   Complete form render array.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function ensureUserNotAnonymousSubmit(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    if ($this->currentUser->isAnonymous()) {
      $values = $form_state->getValue($pane_form['#parents']);

      // Creates new user account.
      if (!$this->userNotUnique($values['email'])) {
        $language = $this->languageManager->getCurrentLanguage()->getId();
        $new_user = User::create();
        $new_user->enforceIsNew();
        $new_user->setUsername('email_registration_' . user_password());
        $new_user->setEmail($values['email']);
        $new_user->set('langcode', $language);
        $new_user->set('preferred_langcode', $language);
        $new_user->activate();

        // Adds user first name / last name.
        $given_name = $form_state
          ->getValue([
            'cpl_commerce_checkout_shipping_profile',
            'shipping_profile',
            'address',
            0,
            'address',
            'given_name',
          ]);
        $family_name = $form_state
          ->getValue(['cpl_commerce_checkout_shipping_profile',
            'shipping_profile',
            'address',
            0,
            'address',
            'family_name',
          ]);
        $new_user->set('field_first_name', $given_name);
        $new_user->set('field_last_name', $family_name);

        if (!$given_name || !$family_name) {
          // @TODO Log the error.
        }

        // Save and login.
        $this->userSaveAndLogin($new_user);

        // Assign order to a new user.
        $this->order->setCustomer($new_user);

      }
    }
  }

  /**
   * Save new user, sign in and notify about creation.
   *
   * @param \Drupal\user\UserInterface $user
   *   User entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function userSaveAndLogin(UserInterface $user) {
    $user->save();
    user_login_finalize($user);
    _user_mail_notify('register_no_approval_required', $user);
  }

  /**
   * Check if there is registered user with given email.
   *
   * @param string $email
   *   Email address.
   *
   * @return bool
   *   TRUE - if user exists.
   */
  protected function userNotUnique($email) {
    return (bool) $this->userStorage
      ->getQuery()
      ->condition('mail', $email)
      ->range(0, 1)
      ->count()
      ->execute();
  }

}
