<?php

/**
 * @file
 * Diamond Commerce - Shopify Migrate module Drush commands file.
 */

use Drupal\Component\Serialization\Json;
use Shopify\PrivateApp;

/**
 * Implements hook_drush_command().
 */
function dcom_shopify_migrate_drush_command() {
  $items = [];

  $items['dcom-shopify-migrate-download'] = [
    'description' => 'Download Shopify data.',
    'arguments' => [
      'resource' => 'Shopify resource name',
    ],
    'required-arguments' => 1,
    'examples' => [
      'drush dcom-shopify-migrate-download products',
    ],
    'options' => [
      'page' => 'Starting page.',
    ],
  ];

  return $items;
}

/**
 * Download data from Shopify API.
 *
 * This is a callback for dcom-shopify-migrate-download drush command.
 *
 * @param string $resource
 *   Shopify resource name.
 *
 * @see dcom_shopify_migrate_drush_command()
 */
function drush_dcom_shopify_migrate_download($resource) {
  $operations = [
    [
      'drush_dcom_shopify_migrate_download_process',
      [$resource, drush_get_option('page')],
    ],
  ];

  batch_set(['operations' => $operations]);
  drush_backend_batch_process();
}

/**
 * Get Shopify API client.
 *
 * @return \Shopify\PrivateApp
 *   Shopify API client object.
 */
function dcom_shopify_shopify_api_client() {
  $shop_domain = 'diamondcbdwholesale.myshopify.com';
  $api_key = '2cfa640a2566deacbabe0a9a9a288ceb';
  $password = 'c1fbc742b8ea51cc2496bf0e2d53bb50';
  $shared_secret = '6a01dee49b3f4d399e39d755496a3260';

  return new PrivateApp($shop_domain, $api_key, $password, $shared_secret);
}

/**
 * Batch process callback for Shopify API download.
 *
 * @param string $resource
 *   Shopify resource name.
 * @param int $page
 *   Starting page.
 * @param \ArrayAccess $context
 *   Batch context.
 *
 * @throws \Exception
 *   Shopify API error.
 */
function drush_dcom_shopify_migrate_download_process($resource, $page, ArrayAccess &$context) {
  $shopify = dcom_shopify_shopify_api_client();
  $limit = 250;
  $dirname = 'private://shopifywholesale_data/' . $resource;
  /** @var \Drupal\Core\File\FileSystem $filesystem */
  $filesystem = Drupal::service('file_system');

  if (empty($context['sandbox'])) {
    // Initiate multistep processing.
    $context['sandbox']['progress'] = 0;
    $context['sandbox']['page'] = 1;
    $context['sandbox']['max'] = $shopify->getResourceCount($resource);

    if ($page) {
      // Continue downloading.
      $context['sandbox']['page'] = $page;
      $context['sandbox']['progress'] = $limit * ($page - 1);
    }
    else {
      // Create directory and remove all existing files.
      @$filesystem->mkdir($dirname, NULL, TRUE);
      foreach (file_scan_directory($dirname, '/^page_\d+\.json$/') as $file) {
        $filesystem->unlink($file->uri);
      }
    }
  }

  // Process the next $limit entities.
  $opts = [
    'query' => [
      'limit' => $limit,
      'page' => $context['sandbox']['page'],
    ],
  ];
  $response = $shopify->get($resource, $opts);
  if (!isset($response->{$resource})) {
    throw new \Exception('Empty Shopify answer.');
  }
  $objects = $response->{$resource};

  // Save JSON.
  $filename = $dirname . '/page_' . $context['sandbox']['page'] . '.json';
  file_put_contents($filename, Json::encode($objects));

  drush_dcom_shopify_migrate_download_deps($resource, $objects, $shopify);

  // Update batch status.
  $context['sandbox']['progress'] += count($objects);

  if ($context['sandbox']['max'] > 0) {
    $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
    drush_log('Downloaded ' . $context['sandbox']['progress'] . ' of ' . $context['sandbox']['max'] . ' (' . number_format($context['finished'] * 100, 2) . '%), page ' . $context['sandbox']['page'] . '.');
  }
  $context['sandbox']['page']++;
}

/**
 * Download additional data for given resource.
 *
 * @param string $resource
 *   Resource name.
 * @param array $objects
 *   Objects.
 * @param \Shopify\PrivateApp $shopify
 *   Shopify API client.
 */
function drush_dcom_shopify_migrate_download_deps($resource, array $objects, PrivateApp $shopify) {
  /** @var \Drupal\Core\File\FileSystem $filesystem */
  $filesystem = Drupal::service('file_system');

  $dirname = 'private://shopifywholesale_data/' . $resource;
  $deps = [
    'orders' => [
      'transactions',
    ],
  ];

  if (!isset($deps[$resource])) {
    return;
  }

  foreach ($deps[$resource] as $dep_resource) {
    @$filesystem->mkdir($dirname . '/' . $dep_resource, NULL, TRUE);
  }

  foreach ($objects as $object) {
    if (!isset($object->id)) {
      continue;
    }
    $id = $object->id;
    foreach ($deps[$resource] as $dep_resource) {
      $filename = $dirname . '/' . $dep_resource . '/' . $id . '.json';
      if (file_exists($filename)) {
        // No need to re-download.
        continue;
      }
      $response = $shopify->get($resource . '/' . $id . '/' . $dep_resource);

      if (empty($response)
        || (isset($response->{$dep_resource}) && empty($response->{$dep_resource}))) {
        continue;
      }
      file_put_contents($filename, Json::encode($response->{$dep_resource}));
    }
  }
}
