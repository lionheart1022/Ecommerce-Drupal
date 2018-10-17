<?php

namespace Drupal\dcom_back_in_stock\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\dcom_back_in_stock\Entity\StockNotification;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class DcomBackInStockNotificationForm.
 *
 * @package Drupal\dcom_back_in_stock\Form
 */
class DcomBackInStockNotificationForm extends FormBase {

  /**
   * The theme manager.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected $themeManager;

  /**
   * Class constructor.
   */
  public function __construct(ThemeManagerInterface $theme_manager) {
    $this->themeManager = $theme_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('theme.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dcom_back_in_stock_notification';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#prefix'] = '<div id="modal-wrapper">';
    $form['#suffix'] = '</div>';
    $form['email'] = [
      '#type' => 'email',
      '#title' => t('Your email:'),
      '#required' => TRUE,
    ];

    $form['variation_id'] = [
      '#type' => 'hidden',
      '#default_value' => $form_state->getBuildInfo()['args'][0],
    ];

    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Submit'),
      '#ajax' => [
        'callback' => '::createStockNotificationAjax',
        'wrapper' => 'modal-wrapper',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $active_theme = $this->themeManager->getActiveTheme();
    StockNotification::create([
      'session_id' => \Drupal::service('session_manager')->getId(),
      'email' => $form_state->getValue('email'),
      'product_variation' => $form_state->getValue('variation_id'),
      'theme_id' => $active_theme->getName(),
    ])->save();
  }

  /**
   * {@inheritdoc}
   */
  public function createStockNotificationAjax(array &$form, FormStateInterface $form_state) {
    if ($form_state->getErrors()) {
      return $form;
    }
    $response = new AjaxResponse();

    $message = [
      '#type' => 'container',
      '#attributes' => [
        'class' => 'back-in-stock-message',
      ],
      '#plain_text' => t("We will notify you once this product will be available."),
    ];

    $response->addCommand(new CloseModalDialogCommand());
    $response->addCommand(new ReplaceCommand('#cpl-back-in-stock-form-wrapper', $message));

    return $response;
  }

}
