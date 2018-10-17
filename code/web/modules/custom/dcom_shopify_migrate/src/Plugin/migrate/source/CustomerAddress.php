<?php

namespace Drupal\dcom_shopify_migrate\Plugin\migrate\source;

/**
 * Provides a Shopify Customer Address migrate source.
 *
 * Usage example:
 *
 * @code
 * source:
 *   plugin: dcom_shopify_customer_address
 * @endcode
 *
 * @MigrateSource(
 *  id = "dcom_shopify_customer_address"
 * )
 */
class CustomerAddress extends Base {

  use CustomerTrait;

  /**
   * Increase API fetch limit.
   *
   * {@inheritdoc}
   */
  protected $pagerLimit = 200;

  /**
   * Addresses aren't countable since they are properties of customers.
   *
   * {@inheritdoc}
   */
  protected $skipCount = TRUE;

  /**
   * {@inheritdoc}
   */
  protected function getShopifyResource() {
    return 'orders';
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'id' => [
        'type' => 'string',
        'max_length' => 256,
        'is_ascii' => TRUE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function initializeIterator() {
    $fields = ['billing_address', 'shipping_address'];
    foreach (parent::initializeIterator() as $order) {
      foreach ($fields as $field) {
        if (!empty($order[$field])) {
          $address_array = (array) $order[$field];
          $address_array['id'] = $field . '_' . $order['id'];
          $address_array['customer_id'] = !empty($order['customer']->id) ? $order['customer']->id : NULL;
          yield $address_array;
        }
      }
    }
  }

}
