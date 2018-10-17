<?php

/**
 * @file
 * Diamond Commerce - Odoo Entity Sync module Drush commands file.
 */

use Drupal\Component\Render\FormattableMarkup;
use Drupal\odoo_api_entity_sync\MappingManagerInterface;
use fXmlRpc\Exception\FaultException;
use League\Csv\Writer;

/**
 * Implements hook_drush_command().
 */
function dcom_odoo_entity_sync_drush_command() {
  $items = [];

  $items['dcom-odoo-entity-sync-export-orders'] = [
    'description' => 'Re-export all orders to Odoo.',
    'options' => [
      'state' => 'A comma-separated states of orders to export',
      'order-source' => 'A comma-separated order sources',
      'odoo-sync-status' => 'A comma-separated Odoo API sync statuses of orders to export',
      'limit' => 'The number of orders to export',
      'offset' => 'The number of orders to skip',
    ],
    'examples' => [
      'drush dcom-odoo-entity-sync-export-orders',
      'drush dcom-odoo-entity-sync-export-orders --state=completed --order-source=odoo,shopify_retail,shopify_wholesale --limit=10 --odoo-sync-status=3',
    ],
  ];

  $items['dcom-odoo-entity-sync-export-invoices'] = [
    'description' => 'Re-export all invoices to Odoo.',
    'options' => [
      'single-order-invoice' => 'Boolean. If there is already an invoice for the order - skip export for this item.',
    ],
    'examples' => [
      'drush dcom-odoo-entity-sync-export-invoices',
      'drush dcom-odoo-entity-sync-export-invoices --single-order-invoice=1',
    ],
  ];

  $items['dcom-odoo-entity-sync-export-entities'] = [
    'description' => 'Re-export all entities of given type to Odoo.',
    'examples' => [
      'drush dcom-odoo-entity-sync-export-entities profile',
      'drush dcom-odoo-entity-sync-export-entities profile --skip=1000',
    ],
    'arguments' => [
      'entity_type' => 'Exported entity type',
    ],
    'options' => [
      'skip' => [
        'description' => 'Number of entities to skip',
        'example-value' => 1000,
      ],
    ],
    'required-arguments' => 1,
  ];


  $items['dcom-odoo-entity-sync-delete-untracked-contacts'] = [
    'description' => 'Delete Odoo contacts which aren\'t tracked by Drupal.',
    'examples' => [
      'drush dcom-odoo-entity-sync-delete-untracked-contacts',
    ],
  ];

  $items['dcom-odoo-entity-sync-fix-invoices'] = [
    'description' => 'Fix broken/incorrect invoices in Odoo.',
    'examples' => [
      'drush dcom-odoo-entity-sync-fix-invoices',
      'drush dcom-odoo-entity-sync-fix-invoices --skip=1000',
    ],
    'options' => [
      'skip' => [
        'description' => 'Number of entities to skip',
        'example-value' => 1000,
      ],
    ],
  ];

  return $items;
}

/**
 * Drush command callback for dcom-odoo-entity-sync-export-orders.
 */
function drush_dcom_odoo_entity_sync_export_orders() {
  $conditions = $odoo_sync_statuses = [];

  if ($states = drush_get_option('state')) {
    $states = explode(',', $states);
    $conditions[] = [
      'field' => 'state',
      'value' => $states,
      'operator' => 'in',
    ];
  }
  if ($order_sources = drush_get_option('order-source')) {
    $order_sources = explode(',', $order_sources);
    $conditions[] = [
      'field' => 'field_order_source',
      'value' => $order_sources,
      'operator' => 'in',
    ];
  }
  if ($odoo_sync_statuses = drush_get_option('odoo-sync-status')) {
    $odoo_sync_statuses = explode(',', $odoo_sync_statuses);
  }

  $args = [
    $conditions,
    $odoo_sync_statuses,
    drush_get_option('limit', NULL),
    drush_get_option('offset', NULL),
  ];
  $operations = [
    ['dcom_odoo_entity_sync_orders_export_process', $args],
  ];

  batch_set(['operations' => $operations]);
  drush_backend_batch_process();
}

/**
 * Drush command callback for dcom-odoo-entity-sync-export-entities.
 */
