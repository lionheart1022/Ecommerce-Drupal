<?php

namespace Drupal\cpl_commerce_payment\Form;

use Drupal\commerce_nmi\Plugin\Commerce\PaymentGateway\NMIInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\Form\PaymentMethodDeleteForm as BasePaymentMethodDeleteForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the payment method delete form.
 *
 * Since we don't want to delete the payment method if there is a reference
 * to it - we simply mark it as not reusable.
 */
class PaymentMethodDeleteForm extends BasePaymentMethodDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method */
    $payment_method = $this->getEntity();
    /** @var \Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsStoredPaymentMethodsInterface $payment_gateway_plugin */
    $payment_gateway_plugin = $payment_method->getPaymentGateway()->getPlugin();
    $form_state->setRedirectUrl($this->getRedirectUrl());

    $order_reference_count = $this->entityManager
      ->getStorage('commerce_order')
      ->getQuery()
      ->condition('payment_method', $payment_method->id(), '=')
      ->count()
      ->execute();
    $payment_reference_count = $this->entityManager
      ->getStorage('commerce_payment')
      ->getQuery()
      ->condition('payment_method', $payment_method->id(), '=')
      ->count()
      ->execute();

    // Completely delete the payment method if there is no references to it.
    if ($order_reference_count || $payment_reference_count) {
      try {
        if ($payment_gateway_plugin instanceof NMIInterface) {
          $payment_gateway_plugin->deleteCustomerVault($payment_method);
        }

        $payment_method->setReusable(FALSE);
        $payment_method->save();
      }
      catch (PaymentGatewayException $e) {
        $this->messenger()->addError($e->getMessage());
        return;
      }

      $this->messenger()->addMessage($this->getDeletionMessage());
      $this->logDeletionMessage();
    }
    else {
      parent::submitForm($form, $form_state);
    }
  }

}
