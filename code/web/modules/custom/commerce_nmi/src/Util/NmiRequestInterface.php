<?php

namespace Drupal\commerce_nmi\Util;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_price\Price;
use Drupal\profile\Entity\ProfileInterface;

/**
 * Provides the interface for the NmiRequest service.
 *
 * @package Drupal\commerce_nmi\Util
 */
interface NmiRequestInterface {

  /**
   * Get the request data.
   *
   * @return array
   *   An array with the request data.
   */
  public function getRequest();

  /**
   * Sets the API key.
   *
   * @param string $api_key
   *   The API key.
   *
   * @return $this
   */
  public function setApiKey($api_key);

  /**
   * Sets an amount to the request from the commerce price.
   *
   * @param \Drupal\commerce_price\Price $amount
   *   The amount.
   *
   * @return $this
   */
  public function setAmount(Price $amount);

  /**
   * Sets an amount to the request from the order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return $this
   */
  public function setAmountFromOrder(OrderInterface $order);

  /**
   * Gets the amount to pay.
   *
   * @return array
   *   An associative array with amount. Array keys: number, currency_code.
   */
  public function getAmount();

  /**
   * Add the order data to the request.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   Drupal commerce order.
   *
   * @return $this
   */
  public function setOrder(OrderInterface $order);

  /**
   * Attaches the customer information to the request.
   *
   * @param \Drupal\profile\Entity\ProfileInterface $profile
   *   The customer profile.
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The commerce order.
   * @param string $phone_field_name
   *   The name of the field from which a phone can be retrieved.
   * @param string $type
   *   A type of information to attach.
   *   'billing' for billing. Otherwise - shipping.
   *
   * @return $this
   */
  public function setCustomerInfo(ProfileInterface $profile, OrderInterface $order, $phone_field_name, $type = 'billing');

  /**
   * Initiates sale or auth request.
   *
   * Submits all transaction details to the Payment Gateway except
   * the customer's sensitive payment information. The Payment Gateway
   * will return a variable form-url.
   *
   * @param string $redirect_url
   *   The URL that handles a future browser redirect.
   * @param bool $capture
   *   Whether execute sale request (if TRUE) or auth otherwise.
   * @param string|int|null $customer_vault_id
   *   The customer Vault ID.
   *
   * @return \SimpleXMLElement
   *   The API response.
   *
   * @throws \Drupal\commerce_payment\Exception\PaymentGatewayException
   */
  public function saleRequest($redirect_url, $capture, $customer_vault_id = NULL);

  /**
   * Executes capture request.
   *
   * @param string $transaction_id
   *   The NMI transaction id.
   * @param int|null $amount
   *   The amount to capture.
   *
   * @return \SimpleXMLElement
   *   The API response.
   */
  public function doCapture($transaction_id, $amount = NULL);

  /**
   * Executes refund request.
   *
   * @param string $transaction_id
   *   The NMI transaction id.
   * @param int|null $amount
   *   The amount to refund.
   *
   * @return \SimpleXMLElement
   *   The API response.
   */
  public function doRefund($transaction_id, $amount = NULL);

  /**
   * Executes void request.
   *
   * @param string $transaction_id
   *   The NMI transaction id.
   *
   * @return \SimpleXMLElement
   *   The API response.
   */
  public function doVoid($transaction_id);

  /**
   * Sets add-customer parameter.
   *
   * @return $this
   */
  public function addCustomer();

  /**
   * Deletes the customer vault record.
   *
   * @param string|int $customer_vault_id
   *   The customer vault ID.
   *
   * @return \SimpleXMLElement
   *   The API response.
   */
  public function deleteCustomerVault($customer_vault_id);

  /**
   * Completes three step redirect API transaction.
   *
   * @param string $token_id
   *   The token returned by NMI.
   *
   * @return \SimpleXMLElement
   *   The API response.
   */
  public function completeAction($token_id);

}