function drush_dcom_odoo_entity_sync_export_entities($entity_type) {
  $operations = [
    [
      'dcom_odoo_entity_sync_export_entities_process', [
        $entity_type, drush_get_option('skip', 0),
      ],
    ],
  ];

  batch_set(['operations' => $operations]);
  drush_backend_batch_process();
}

/**
 * Drush command callback for dcom-odoo-entity-sync-delete-untracked-contacts.
 */
function drush_dcom_odoo_entity_sync_delete_untracked_contacts() {
  $operations = [
    ['dcom_odoo_entity_sync_delete_untracked_process', []],
  ];

  batch_set(['operations' => $operations]);
  drush_backend_batch_process();
}

/**
 * Drush command callback for dcom-odoo-entity-sync-fix-invoices.
 */
function drush_dcom_odoo_entity_sync_fix_invoices() {
  $operations = [
    [
      'dcom_odoo_entity_sync_fix_invoices_process', [drush_get_option('skip', 0)],
    ],
  ];

  batch_set(['operations' => $operations]);
  drush_backend_batch_process();
}

/**
 * Implements callback_batch_operation().
 *
 * Batch process callback for dcom-odoo-entity-sync-export-orders command.
 * Processes orders export.
 *
 * @param array $conditions
 *   The conditions array.
 * @param array $odoo_sync_statuses
 *   Export orders with specific Odoo sync statuses.
 * @param int|null $limit
 *   The number of items to process. NULL - to fetch all items.
 * @param int|null $offset
 *   The number of items to skip. NULL - do not skip items.
 * @param array|\ArrayAccess $context
 *   The batch context.
 *
 * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
 * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
 */
function dcom_odoo_entity_sync_orders_export_process(array $conditions, array $odoo_sync_statuses, $limit, $offset, &$context) {
  $orders_storage = \Drupal::entityTypeManager()->getStorage('commerce_order');

  /** @var \Drupal\odoo_api_entity_sync\SyncManagerInterface $syncer */
  $syncer = \Drupal::service('odoo_api_entity_sync.sync');
  $entity_query = \Drupal::entityQuery('commerce_order')
    ->condition('cart', FALSE)
    ->condition('state', 'draft', '!=');

  foreach ($conditions as $condition) {
    $entity_query->condition($condition['field'], $condition['value'], $condition['operator']);
  }

  if (empty($context['sandbox'])) {
    // Offset orders only on the first iteration. On next iterations we will
    // filter it by $context['sandbox']['current_order'].
    $offset = $offset ?: 0;
    // Initiate multistep processing.
    $context['sandbox']['progress'] = 0;
    $context['sandbox']['current_order'] = 0;
    $context['sandbox']['errors'] = [];
    $count = clone $entity_query;
    $context['sandbox']['max'] = $count->count()->execute();

    if ($limit && $context['sandbox']['max'] > $limit) {
      $context['sandbox']['max'] = $limit;
    }
  }
  else {
    $offset = 0;
  }

  // Process the next 50 orders.
  $items_per_pass = 50;
  $items_per_pass = ($limit && $items_per_pass > $limit) ? $limit : $items_per_pass;
  $ids = $entity_query
    ->condition('order_id', $context['sandbox']['current_order'], '>')
    ->sort('order_id', 'ASC')
    ->range($offset, $items_per_pass)
    ->execute();

  if ($ids && $odoo_sync_statuses) {
    // Filter by Odoo sync status.
    /** @var \Drupal\odoo_api_entity_sync\MappingManagerInterface $mapping_manager */
    $mapping_manager = \Drupal::service('odoo_api_entity_sync.mapping');
    $sync_statuses = $mapping_manager->getSyncStatus('commerce_order', 'sale.order', 'default', $ids);
    $sync_statuses = array_filter($sync_statuses, function ($sync_status) use ($odoo_sync_statuses) {
      return in_array($sync_status['status'], $odoo_sync_statuses);
    });

    // If an order doesn't has a needed Odoo status - assume it is processed.
    $skipped = (count($ids) - count($sync_statuses));
    $context['sandbox']['progress'] += $skipped;
    // Prevent going circles if there is no orders to export in this iteration:
    // Update current order id.
    $iteration_last_order_id = end($ids);
    $ids = array_keys($sync_statuses);

    if (!$ids) {
      $context['sandbox']['current_order'] = $iteration_last_order_id;
    }
  }

  /** @var \Drupal\commerce_order\Entity\OrderInterface[] $orders */
  $orders = $orders_storage->loadMultiple($ids);

  foreach ($orders as $id => $order) {
    // Sync order and all order items.
    if (!empty($order)) {
      dcom_odoo_entity_sync_handle_entity($order);
      foreach ($order->getItems() as $item) {
        dcom_odoo_entity_sync_handle_entity($item);
      }
    }

    $context['sandbox']['progress']++;
    $context['sandbox']['current_order'] = $id;
  }

  if ($orders) {
    // Run sync.
    $syncer->syncAndFlush();

    // Verify everything's good.
    dcom_odoo_entity_sync_verify_order_sync($orders, $context);
  }

  if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
    $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
    drush_log('Exported ' . $context['sandbox']['progress'] . ' of ' . $context['sandbox']['max'] . ' (' . number_format($context['finished'] * 100, 2) . '%).');
  }

  if ($context['finished'] >= 1) {
    $table = [];
    foreach ($context['sandbox']['errors'] as $id => $error) {
      $table[] = [$id, $error];
    }
    drush_print_table($table);
  }
}

