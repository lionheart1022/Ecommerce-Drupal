<?php

namespace Drupal\dcom_odoo_entity_sync\Util;

use Drupal\dcom_odoo_entity_sync\Plugin\OdooEntitySync\User;
use Drupal\user\UserInterface;

/**
 * Helper trait for users sync.
 */
trait UserSyncTrait {

  use WholesaleSyncTrait;

  /**
   * Checks if the user has at least one order.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user entity.
   *
   * @return bool
   *   Whether the user made at least one order.
   */
  protected function userHasOrders(UserInterface $user) {
    return \Drupal::entityQuery('commerce_order')
      ->condition('uid', $user->id())
      ->condition('cart', FALSE)
      ->condition('state', 'draft', '!=')
      ->count()
      ->execute() > 0;
  }

  /**
   * Checks whether is the user excluded from sync or not.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user entity.
   *
   * @return bool
   *   Whether is the user excluded from sync or not.
   */
  protected function userSyncExcluded(UserInterface $user) {
    return !empty($user->{User::DCOM_ODOO_USER_SYNC_EXCLUDE_FIELD}->value);
  }

}
