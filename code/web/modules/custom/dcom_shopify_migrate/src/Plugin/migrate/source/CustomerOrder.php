<?php

namespace Drupal\dcom_shopify_migrate\Plugin\migrate\source;

use Drupal\shopify_migrate\Plugin\migrate\source\Base;

/**
 * Provides a Shopify Order migrate source with the ability to map addresses.
 *
 * Usage example:
 *
 * @code
 * source:
 *   plugin: dcom_shopify_customer_order
 *   shopify:
 *     shop_domain: mydomain.myshopify.com
 *     api_key: API_KEY
 *     password: PASSWORD
 *     shared_secret: SHARED_SECRET
 * @endcode
 *
 * @MigrateSource(
 *  id = "dcom_shopify_customer_order"
 * )
 */
class CustomerOrder extends Base {

  use OrderTrait;

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
  protected function initializeIterator() {
    foreach (parent::initializeIterator() as $order) {
      // Billing addresses, shipping_address and customer should be IDs.
      $order['billing_address'] = !empty($order['billing_address']) ? 'billing_address_' . $order['id'] : NULL;
      $order['shipping_address'] = !empty($order['shipping_address']) ? 'shipping_address_' . $order['id'] : NULL;
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
      'status' => 'any',
    ];
  }

}
