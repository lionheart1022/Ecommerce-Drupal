<?php

namespace Drupal\cpl_commerce_checkout\Plugin\Commerce\CheckoutFlow;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowWithPanesBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the default multistep checkout flow.
 *
 * @CommerceCheckoutFlow(
 *   id = "cpl_commerce_checkout_4step",
 *   label = "4 step checkout",
 * )
 */
class Multistep4Steps extends CheckoutFlowWithPanesBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $parent = parent::defaultConfiguration();
    return $parent + [
      'display_cart_link' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['display_cart_link'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display Cart link'),
      '#description' => $this->t('Used by the checkout progress block.'),
      '#default_value' => $this->configuration['display_cart_link'],
    ];

    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['display_cart_link'] = $values['display_cart_link'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getSteps() {
    // Note that previous_label and next_label are not the labels
    // shown on the step itself. Instead, they are the labels shown
    // when going back to the step, or proceeding to the step.
    return [
      'login' => [
        'label' => $this->t('Login'),
        'previous_label' => $this->t('Return to login'),
        'has_sidebar' => FALSE,
        'hidden' => TRUE,
      ],
      'customer_information' => [
        'label' => $this->t('Customer Info'),
        'has_sidebar' => TRUE,
        'previous_label' => $this->t('Return to customer information'),
        'next_label' => $this->t('Continue to customer information'),
      ],
      'shipping_method' => [
        'label' => $this->t('Shipping method'),
        'has_sidebar' => TRUE,
        'previous_label' => $this->t('Return to shipping method'),
        'next_label' => $this->t('Continue to shipping method'),
      ],
      'payment_information' => [
        'label' => $this->t('Payment method'),
        'has_sidebar' => TRUE,
        'previous_label' => $this->t('Return to payment method'),
        'next_label' => $this->t('Continue to payment method'),
      ],
      'payment' => [
        'label' => $this->t('Payment'),
        'next_label' => $this->t('Continue'),
        'has_sidebar' => TRUE,
      ],
      'complete' => [
        'label' => $this->t('Complete'),
        'next_label' => $this->t('Pay and complete purchase'),
        'has_sidebar' => TRUE,
      ],
    ];
  }

}
