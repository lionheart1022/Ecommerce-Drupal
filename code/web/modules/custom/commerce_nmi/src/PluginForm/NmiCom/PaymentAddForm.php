<?php

namespace Drupal\commerce_nmi\PluginForm\NmiCom;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\commerce_nmi\Plugin\Commerce\PaymentGateway\NMI;
use Drupal\commerce_payment\PluginForm\PaymentGatewayFormBase;

/**
 * NMI Payment add form.
 *
 * @package Drupal\commerce_nmi\PluginForm\NmiCom
 */
class PaymentAddForm extends PaymentGatewayFormBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_payment\Entity\Payment $payment */
    $payment = $this->getEntity();
    if (!($order = $payment->getOrder())) {
      throw new \InvalidArgumentException('Payment entity with no order reference given to PaymentAddForm.');
    }

    $form['offsite_payment'] = [
      '#type' => 'commerce_payment_gateway_form',
      '#operation' => 'offsite-payment',
      '#default_value' => $payment,
      '#return_url' => NMI::buildReturnUrl($order, TRUE)->toString(),
      // Cancel_url - dummy element.
      // Required by PaymentOffsiteForm::buildConfigurationForm.
      '#cancel_url' => NMI::buildCancelUrl($order)->toString(),
      '#exception_message' => $this->t('We encountered an unexpected error processing your payment. Please try again later.'),
      '#capture' => $form['#capture'],
      '#amount' => $form['#amount'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Do nothing.
  }

}
