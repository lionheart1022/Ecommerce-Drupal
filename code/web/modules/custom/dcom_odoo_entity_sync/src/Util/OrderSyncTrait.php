<?php

namespace Drupal\dcom_odoo_entity_sync\Util;

use Drupal\commerce_order\Entity\OrderInterface;

/**
 * Helper trait for orders sync.
 */
trait OrderSyncTrait {

  use WholesaleSyncTrait;

  /**
   * Returns whether the order can be exported or not.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order entity.
   *
   * @return bool
   *   Whether the order can be exported or not.
   */
  protected function shouldExportOrder(OrderInterface $order) {
    $draft_order = $order->getState()->value == 'draft';
    $cart_order = !empty($order->cart) && $order->cart->value;
    // Skip draft/anonymous/cart orders.
    return !$draft_order && !$cart_order && $order->getCustomerId();
  }

}
