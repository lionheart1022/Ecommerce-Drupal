<?php

namespace Drupal\dcom_shopify_migrate\Plugin\migrate\source;

/**
 * Trait ShippingOrderItemTrait.
 *
 * @package Drupal\dcom_shopify_migrate\Plugin\migrate\source
 */
trait ShippingOrderItemTrait {

  /**
   * Returns shopify order items, which should be actually shipping_lines.
   *
   * @return array
   *   An array keyed by order item id. Value - order id.
   */
  protected function getShopifyShippingOrderItems() {
    return [
      // Shopify order item id => Shopify order ID.
      8899751056 => 4776413904,
      23333208080 => 12912787472,
      9282823504 => 5005333456,
      9136679312 => 4918064080,
      726679814185 => 334355824681,
      563704660009 => 243534790697,
      9522315600 => 5150195408,
      9518618448 => 5148065104,
      9123666448 => 4909959120,
      8878658512 => 4764203664,
      438857007145 => 177477419049,
    ];
  }

}
