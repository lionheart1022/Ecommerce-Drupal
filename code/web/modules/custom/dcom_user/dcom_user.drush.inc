<?php

/**
 * @file
 * Dcom_user module Drush commands file.
 */

/**
 * Implements hook_drush_command().
 */
function dcom_user_drush_command() {
  $items = array();

  $items['dcom-user-fix-profile-names'] = [
    'description' => 'Reformat user names for all profiles.',
    'examples' => [
      'drush dcom-user-fix-profile-names',
    ],
  ];

  return $items;
}

/**
 * Drush command callback for dcom-user-fix-profile-names.
 */
function drush_dcom_user_fix_profile_names() {
  $operations = [
    ['dcom_user_fix_profiles_process', []],
  ];

  batch_set(['operations' => $operations]);
  drush_backend_batch_process();
}

/**
 * Batch process callback for dcom-user-fix-profile-names.
 */
function dcom_user_fix_profiles_process(&$context) {
  $orders_storage = \Drupal::entityTypeManager()->getStorage('profile');

  /** @var \Drupal\odoo_api_entity_sync\SyncManagerInterface $syncer */
  $syncer = \Drupal::service('odoo_api_entity_sync.sync');

  if (empty($context['sandbox'])) {
    $context['sandbox']['progress'] = 0;
    $context['sandbox']['current'] = 0;
    $context['sandbox']['max'] = \Drupal::entityQuery('profile')
      ->accessCheck(FALSE)
      ->count()
      ->execute();
  }

  $limit = 200;
  $ids = \Drupal::entityQuery('profile')
    ->accessCheck(FALSE)
    ->condition('profile_id', $context['sandbox']['current'], '>')
    ->sort('profile_id', 'ASC')
    ->range(0, $limit)
    ->execute();

  /** @var \Drupal\profile\Entity\ProfileInterface[] $profiles */
  $profiles = $orders_storage->loadMultiple($ids);

  foreach ($profiles as $id => $profile) {
    if (!empty($profile) && $address = $profile->address->first()) {
      /** @var \Drupal\address\Plugin\Field\FieldType\AddressItem $address */
      $first_name = $address->given_name;
      $last_name = $address->family_name;
      if (ucfirst(strtolower($first_name)) != $first_name
      || ucfirst(strtolower($last_name)) != $last_name) {
        $profile->save();
      }
    }

    $context['sandbox']['progress']++;
    $context['sandbox']['current'] = $id;
  }

  // Run sync.
  $syncer->syncAndFlush();

  if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
    $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
    drush_log('Fixed ' . $context['sandbox']['progress'] . ' of ' . $context['sandbox']['max'] . ' (' . number_format($context['finished'] * 100, 2) . '%).');
  }

}
