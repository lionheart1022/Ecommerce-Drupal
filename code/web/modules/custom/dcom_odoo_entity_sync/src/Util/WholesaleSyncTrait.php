<?php

namespace Drupal\dcom_odoo_entity_sync\Util;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\user\UserInterface;

/**
 * Helper trait for wholesale sync.
 */
trait WholesaleSyncTrait {

  /**
   * Checks if given user is a wholesales.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user entity.
   *
   * @return bool
   *   Whether user is wholesale.
   */
  protected function userIsWholesale(UserInterface $user) {
    // @TODO: Implement multiple wholesale roles.
    return $user->hasRole('wholesale_1')
      || $user->hasRole('wholesale_unapproved');
  }

  /**
   * Checks if given order is a wholesale.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order entity.
   *
   * @return bool
   *   Whether order is wholesale.
   *
   * @TODO: Implement a better check. We're currently checking for a promotion
   * @TODO: ID == 3 which is '50% off for wholesales'.
   */
  protected function orderIsWholesale(OrderInterface $order) {
    if ($order->hasField('field_order_source') && !$order->get('field_order_source')->isEmpty()) {
      $order_source = $order->get('field_order_source')->first()->getValue()['value'];

      if ($order_source == 'shopify_wholesale') {
        return TRUE;
      }
    };

    foreach ($order->getItems() as $item) {
      foreach ($item->getAdjustments() as $adjustment) {
        if ($adjustment->getSourceId() == 3) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

}
