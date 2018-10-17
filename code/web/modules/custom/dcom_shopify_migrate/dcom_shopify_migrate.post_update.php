<?php

/**
 * @file
 * Diamond Commerce - Shopify Migrate module post update file.
 */

/**
 * Adds the prefix "SHW" for all orders imported from shopify wholesale.
 */
function dcom_shopify_migrate_post_update_shopify_wholesale_orders2(&$sandbox) {
  if (!isset($sandbox['progress'])) {
    $shopify_wholesale_orders = \Drupal::database()
      ->select('migrate_map_diamondcbdwholesale_orders')
      ->fields('migrate_map_diamondcbdwholesale_orders', ['destid1']);
    $shopify_wholesale_orders->isNotNull('destid1');

    $sandbox['wholesale_order_ids'] = $shopify_wholesale_orders
      ->execute()
      ->fetchAllKeyed(0, 0);
    $sandbox['progress'] = 0;
    $sandbox['count'] = count($sandbox['wholesale_order_ids']);
  }

  $items_per_pass = 10;
  $records = array_splice($sandbox['wholesale_order_ids'], 0, $items_per_pass);
  if ($records) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface[] $orders */
    $orders = \Drupal::entityTypeManager()
      ->getStorage('commerce_order')
      ->loadMultiple($records);

    foreach ($orders as $order) {
      $order_number = $order->getOrderNumber();
      $prefix = substr($order_number, 0, 3);

      if ($prefix != 'SHW') {
        $order->setOrderNumber('SHW' . $order_number);
        $order->save();
      }

      // Increases progress.
      $sandbox['progress']++;
    }

    /** @var \Drupal\odoo_api_entity_sync\SyncManagerInterface $syncer */
    $syncer = \Drupal::service('odoo_api_entity_sync.sync');
    $syncer->syncAndFlush();
  }

  // Inform the batch engine that we are not finished,
  // and provide an estimation of the completion level we reached.
  if ($records && $sandbox['progress'] != $sandbox['count']) {
    $sandbox['#finished'] = $sandbox['progress'] / $sandbox['count'];
  }
  else {
    $sandbox['#finished'] = 1;
    return t('Added the prefix "SHW" for all orders imported from shopify wholesale.');
  }
}
