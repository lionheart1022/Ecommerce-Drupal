<?php

/**
 * @file
 * Post update functions for Diamond Commerce - Odoo Entity Sync.
 */

use Drupal\odoo_api_entity_sync\MappingManagerInterface;

/**
 * Migrate mapping for domain entities.
 */
function dcom_odoo_entity_sync_post_update_migrate_domains_mapping(&$sandbox) {
  /** @var \Drupal\domain\DomainInterface[] $domains */
  $domains = \Drupal::entityTypeManager()
    ->getStorage('domain')
    ->loadMultiple();
  /** @var \Drupal\odoo_api_entity_sync\MappingManagerInterface $id_map */
  $id_map = \Drupal::service('odoo_api_entity_sync.mapping');

  foreach ($domains as $domain) {
    $odoo_id = $domain->getThirdPartySetting('dcom_odoo_export', 'odoo_sync_Id');
    if ($odoo_id) {
      $map[$domain->id()] = $odoo_id;
      $domain->unsetThirdPartySetting('dcom_odoo_export', 'odoo_sync_Id');
      $domain->save();
    }
  }

  if (isset($map)) {
    $id_map->setSyncStatus('domain', 'x_product.domain', 'default', $map, MappingManagerInterface::STATUS_SYNCED);
  }

  return t('Migrate mapping for domain entities.');
}

/**
 * Migrate users/companies mapping to Odoo Entity Sync.
 */
function dcom_odoo_entity_sync_post_update_user_mapping(&$sandbox) {
  $items_per_pass = 100;
  /** @var \Drupal\odoo_api_entity_sync\MappingManagerInterface $mapper */
  $mapper = \Drupal::service('odoo_api_entity_sync.mapping');

  if (!isset($sandbox['progress'])) {
    $sandbox['progress'] = 0;
    $sandbox['current'] = 0;

    $sandbox['count'] = \Drupal::entityQuery('user')
      ->accessCheck(FALSE)
      ->condition('uid', 0, '>')
      ->count()
      ->execute();
  }

  $records = \Drupal::entityQuery('user')
    ->accessCheck(FALSE)
    ->condition('uid', $sandbox['current'], '>')
    ->range(0, $items_per_pass)
    ->sort('uid')
    ->execute();

  if ($records) {
    $users = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->loadMultiple($records);

    $users_map = [];
    $companies_map = [];

    foreach ($users as $user) {
      /** @var \Drupal\user\Entity\User $user */

      if (!empty($user->field_odoo_sync_user_id->value)) {
        $users_map[$user->id()] = $user->field_odoo_sync_user_id->value;
      }

      if (!empty($user->field_odoo_sync_company_id->value)) {
        $companies_map[$user->id()] = $user->field_odoo_sync_company_id->value;
      }

      // Increases progress.
      $sandbox['progress']++;
      $sandbox['current'] = $user->id();
    }

    if ($users_map) {
      $mapper->setSyncStatus('user', 'res.partner', 'default', $users_map, MappingManagerInterface::STATUS_SYNCED);
    }
    if ($companies_map) {
      $mapper->setSyncStatus('user', 'res.partner', 'company', $companies_map, MappingManagerInterface::STATUS_SYNCED);
    }
  }

  // Inform the batch engine that we are not finished,
  // and provide an estimation of the completion level we reached.
  if ($sandbox['progress'] != $sandbox['count']) {
    $sandbox['#finished'] = $sandbox['progress'] / $sandbox['count'];
  }
  else {
    $sandbox['#finished'] = 1;
    return t('Migrated users mapping.');
  }
}

/**
 * Migrate orders to Odoo Entity Sync.
 */
