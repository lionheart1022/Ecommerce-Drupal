<?php

namespace Drupal\commerce_nmi\PluginForm\NmiCom;

use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\PluginForm\PaymentMethodAddForm as BasePaymentMethodAddForm;
use Drupal\commerce_order\Entity\OrderInterface;

/**
 * Provides PaymentMethodAddForm for the NMI payment gateway.
 *
 * @package Drupal\commerce_nmi\PluginForm\NmiCom
 */
class PaymentMethodAddForm extends BasePaymentMethodAddForm {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method */
    $payment_method = $this->entity;

    if ($payment_method->bundle() === 'nmi_3sr_cc') {
      $form['payment_details'] = $this->buildNmi3srCreditCardForm($form['payment_details'], $form_state);
    }

    return $form;
  }

  /**
   * Builds NMI payment form.
   *
   * @param array $element
   *   The target element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the complete form.
   *
   * @return array
   *   The built NMI form.
   */
  protected function buildNmi3srCreditCardForm(array $element, FormStateInterface $form_state) {
    $payment_method = $this->entity;
    $payment_gateway = $payment_method->getPaymentGateway();
    $order = $this->routeMatch->getParameter('commerce_order');

    if (!$order || !($order instanceof OrderInterface)) {
      // As per #817160, #2055851 issues - don't translate exceptions.
      throw new PaymentGatewayException(sprintf('The payment gateway "%s" requires an order entity at this checkout step.', $payment_gateway->label()));
    }

    // At the step 1 NMI.com three step API requires only billing/shipping
    // information. Add dummy payment details as
    // Drupal\commerce_nmi\Plugin\Commerce\PaymentGateway::createPaymentMethod
    // expects an array of payment details.
    $element['order_id'] = [
      '#type' => 'value',
      '#value' => $order->id(),
    ];

    return $element;
  }

}
