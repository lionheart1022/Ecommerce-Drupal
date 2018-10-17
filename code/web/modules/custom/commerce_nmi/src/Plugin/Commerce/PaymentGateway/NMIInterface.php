<?php

namespace Drupal\commerce_nmi\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsAuthorizationsInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsRefundsInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsStoredPaymentMethodsInterface;
use Drupal\commerce_price\Price;

/**
 * Provides the interface for the NMI payment gateway.
 */
interface NMIInterface extends SupportsStoredPaymentMethodsInterface, SupportsRefundsInterface, SupportsAuthorizationsInterface {

  /**
   * Returns "capture" setting from the payment_process pane.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The Order.
   *
   * @return bool
   *   TRUE if do capture, otherwise - false.
   */
  public function isCapturePayment(OrderInterface $order);

  /**
   * Sends step1 "sale" or "auth" request to NMI.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order entity.
   * @param \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method
   *   The payment method.
   * @param string $return_url
   *   Return url.
   * @param bool $capture
   *   Whether capture not payment.
   * @param \Drupal\commerce_price\Price|null $amount
   *   An amount to pay. If null - will be fetched from the order.
   *
   * @return array
   *   An associative array with the following keys:
   *   form_url - the form to which send payment sensitive data via POST
   *   transaction_id - the transaction id
   *   amount_to_pay - the amount to pay sent to NMI.
   *
   * @throws \Drupal\commerce_payment\Exception\PaymentGatewayException
   * @throws \Drupal\commerce_payment\Exception\DeclineException
   */
  public function saleRequest(OrderInterface $order, PaymentMethodInterface $payment_method, $return_url, $capture, Price $amount = NULL);

  /**
   * Deletes the customer vault record.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method
   *   The reusable payment method with a customer vault ID.
   *
   * @throws \Drupal\commerce_payment\Exception\PaymentGatewayException
   *   Throw if an error occurred while attempting to delete the customer vault.
   *
   * @return bool
   *   TRUE - deleted, FALSE if the payment method doesn't has a customer vault.
   */
  public function deleteCustomerVault(PaymentMethodInterface $payment_method);

  /**
   * Builds the payment return URL.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param bool $nmi_custom_route
   *   Whether return nmi custom return url or not.
   *
   * @return \Drupal\Core\Url
   *   The URL.
   */
  public static function buildReturnUrl(OrderInterface $order, $nmi_custom_route = FALSE);

  /**
   * Builds the URL to the "cancel" page.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return \Drupal\Core\Url
   *   The "cancel" page URL.
   */
  public static function buildCancelUrl(OrderInterface $order);

}