function dcom_odoo_entity_sync_post_update_order_mapping(&$sandbox) {
  $items_per_pass = 100;
  /** @var \Drupal\odoo_api_entity_sync\MappingManagerInterface $mapper */
  $mapper = \Drupal::service('odoo_api_entity_sync.mapping');

  if (!isset($sandbox['progress'])) {
    $sandbox['progress'] = 0;
    $sandbox['current'] = 0;

    $sandbox['count'] = \Drupal::entityQuery('commerce_order')
      ->accessCheck(FALSE)
      ->condition('cart', FALSE)
      ->condition('state', 'draft', '!=')
      ->condition('order_id', 0, '>')
      ->count()
      ->execute();
  }

  $records = \Drupal::entityQuery('commerce_order')
    ->accessCheck(FALSE)
    ->condition('cart', FALSE)
    ->condition('state', 'draft', '!=')
    ->condition('order_id', $sandbox['current'], '>')
    ->range(0, $items_per_pass)
    ->sort('order_id')
    ->execute();

  if ($records) {
    $orders = \Drupal::entityTypeManager()
      ->getStorage('commerce_order')
      ->loadMultiple($records);

    $orders_map = [];
    $missing_orders_map = [];
    $discounts_map = [];
    $order_items_map = [];
    $missing_order_items_map = [];

    foreach ($orders as $order) {
      /** @var \Drupal\commerce_order\Entity\OrderInterface $order */

      if (!empty($order->field_odoo_sync_id->value)) {
        $orders_map[$order->id()] = $order->field_odoo_sync_id->value;
      }
      else {
        $missing_orders_map[$order->id()] = NULL;
      }
      if (!empty($order->field_odoo_discount_item_id->value)) {
        $discounts_map[$order->id()] = $order->field_odoo_discount_item_id->value;
      }

      foreach ($order->getItems() as $order_item) {
        /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */

        if (!empty($order_item->field_odoo_sync_id->value)) {
          $order_items_map[$order_item->id()] = $order_item->field_odoo_sync_id->value;
        }
        else {
          $missing_order_items_map[$order_item->id()] = NULL;
        }
      }

      // Increases progress.
      $sandbox['progress']++;
      $sandbox['current'] = $order->id();
    }

    if ($orders_map) {
      $mapper->setSyncStatus('commerce_order', 'sale.order', 'default', $orders_map, MappingManagerInterface::STATUS_SYNCED);
    }
    if ($missing_orders_map) {
      $mapper->setSyncStatus('commerce_order', 'sale.order', 'default', $missing_orders_map, MappingManagerInterface::STATUS_NOT_SYNCED);
    }
    if ($discounts_map) {
      $mapper->setSyncStatus('commerce_order', 'sale.order.line', 'discount_line', $discounts_map, MappingManagerInterface::STATUS_SYNCED);
    }
    if ($order_items_map) {
      $mapper->setSyncStatus('commerce_order_item', 'sale.order.line', 'default', $order_items_map, MappingManagerInterface::STATUS_SYNCED);
    }
    if ($missing_order_items_map) {
      $mapper->setSyncStatus('commerce_order_item', 'sale.order.line', 'default', $missing_order_items_map, MappingManagerInterface::STATUS_NOT_SYNCED);
    }
  }

  // Inform the batch engine that we are not finished,
  // and provide an estimation of the completion level we reached.
  if ($sandbox['progress'] != $sandbox['count']) {
    $sandbox['#finished'] = $sandbox['progress'] / $sandbox['count'];
  }
  else {
    $sandbox['#finished'] = 1;
    return t('Migrated orders mapping.');
  }
}

/**
 * Migrate profiles to Odoo Entity Sync.
 */
