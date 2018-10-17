<?php

namespace Drupal\dcom_shopify_migrate\Plugin\migrate\source;

use Drupal\migrate\MigrateException;

/**
 * Provides a Shopify Payment Transaction migrate source.
 *
 * Usage example:
 *
 * @code
 * source:
 *   plugin: dcom_shopify_payment_transaction
 * @endcode
 *
 * @MigrateSource(
 *  id = "dcom_shopify_payment_transaction"
 * )
 */
class PaymentTransaction extends Base {

  /**
   * Payment transactions aren't countable since they are properties of orders.
   *
   * {@inheritdoc}
   */
  protected $skipCount = TRUE;

  /**
   * Increase API fetch limit.
   *
   * Do not fetch too much at same time since each order may contain multiple
   * items.
   *
   * {@inheritdoc}
   */
  protected $pagerLimit = 100;

  /**
   * {@inheritdoc}
   */
  protected function getShopifyResource() {
    return 'orders/transactions';
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'id' => $this->t('The ID for the transaction'),
      'amount' => $this->t('The amount of money that the transaction was for'),
      'created_at' => $this->t('The date and time (ISO 8601 format) when the transaction was created'),
      'currency' => $this->t('The three-letter code (ISO 4217 format) for the currency used for the payment'),
      'gateway' => $this->t('The name of the gateway the transaction was issued through'),
      'kind' => $this->t("The transaction's type"),
      'order_id' => $this->t('The ID for the order that the transaction is associated with'),
      'status' => $this->t('The status of the transaction.'),
      'test' => $this->t('Whether the transaction is a test transaction.'),
      // @TODO: Add more fields.
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getFileScanDirectoryMask() {
    return '/^\d+\.json$/';
  }

  /**
   * {@inheritdoc}
   */
  protected function initializeIterator() {
    foreach ($this->getJsonFiles() as $filename) {
      // As per shopify docs:
      // An order can have no more than 100 transactions associated with it.
      $payment_transactions = $this->loadJsonFile($filename);
      $ids = array_map(function ($payment_transaction) {
        return $payment_transaction->id;
      }, $payment_transactions);

      // Payment transaction array keyed by the transaction id.
      $payment_transactions = array_combine($ids, $payment_transactions);

      foreach ($payment_transactions as $id => $transaction) {
        if ($transaction->kind == 'refund') {
          $empty_parent = empty($payment_transactions[$transaction->parent_id]);
          $test_refund = $transaction->test;
          $not_success = $transaction->status != 'success';

          if ($empty_parent || $test_refund || $not_success) {
            continue;
          }

          $parent = &$payment_transactions[$transaction->parent_id];

          if ($transaction->currency != $parent->currency) {
            // TODO Support this case.
            throw new MigrateException('Unsupported multi currency between payment and refund transactions.');
          }

          $parent->refunded_amount = isset($parent->refunded_amount) ? $parent->refunded_amount : 0;
          $parent->refunded_amount += $transaction->amount;
          // Fix the issue:
          // "The provided value "xx.xx" must be a string, not a float.".
          $parent->refunded_amount = (string) $parent->refunded_amount;

          // Remove refund payment transaction.
          unset($payment_transactions[$id]);
        }
      }

      foreach ($payment_transactions as $transaction) {
        yield (array) $transaction;
      }
    }
  }

}