/**
 * Verify synced orders.
 *
 * @param \Drupal\commerce_order\Entity\OrderInterface[] $orders
 *   Array of orders being synced.
 * @param mixed $context
 *   Batch context.
 */
function dcom_odoo_entity_sync_verify_order_sync(array $orders, &$context) {
  $odoo_ids = dcom_odoo_entity_sync_get_odoo_ids(array_keys($orders));
  $odoo_orders = dcom_odoo_entity_sync_fetch_odoo_orders($odoo_ids);

  foreach ($orders as $order) {
    $order_id = $order->id();
    if (empty($odoo_ids[$order_id])) {
      $context['sandbox']['errors'][$order_id] = 'Missing Odoo ID.';
      continue;
    }
    $odoo_id = $odoo_ids[$order_id];
    if (!isset($odoo_orders[$odoo_id])) {
      $context['sandbox']['errors'][$order_id] = 'No such order in Odoo.';
      continue;
    }

    if ($odoo_orders[$odoo_id]['amount_total'] != $order->getTotalPrice()->getNumber()) {
      $arguments = [
        '@drupal' => $order->getTotalPrice()->getNumber(),
        '@odoo' => $odoo_orders[$odoo_id]['amount_total'],
      ];
      $context['sandbox']['errors'][$order_id] = (string) new FormattableMarkup('Order total price mismatch. Drupal order total: @drupal. Odoo order total: @odoo.', $arguments);
      continue;
    }
  }
}

/**
 * Fetch orders data from Odoo.
 *
 * @param array $odoo_ids
 *   Odoo IDs.
 *
 * @return array
 *   Array of Odoo orders, keyed by Odoo order ID.
 */
function dcom_odoo_entity_sync_fetch_odoo_orders(array $odoo_ids) {
  /** @var \Drupal\odoo_api\OdooApi\ClientInterface $api */
  $api = \Drupal::service('odoo_api.api_client');

  $odoo_orders = [];
  if ($odoo_ids) {
    foreach ($api->read('sale.order', array_values($odoo_ids), ['id', 'amount_total']) as $odoo_order) {
      $odoo_orders[$odoo_order['id']] = $odoo_order;
    }
  }

  return $odoo_orders;
}

/**
 * Fetch Odoo IDs of given orders.
 *
 * @param array $order_ids
 *   Drupal order IDs.
 *
 * @return array
 *   Array of Odoo IDs for given orders.
 */
function dcom_odoo_entity_sync_get_odoo_ids(array $order_ids) {
  /** @var \Drupal\odoo_api_entity_sync\MappingManagerInterface $mapping */
  $mapping = \Drupal::service('odoo_api_entity_sync.mapping');

  return $mapping->getIdMap('commerce_order', 'sale.order', 'default', $order_ids);
}

/**
 * Batch callback for dcom-odoo-entity-sync-export-entities command.
 */