function dcom_odoo_entity_sync_post_update_profile_mapping(&$sandbox) {
  $items_per_pass = 100;
  /** @var \Drupal\odoo_api_entity_sync\MappingManagerInterface $mapper */
  $mapper = \Drupal::service('odoo_api_entity_sync.mapping');

  if (!isset($sandbox['progress'])) {
    $sandbox['progress'] = 0;
    $sandbox['current'] = 0;

    $sandbox['count'] = \Drupal::entityQuery('profile')
      ->accessCheck(FALSE)
      ->condition('type', 'customer')
      ->condition('profile_id', 0, '>')
      ->count()
      ->execute();
  }

  $records = \Drupal::entityQuery('profile')
    ->accessCheck(FALSE)
    ->condition('type', 'customer')
    ->condition('profile_id', $sandbox['current'], '>')
    ->range(0, $items_per_pass)
    ->sort('profile_id')
    ->execute();

  if ($records) {
    $profiles = \Drupal::entityTypeManager()
      ->getStorage('profile')
      ->loadMultiple($records);

    $profiles_map = [];
    $missing_profiles_map = [];
    $excluded_profiles_map = [];

    foreach ($profiles as $profile) {
      /** @var \Drupal\profile\Entity\ProfileInterface $profile */

      if (!empty($profile->field_odoo_sync_id->value)) {
        $profiles_map[$profile->id()] = $profile->field_odoo_sync_id->value;
      }
      else {
        $has_orders =
          \Drupal::entityTypeManager()
            ->getStorage('commerce_order')
            ->getQuery()
            ->condition('uid', $profile->getOwnerId())
            ->condition('cart', FALSE)
            ->condition('state', 'draft', '!=')
            ->count()
            ->execute() > 0;
        if ($has_orders) {
          $missing_profiles_map[$profile->id()] = NULL;
        }
        else {
          $excluded_profiles_map[$profile->id()] = NULL;
        }
      }

      // Increases progress.
      $sandbox['progress']++;
      $sandbox['current'] = $profile->id();
    }

    if ($profiles_map) {
      $mapper->setSyncStatus('profile', 'res.partner', 'default', $profiles_map, MappingManagerInterface::STATUS_SYNCED);
    }
    if ($missing_profiles_map) {
      $mapper->setSyncStatus('profile', 'res.partner', 'default', $missing_profiles_map, MappingManagerInterface::STATUS_NOT_SYNCED);
    }
    if ($excluded_profiles_map) {
      $mapper->setSyncStatus('profile', 'res.partner', 'default', $excluded_profiles_map, MappingManagerInterface::STATUS_SYNC_EXCLUDED);
    }
  }

  // Inform the batch engine that we are not finished,
  // and provide an estimation of the completion level we reached.
  if ($sandbox['progress'] != $sandbox['count']) {
    $sandbox['#finished'] = $sandbox['progress'] / $sandbox['count'];
  }
  else {
    $sandbox['#finished'] = 1;
    return t('Migrated profiles mapping.');
  }
}

/**
 * Export user roles to Odoo.
 */
function dcom_odoo_entity_sync_post_update_user_roles(&$sandbox) {
  $entity_query = \Drupal::entityQuery('user')
    ->accessCheck(FALSE)
    ->condition('uid', 0, '>')
    ->exists('roles');

  if (!isset($sandbox['progress'])) {
    $sandbox['progress'] = 0;
    $sandbox['current'] = 0;
    $count = clone $entity_query;
    $sandbox['count'] = $count->count()->execute();
  }

  $items_per_pass = 20;
  $records = $entity_query
    ->condition('uid', $sandbox['current'], '>')
    ->range(0, $items_per_pass)
    ->sort('uid')
    ->execute();

  if ($records) {
    /** @var \Drupal\odoo_api_entity_sync\SyncManagerInterface $syncer */
    $syncer = \Drupal::service('odoo_api_entity_sync.sync');
    $users = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->loadMultiple($records);

    foreach ($users as $user) {
      $syncer->delayedSync('user', 'res.partner', 'default', $user->id());
      // Increases progress.
      $sandbox['progress']++;
      $sandbox['current'] = $user->id();
    }

    $syncer->syncAndFlush();
  }

  // Inform the batch engine that we are not finished,
  // and provide an estimation of the completion level we reached.
  if ($sandbox['progress'] != $sandbox['count']) {
    $sandbox['#finished'] = $sandbox['progress'] / $sandbox['count'];
  }
  else {
    $sandbox['#finished'] = 1;
    return t('Migrated user roles.');
  }
}
