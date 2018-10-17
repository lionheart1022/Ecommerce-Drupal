<?php

namespace Drupal\dcom_back_in_stock\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\dcom_back_in_stock\BackInStockClassService;

/**
 * Class ContentEntityExampleSettingsForm.
 *
 * @ingroup stock_notification
 */
class StockNotificationSettingsForm extends FormBase {

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'stock_notification_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'dcom_back_in_stock.config',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory()->getEditable('dcom_back_in_stock.config')
      ->set('inventory_policy', $form_state->getValue('inventory_policy'))
      ->save();
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('dcom_back_in_stock.config');

    $form['inventory_policy'] = [
      '#type' => 'select',
      '#title' => $this->t('Inventory policy'),
      '#description' => $this->t('Inventory policy for all products. Note that individual products or product variations may override this setting.'),
      '#default_value' => $config->get('inventory_policy', BackInStockClassService::AVAIL_ODOO),
      '#options' => [
        BackInStockClassService::AVAIL_ODOO => $this->t('Use Odoo stock status'),
        BackInStockClassService::AVAIL_FORCE_AVAIL => $this->t('Force available'),
        BackInStockClassService::AVAIL_FORCE_NOTAVAIL => $this->t('Force not available'),
      ],
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

}
