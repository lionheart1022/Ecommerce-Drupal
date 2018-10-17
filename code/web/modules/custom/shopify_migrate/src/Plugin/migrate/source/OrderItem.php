<?php

namespace Drupal\shopify_migrate\Plugin\migrate\source;

/**
 * Provides a Shopify Order Item migrate source.
 *
 * Usage example:
 *
 * @code
 * source:
 *   plugin: shopify_order_item
 *   shopify:
 *     shop_domain: mydomain.myshopify.com
 *     api_key: API_KEY
 *     password: PASSWORD
 *     shared_secret: SHARED_SECRET
 * @endcode
 *
 * @MigrateSource(
 *  id = "shopify_order_item"
 * )
 */
class OrderItem extends Base {

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

        // Add extra order data; may be useful during the import.
        $line_item_array['order_id'] = $order['id'];
        $line_item_array['order_name'] = $order['name'];
        $line_item_array['customer_id'] = $order['customer']->id;

        // Add order timestamps.
        $line_item_array['order_created_at'] = $order['created_at'];
        $line_item_array['order_updated_at'] = $order['updated_at'];

        // Add order currency.
        $line_item_array['order_currency'] = $order['currency'];

        yield $line_item_array;
      }
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
