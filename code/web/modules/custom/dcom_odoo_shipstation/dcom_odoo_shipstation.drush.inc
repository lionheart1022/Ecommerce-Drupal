<?php

/**
 * @file
 * Diamond Commerce - Odoo Shipstation module Drush commands file.
 */

use dacardworld\ShipStation\ShipStationApi;
use Drupal\Component\Serialization\Json;
use fXmlRpc\Exception\FaultException;

/**
 * Implements hook_drush_command().
 */
function dcom_odoo_shipstation_drush_command() {
  $items = array();

  $items['dcom-odoo-shipstation-sync-fulfilled'] = array(
    'description' => 'Fulfill orders in Odoo.',
    'examples' => array(
      'drush dcom-odoo-shipstation-sync-fulfilled',
    ),
  );

  return $items;
}

/**
 * Drush command callback for dcom-odoo-shipstation-sync-fulfilled.
 */
function drush_dcom_odoo_shipstation_sync_fulfilled() {
  $operations = [
    ['dcom_odoo_shipstation_sync_fulfilled_process', []],
  ];

  batch_set(['operations' => $operations]);
  drush_backend_batch_process();
}

/**
 * Batch process callback for dcom-odoo-entity-sync-export-orders command.
 */
function dcom_odoo_shipstation_sync_fulfilled_process(&$context) {
  // @TODO: Do not hardcode credentials.
  $store_id = 353253;
  $apiKey = 'c436527950774644908e9ceec633c26f';
  $apiSecret = '3540e7062a6146d294f3b0500e95b75e';
  $options = [];
  $shipstation = new ShipStationApi($apiKey, $apiSecret, $options);

  if (empty($context['sandbox'])) {
    // Initiate multistep processing.
    $context['sandbox']['progress'] = 0;
    $context['sandbox']['offset'] = 0;
    $context['sandbox']['errors'] = [];
  }

  // Process the next 50 orders.
  $limit = 50;

  $orders = Json::decode($shipstation->getOrdersService()->getListing([
    'storeId' => $store_id,
    'page' => intval(floor($context['sandbox']['progress'] / $limit) + 1),
    'pageSize' => $limit,
  ])->getBody());

  if (!isset($context['sandbox']['total'])) {
    $context['sandbox']['total'] = $orders['total'];
  }

  $shipstation_orders_by_id = [];
  foreach ($orders['orders'] as $order) {
    // Only process shipped orders.
    if ($order['orderStatus'] == 'shipped') {
      $shipstation_orders_by_id[$order['orderKey']] = $order;
    }
    $context['sandbox']['progress']++;
  }

  try {
    $errors = \Drupal::service('dcom_odoo_shipstation.transfer_processor')
      ->fulfillOdooOrders(array_keys($shipstation_orders_by_id));
  }
  catch (FaultException $e) {
    drush_log('Odoo exception, skipping the batch step.');
  }

  $context['sandbox']['errors'] += $errors;

  if ($context['sandbox']['progress'] != $context['sandbox']['total']) {
    $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['total'];
    drush_log('Fulfilled ' . $context['sandbox']['progress'] . ' of ' . $context['sandbox']['total'] . ' (' . number_format($context['finished'] * 100, 2) . '%), ' . count($context['sandbox']['errors']) . ' errors so far.');
  }

  if ($context['finished'] >= 1) {
    $table = [['Order ID', 'Error message']];
    foreach ($context['sandbox']['errors'] as $id => $error) {
      $table[] = [$id, $error];
    }
    drush_print_table($table, TRUE);
  }
}
