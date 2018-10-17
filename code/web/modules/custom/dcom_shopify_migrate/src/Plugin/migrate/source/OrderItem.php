<?php

namespace Drupal\dcom_shopify_migrate\Plugin\migrate\source;

/**
 * Provides a Shopify Order Item migrate source.
 *
 * Usage example:
 *
 * @code
 * source:
 *   plugin: dcom_shopify_order_item
 * @endcode
 *
 * @MigrateSource(
 *  id = "dcom_shopify_order_item"
 * )
 */
class OrderItem extends Base {

  use ShippingOrderItemTrait;

  /**
   * Order items aren't countable since they are properties of orders.
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
    return 'orders';
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'id' => $this->t('The line item ID'),
      'order_id' => $this->t('The order ID'),
      'product_id' => $this->t('Product ID'),
      'variant_id' => $this->t('Product variant ID'),
      'order_created_at' => $this->t('Order creation time'),
      'order_updated_at' => $this->t('Order update time'),
      'price' => $this->t('Price'),
      'order_currency' => $this->t('Order currency'),
      // @TODO: Add more fields.
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function initializeIterator() {
    foreach (parent::initializeIterator() as $order) {
      if (empty($order['line_items'])) {
        continue;
      }
      foreach ($order['line_items'] as $line_item) {
        // Extract line items from orders.
        $line_item_array = (array) $line_item;

        if (!empty($this->getShopifyShippingOrderItems()[$line_item->id])) {
          // Do not import order items, which should be actually shipping lines.
          continue;
        }

        $line_item_array['customer'] = isset($order['customer']->id) ? $order['customer']->id : NULL;

        // Add extra order ID item; may be useful during the import.
        $line_item_array['order_id'] = $order['id'];

        // Add order timestamps.
        $line_item_array['order_created_at'] = $order['created_at'];
        $line_item_array['order_updated_at'] = $order['updated_at'];

        // Add order currency.
        $line_item_array['order_currency'] = $order['currency'];

        yield $line_item_array;
      }
    }
  }

}