function dcom_odoo_entity_sync_export_entities_process($entity_type, $skip, &$context) {
  $storage = \Drupal::entityTypeManager()->getStorage($entity_type);
  $entity_type_def = \Drupal::entityTypeManager()->getDefinition($entity_type);
  $entity_id_field = $entity_type_def->getKey('id');

  /** @var \Drupal\odoo_api_entity_sync\SyncManagerInterface $syncer */
  $syncer = \Drupal::service('odoo_api_entity_sync.sync');

  if (empty($context['sandbox'])) {
    // Initiate multistep processing.
    $context['sandbox']['progress'] = 0;
    $context['sandbox']['current_id'] = 0;
    $context['sandbox']['max'] = \Drupal::entityQuery($entity_type)
      ->accessCheck(FALSE)
      ->count()
      ->execute();

    if (!empty($skip)) {
      $skip = intval($skip);
      $context['sandbox']['progress'] = $skip;
      if ($skip > $context['sandbox']['max']) {
        throw new \Exception('You tried skipping ' . $skip . ' of maximum ' . $context['sandbox']['max'] . ' entities');
      }
      $query = \Drupal::entityQuery($entity_type)
        ->accessCheck(FALSE)
        ->range($skip, 1);
      $result = $query->execute();
      if (empty($result)) {
        throw new \Exception('Error skipping entities.');
      }
      $context['sandbox']['current_id'] = reset($result);
    }
  }

  // Process the next 50 entities.
  $limit = 50;
  $ids = \Drupal::entityQuery($entity_type)
    ->accessCheck(FALSE)
    ->condition($entity_id_field, $context['sandbox']['current_id'], '>')
    ->sort($entity_id_field, 'ASC')
    ->range(0, $limit)
    ->execute();

  if ($ids) {
    $entities = $storage->loadMultiple($ids);
    foreach ($entities as $id => $entity) {
      // Sync order and all order items.
      if (!empty($entity)) {
        dcom_odoo_entity_sync_handle_entity($entity);
      }

      $context['sandbox']['progress']++;
      $context['sandbox']['current_id'] = $id;
    }

    // Run sync.
    $syncer->syncAndFlush();
  }
  else {
    $context['sandbox']['progress'] = $context['sandbox']['max'];
  }

  if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
    $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
  }
  else {
    $context['finished'] = 1;
  }

  drush_log('Exported ' . $context['sandbox']['progress'] . ' of ' . $context['sandbox']['max'] . ' (' . number_format($context['finished'] * 100, 2) . '%).');

}

/**
 * Batch callback for dcom-odoo-entity-sync-delete-untracked-contacts command.
 */
function dcom_odoo_entity_sync_delete_untracked_process(&$context) {
  $items_per_pass = 1000;
  /** @var \Drupal\odoo_api_entity_sync\MappingManagerInterface $mapper */
  $mapper = \Drupal::service('odoo_api_entity_sync.mapping');

  /** @var \Drupal\odoo_api\OdooApi\ClientInterface $api */
  $api = \Drupal::service('odoo_api.api_client');

  $model = 'res.partner';
  $filter = [
    ['comment', '=', 'Imported from Drupal'],
  ];

  if (!isset($context['sandbox']['progress'])) {
    $context['sandbox']['progress'] = 0;
    $context['sandbox']['current'] = 0;
    $context['sandbox']['count'] = $api->count($model, $filter);
    $context['sandbox']['errors'] = [];
    $context['sandbox']['removed'] = [];
  }

  $search_filter = $filter;
  $search_filter[] = ['id', '>', $context['sandbox']['current']];

  // Find next 100 contacts.
  $ids = $api->search($model, $search_filter, 0, $items_per_pass, 'id asc');

  if ($ids) {
    $map = $mapper->findMappedEntities($model, $ids);

    foreach ($map as $odoo_id => $drupal_entity) {
      if (empty($drupal_entity)) {
        // Delete Odoo object.
        try {
          $api->unlink($model, [$odoo_id]);
          $context['sandbox']['removed'][$odoo_id] = TRUE;
        }
        catch (FaultException $e) {
          $context['sandbox']['errors'][$odoo_id] = $e->getMessage();
        }
      }

      // Increases progress.
      $context['sandbox']['progress']++;
      $context['sandbox']['current'] = $odoo_id;
    }
  }

  if ($context['sandbox']['progress'] != $context['sandbox']['count']) {
    $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['count'];
    drush_log('Processed ' . $context['sandbox']['progress'] . ' of ' . $context['sandbox']['count'] . ' (' . number_format($context['finished'] * 100, 2) . '%).');
  }

  if ($context['finished'] >= 1) {
    drush_log('Removed ' . count($context['sandbox']['removed']) . ' untracked contacts.');
    $table = [];
    foreach ($context['sandbox']['errors'] as $id => $error) {
      $table[] = [$id, $error];
    }
    drush_print_table($table);
  }
}

