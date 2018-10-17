<?php

/**
 * @file
 * Diamond Commerce - Odoo export post update file.
 */

/**
 * Update all Daytona Locations parent IDs.
 */
function dcom_odoo_export_post_update_fix_locations_structure_1(&$sandbox) {
  /** @var Drupal\odoo_api\OdooApi\ClientInterface $api */
  $api = \Drupal::service('odoo_api.api_client');
  $items_per_pass = 10;

  if (!isset($sandbox['progress'])) {
    $sandbox['progress'] = 0;
    $sandbox['current'] = 0;

    // Select all terms.
    $filter = [
      ['location_id', '=', 11],
      ['usage', '=', 'internal'],
      ['id', 'not in', [11, 14]],
    ];
    $sandbox['location_ids'] = $api
      ->search('stock.location', $filter);

    $sandbox['count'] = count($sandbox['location_ids']);
  }

  $records = array_slice($sandbox['location_ids'], $sandbox['progress'], $items_per_pass);

  if ($records) {
    $fields = [
      'location_id' => 14,
    ];
    $api->write('stock.location', $records, $fields);

    // Increases progress.
    $sandbox['progress'] += count($records);
    $sandbox['current'] = end($records);
  }

  // Inform the batch engine that we are not finished,
  // and provide an estimation of the completion level we reached.
  if ($sandbox['progress'] != $sandbox['count']) {
    $sandbox['#finished'] = $sandbox['progress'] / $sandbox['count'];
  }
  else {
    $sandbox['#finished'] = 1;
    return t('Migrated locations.');
  }
}
