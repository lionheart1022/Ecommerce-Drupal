<?php

namespace Drupal\dcom_shopify_migrate\Plugin\migrate\source;

/**
 * Provides a Shopify Order migrate source.
 *
 * Usage example:
 *
 * @code
 * source:
 *   plugin: dcom_shopify_order
 * @endcode
 *
 * @MigrateSource(
 *  id = "dcom_shopify_order"
 * )
 */
class Order extends Base {

  use ShippingOrderItemTrait;
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
      $order['billing_address'] = !empty($order['billing_address']) ? 'billing_address_' . $order['id'] : NULL;
      $order['shipping_address'] = !empty($order['shipping_address']) ? 'shipping_address_' . $order['id'] : NULL;
      $order['customer'] = isset($order['customer']->id) ? $order['customer']->id : NULL;

      // Fill in line items IDs.
      $line_items = [];
      if (!empty($order['line_items'])) {
        foreach ($order['line_items'] as $line_item) {
          if (!empty($this->getShopifyShippingOrderItems()[$line_item->id])) {
            // Do not import order items, which should be actually shipping lines.
            $shopify_basic_shipping_line = [
              'title' => $line_item->title,
              'discounted_price' => $line_item->price,
            ];
            $order['shipping_lines'][] = (object) $shopify_basic_shipping_line;
            continue;
          }
          $line_items[] = $line_item->id;
        }
      }
      $order['line_items'] = $line_items;
      yield $order;
    }
  }

}