/**
 * Drush command callback for dcom-odoo-entity-sync-export-invoices.
 */
function drush_dcom_odoo_entity_sync_export_invoices() {
  $single_order_invoice = (bool) drush_get_option('single-order-invoice');

  $operations = [
    ['dcom_odoo_entity_sync_invoices_export_process', [$single_order_invoice]],
  ];

  batch_set(['operations' => $operations]);
  drush_backend_batch_process();
}

/**
 * Batch process callback for dcom-odoo-entity-sync-export-invoices command.
 */
function dcom_odoo_entity_sync_invoices_export_process($single_order_invoice, &$context) {
  $orders_storage = \Drupal::entityTypeManager()->getStorage('commerce_order');
  /** @var \Drupal\odoo_api_entity_sync\MappingManagerInterface $mapping */
  $mapping = \Drupal::service('odoo_api_entity_sync.mapping');
  /** @var Drupal\odoo_api\OdooApi\ClientInterface $api */
  $api = \Drupal::service('odoo_api.api_client');
  /** @var \Drupal\odoo_api_entity_sync\SyncManagerInterface $syncer */
  $syncer = \Drupal::service('odoo_api_entity_sync.sync');

  $entity_query = \Drupal::entityQuery('commerce_order')
    ->condition('cart', FALSE)
    ->condition('state', ['fulfillment', 'completed'], 'in');

  if (empty($context['sandbox'])) {
    // Initiate multistep processing.
    $context['sandbox']['progress'] = 0;
    $context['sandbox']['already_invoiced_orders'] = 0;
    $context['sandbox']['invoiced_orders'] = 0;
    $context['sandbox']['current_order'] = 0;
    $count = clone $entity_query;
    $context['sandbox']['max'] = $count->count()->execute();
    $context['sandbox']['errors'] = [];
  }

  // Process the next 50 orders.
  $limit = 50;
  $ids = $entity_query
    ->condition('order_id', $context['sandbox']['current_order'], '>')
    ->sort('order_id', 'ASC')
    ->range(0, $limit)
    ->execute();
  $latest_order_id = end($ids);

  if ($ids && $single_order_invoice) {
    // Filter orders invoiced on Drupal.
    $invoiced_orders = $mapping->getIdMap('commerce_order', 'account.invoice', 'default', $ids);

    foreach ($invoiced_orders as $order_id => $odoo_id) {
      if ($odoo_id) {
        unset($ids[$order_id]);
        $context['sandbox']['progress']++;
        $context['sandbox']['already_invoiced_orders']++;
      }
    }

    // Filter orders invoiced on Odoo and save mapping for it.
    if ($ids) {
      $orders_odoo_ids = $mapping->getIdMap('commerce_order', 'sale.order', 'default', $ids);
      $invoiced_orders = $api->searchRead('sale.order', [
        ['invoice_status', '=', 'invoiced'],
        ['id', 'in', array_values(array_filter($orders_odoo_ids))],
      ], ['id', 'invoice_ids']);

      $invoiced = [];
      foreach ($invoiced_orders as $invoiced_order) {
        $order_id = array_search($invoiced_order['id'], $orders_odoo_ids);
        if ($order_id !== FALSE) {
          unset($ids[$order_id]);
          $invoiced[$order_id] = reset($invoiced_order['invoice_ids']);
          $context['sandbox']['progress']++;
          $context['sandbox']['already_invoiced_orders']++;
        }
      }

      // Save mapping for the orders which already has invoices on Odoo.
      if (isset($invoiced)) {
        $mapping->setSyncStatus('commerce_order', 'account.invoice', 'default', $invoiced, MappingManagerInterface::STATUS_SYNCED);
        unset($invoiced);
      }
    }
  }

  if ($ids) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface[] $orders */
    $orders = $orders_storage->loadMultiple($ids);
    foreach ($orders as $id => $order) {
      $syncer->delayedSync('commerce_order', 'account.invoice', 'default', $order->id());
      $context['sandbox']['invoiced_orders']++;
      $context['sandbox']['progress']++;
      $context['sandbox']['current_order'] = $id;
    }
  }
  else {
    $context['sandbox']['current_order'] = $latest_order_id;
  }

  // Run sync.
  $syncer->syncAndFlush();

  if (!empty($orders)) {
    // Verify mapping.
    dcom_odoo_entity_sync_verify_invoices_sync(array_keys($orders), $context);
  }

  $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
  $percentage = number_format($context['finished'] * 100, 2);
  $message = 'Processed ' . $context['sandbox']['progress'] . ' of ' . $context['sandbox']['max'] . ' (' . $percentage . '%). Invoiced orders: ' . $context['sandbox']['invoiced_orders'] . '. Current order: ' . $context['sandbox']['current_order'] . '.';

  if (isset($context['sandbox']['already_invoiced_orders'])) {
    $message .= ' Orders which already has an invoice on Odoo: ' . $context['sandbox']['already_invoiced_orders'] . '.';
  }
  drush_log($message);

  if ($context['finished'] >= 1) {
    $table = [];
    foreach ($context['sandbox']['errors'] as $id => $error) {
      $table[] = [$id, $error];
    }
    drush_print_table($table);
  }
}

