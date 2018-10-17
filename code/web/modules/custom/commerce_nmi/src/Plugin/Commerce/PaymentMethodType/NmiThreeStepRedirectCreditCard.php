<?php

namespace Drupal\commerce_nmi\Plugin\Commerce\PaymentMethodType;

use Drupal\entity\BundleFieldDefinition;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentMethodType\CreditCard;

/**
 * The NMI credit card payment method type using three step redirect API.
 *
 * We need this payment method type as "credit_card" payment method type
 * requires credit card details at the payment_information checkout step.
 * But we need it at the payment checkout step.
 *
 * @CommercePaymentMethodType(
 *   id = "nmi_3sr_cc",
 *   label = @Translation("Credit card"),
 *   create_label = @Translation("New credit card"),
 * )
 */
class NmiThreeStepRedirectCreditCard extends CreditCard {

  /**
   * {@inheritdoc}
   */
  public function buildLabel(PaymentMethodInterface $payment_method) {
    // With NMI, the user doesn't enter credit card details at the
    // payment_information pane, but Drupal Commerce expects to have credit card
    // details at this step. So if the user goes to payment_information->
    // ->payment->return back to "payment_information" he will see
    // "New credit card" and the existing nmi_3sr_cc payment method with no
    // credit card details. Handle it.
    $empty_card_type = empty($payment_method->card_type) || empty($payment_method->card_type->value);
    $empty_card_number = empty($payment_method->card_number) || empty($payment_method->card_number->value);
    if ($empty_card_type || $empty_card_number) {
      return $this->t('NMI Credit card');
    }

    return parent::buildLabel($payment_method);
  }

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions() {
    $fields = parent::buildFieldDefinitions();

    $fields['customer_vault_id'] = BundleFieldDefinition::create('integer')
      ->setLabel(t('Customer Vault ID'))
      ->setDescription(t('The last few digits of the credit card number'))
      ->setRequired(TRUE)
      ->setSetting('unsigned', TRUE)
      ->setSettings([
        'unsigned' => TRUE,
        // NMI docs say nothing regarding customer vault id Length,
        // so assign the biggest length for it.
        'size' => 'big',
      ]);

    return $fields;
  }

}
