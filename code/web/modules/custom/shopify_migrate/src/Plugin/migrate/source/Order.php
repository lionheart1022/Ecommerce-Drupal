<?php

namespace Drupal\shopify_migrate\Plugin\migrate\source;

/**
 * Provides a Shopify Order migrate source.
 *
 * Usage example:
 *
 * @code
 * source:
 *   plugin: shopify_order
 *   shopify:
 *     shop_domain: mydomain.myshopify.com
 *     api_key: API_KEY
 *     password: PASSWORD
 *     shared_secret: SHARED_SECRET
 * @endcode
 *
 * @MigrateSource(
 *  id = "shopify_order"
 * )
 */
class Order extends Base {

  /**
   * Increase API fetch limit.
   *
   * {@inheritdoc}
   */
  protected $pagerLimit = 200;

  /**
   * {@inheritdoc}
   */
  protected function getShopifyResource() {
    return 'orders';
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'id' => $this->t('Order ID'),
      'created_at' => $this->t('Creation date'),
      'processed_at' => $this->t('The date and time (ISO 8601) when an order is said to be created'),
      'updated_at' => $this->t('Update date'),
      'billing_address' => $this->t('Billing address ID'),
      'shipping_address' => $this->t('Shipping address ID'),
      'customer' => $this->t('Customer ID'),
      'line_items' => $this->t('Line items IDs'),
      // @TODO: Add more fields.
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function initializeIterator() {
    foreach (parent::initializeIterator() as $order) {
      // Billing addresses, shipping_address and customer should be IDs.
      // @TODO: Billing and shipping addresses does not have IDs :(.
      $order['billing_address'] = isset($order['billing_address']->id) ? $order['billing_address']->id : NULL;
      $order['shipping_address'] = isset($order['shipping_address']->id) ? $order['shipping_address']->id : NULL;
      $order['customer'] = $order['customer']->id ?: NULL;

      // Fill in line items IDs.
      $line_items = [];
      if (!empty($order['line_items'])) {
        foreach ($order['line_items'] as $line_item) {
          $line_items[] = $line_item->id;
        }
      }
      $order['line_items'] = $line_items;
      yield $order;
    }
  }

  /**
   * Fetch all orders, including archived, closed etc.
   *
   * {@inheritdoc}
   */
  protected function getQueryOptions() {
    return [
      // @TODO: Add configuration option.
      'status' => 'any',
    ];
  }

}