/**
 * Verify invoices mapping.
 *
 * @param int[] $order_ids
 *   Array of order ids being synced.
 * @param mixed $context
 *   Batch context.
 */
function dcom_odoo_entity_sync_verify_invoices_sync(array $order_ids, &$context) {
  /** @var \Drupal\odoo_api_entity_sync\MappingManagerInterface $mapping */
  $mapping = \Drupal::service('odoo_api_entity_sync.mapping');
  $orders_mapping = $mapping->getIdMap('commerce_order', 'sale.order', 'default', $order_ids);

  if ($orders_mapping = array_filter($orders_mapping)) {
    $invoices_mapping = $mapping->getIdMap('commerce_order', 'account.invoice', 'default', $order_ids);
    $invoices_mapping = array_filter($invoices_mapping);

    /** @var Drupal\odoo_api\OdooApi\ClientInterface $api */
    $api = \Drupal::service('odoo_api.api_client');
    $odoo_invoices = $api->searchRead('sale.order', [
      ['id', 'in', array_values($orders_mapping)],
    ], ['id', 'invoice_ids']);

    foreach ($odoo_invoices as $odoo_invoice) {
      $drupal_order_id = array_search($odoo_invoice['id'], $orders_mapping);

      if (empty($invoices_mapping[$drupal_order_id])) {
        $context['sandbox']['errors'][$drupal_order_id] = (string) new FormattableMarkup('There is no invoice id for the drupal order: @order_id.', ['@order_id' => $drupal_order_id]);
        continue;
      }

      $drupal_invoice_id = $invoices_mapping[$drupal_order_id];
      if (!in_array($drupal_invoice_id, $odoo_invoice['invoice_ids'])) {
        $arguments = [
          '@order_id' => $drupal_order_id,
          '@drupal_invoice_id' => $drupal_invoice_id,
          '@odoo_order_id' => $odoo_invoice['id'],
        ];
        $context['sandbox']['errors'][$drupal_order_id] = (string) new FormattableMarkup('Drupal invoice id is not found in the Odoo order invoices. Order id: @order_id. Odoo order id: @odoo_order_id. Drupal invoice id: @drupal_invoice_id.', $arguments);
        continue;
      }

      unset($invoices_mapping[$drupal_order_id]);
    }

    if ($invoices_mapping) {
      foreach ($invoices_mapping as $drupal_order_id => $odoo_invoice_id) {
        $arguments = [
          '@drupal_order_id' => $drupal_order_id,
          '@odoo_invoice_id' => $odoo_invoice_id,
        ];
        $context['sandbox']['errors'][$drupal_order_id] = (string) new FormattableMarkup('The invoice @odoo_invoice_id is not created on Odoo. Drupal order id: @drupal_order_id.', $arguments);
      }
    }
  }
}

