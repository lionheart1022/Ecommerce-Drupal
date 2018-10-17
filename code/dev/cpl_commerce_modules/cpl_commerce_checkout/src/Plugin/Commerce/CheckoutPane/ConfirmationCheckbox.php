<?php

namespace Drupal\cpl_commerce_checkout\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the confirmation checkbox pane.
 *
 * @CommerceCheckoutPane(
 *   id = "cpl_commerce_checkout_confirmation_checkbox",
 *   label = @Translation("CPL - Confirmation checkbox"),
 *   admin_label = @Translation("CPL - Confirmation checkbox"),
 * )
 */
class ConfirmationCheckbox extends CheckoutPaneBase implements CheckoutPaneInterface {

  /**
   * {@inheritdoc}
   */
  public function isVisible() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $parent = parent::defaultConfiguration();
    $default = [
      'confirmation_checkbox_title' => $this->t('Confirmation checkbox'),
      'confirmation_checkbox_validation_message' => $this->t('Confirmation checkbox needs to be checked'),
    ];
    return $parent + $default;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationSummary() {
    $summary = parent::buildConfigurationSummary();
    $summary .= '<br>';

    if (!empty($this->configuration['confirmation_checkbox_title'])) {
      $summary .= $this->t('Confirmation checkbox title: @title', ['@title' => $this->configuration['confirmation_checkbox_title']]);
      $summary .= '<br>';
    }

    if (!empty($this->configuration['confirmation_checkbox_validation_message'])) {
      $summary .= $this->t('Confirmation checkbox validation message: @message', ['@message' => $this->configuration['confirmation_checkbox_validation_message']]);
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['confirmation_checkbox_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Confirmation checkbox title'),
      '#required' => TRUE,
      '#description' => $this->t('Set the title for confirmation checkbox.'),
      '#default_value' => !empty($this->configuration['confirmation_checkbox_title']) ? $this->configuration['confirmation_checkbox_title'] : $this->t('Confirmation checkbox'),
    ];

    $form['confirmation_checkbox_validation_message'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Confirmation checkbox validatoin message'),
      '#description' => $this->t('Set validation message for confirmation checkbox.'),
      '#default_value' => !empty($this->configuration['confirmation_checkbox_validation_message']) ? $this->configuration['confirmation_checkbox_validation_message'] : $this->t('Confirmation checkbox needs to be checked'),
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
      $this->configuration['confirmation_checkbox_title'] = $values['confirmation_checkbox_title'];
      $this->configuration['confirmation_checkbox_validation_message'] = $values['confirmation_checkbox_validation_message'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $pane_form['confirmation_checkbox'] = [
      '#type' => 'checkbox',
      '#default_value' => FALSE,
      '#title' => $this->configuration['confirmation_checkbox_title'],
    ];

    return $pane_form;
  }

  /**
   * {@inheritdoc}
   */
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $values = $form_state->getValue($pane_form['#parents']);
    if (empty($values['confirmation_checkbox'])) {
      $form_state->setError($pane_form, $this->configuration['confirmation_checkbox_validation_message']);
    }
  }

}
