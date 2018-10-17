<?php

namespace Drupal\dcom_back_in_stock;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access controller for the StockNotification entity.
 */
class StockNotificationAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    switch ($operation) {
      case 'view':
        return AccessResult::allowedIfHasPermission($account, 'view stock_notification entity');

      case 'edit':
        return AccessResult::allowedIfHasPermission($account, 'edit stock_notification entity');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete stock_notification entity');
    }
    return AccessResult::allowed();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add stock_notification entity');
  }

}