/**
 * Batch process callback for dcom-odoo-entity-sync-fix-invoices command.
 */
function dcom_odoo_entity_sync_fix_invoices_process($skip, &$context) {
  /** @var \Drupal\dcom_odoo_entity_sync\InvoiceFixerInterface $account_fixer */
  $account_fixer = \Drupal::service('dcom_odoo_entity_sync.invoice_fixer');

  $query = \Drupal::database()->select('odoo_api_entity_sync', 's');
  $query
    ->condition('s.entity_type', 'commerce_order')
    ->condition('s.odoo_model', 'sale.order')
    ->condition('s.export_type', 'default')
    ->condition('s.status', MappingManagerInterface::STATUS_SYNCED);

  $query->orderBy('s.entity_id', 'ASC');

  $dir = 'private://dcom_odoo_entity_sync';
  if (!file_prepare_directory($dir, FILE_MODIFY_PERMISSIONS | FILE_CREATE_DIRECTORY)) {
    throw new \Exception('Error creating ' . $dir);
  }
  $report_file_path = $dir . '/invoices.csv';

  if (empty($context['sandbox'])) {
    // Initiate multistep processing.
    $context['sandbox']['progress'] = 0;
    $context['sandbox']['current_id'] = 0;
    $context['sandbox']['max'] = (clone $query)->countQuery()->execute()->fetchField();

    if (!empty($skip)) {
      $skip = intval($skip);
      $context['sandbox']['progress'] = $skip;
      if ($skip > $context['sandbox']['max']) {
        throw new \Exception('You tried skipping ' . $skip . ' of maximum ' . $context['sandbox']['max'] . ' entities');
      }
      $last_item_query = (clone $query)
        ->fields('s', ['entity_id'])
        ->range($skip, 1);
      $result = $last_item_query->execute()->fetchField();
      if (empty($result)) {
        throw new \Exception('Error skipping entities.');
      }
      $context['sandbox']['current_id'] = $result;
    }

    if (file_exists($report_file_path)) {
      unlink($report_file_path);
    }
    touch($report_file_path);
    $csv = Writer::createFromPath($report_file_path, 'a');
    $header = [
      'Drupal ID',
      'Odoo ID',
      'Result',
      'Exception class',
      'Exception message',
    ];
    $csv->insertOne($header);
  }

  if (!isset($csv)) {
    $csv = Writer::createFromPath($report_file_path, 'a');
  }

  // Process the next 50 entities.
  $limit = 50;
  $ids_query = (clone $query)->fields('s', ['entity_id', 'odoo_id']);
  $ids_query->condition('s.entity_id', $context['sandbox']['current_id'], '>');
  $ids_query->range(0, $limit);
  $ids = $ids_query
    ->execute()
    ->fetchAllKeyed();

  if ($ids) {
    try {
      $account_fixer->preloadOrders(array_map('intval', array_values($ids)));
    }
    catch (FaultException $fault) {
      // Try one more time if loading failed.
      try {
        $account_fixer->preloadOrders(array_map('intval', array_values($ids)));
      }
      catch (FaultException $second_fault) {
        sleep(300);
        $account_fixer->preloadOrders(array_map('intval', array_values($ids)));
      }
    }

    foreach ($ids as $entity_id => $odoo_order_id) {
      try {
        $result = $account_fixer->checkAndFixInvoice($odoo_order_id);
        $result_string = $result ? 'Updated' : 'Skipped';
        $row = [$entity_id, $odoo_order_id, $result_string, '', ''];
      }
      catch (\Throwable $e) {
        $row = [
          $entity_id,
          $odoo_order_id,
          'Error',
          get_class($e),
          $e->getMessage(),
        ];
      }

      drush_log('Processed order ' . $odoo_order_id);
      $csv->insertOne($row);
      $context['sandbox']['current_id'] = $entity_id;
      $context['sandbox']['progress']++;
    }
  }
  else {
    $context['sandbox']['progress'] = $context['sandbox']['max'];
  }

  if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
    $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
  }
  else {
    $context['finished'] = 1;
  }

  drush_log('Fixed ' . $context['sandbox']['progress'] . ' of ' . $context['sandbox']['max'] . ' (' . number_format($context['finished'] * 100, 2) . '%).');
}
