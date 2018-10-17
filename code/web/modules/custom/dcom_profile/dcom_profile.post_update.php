<?php

/**
 * @file
 * Diamond Commerce - Profile module post update file.
 */

/**
 * Deactivate duplicated profiles.
 */
function dcom_profile_post_update_deactivate_duplicated_profiles(&$sandbox) {
  $items_per_pass = 10;
  $query = \Drupal::database()->select('profile');
  $query->fields('profile', ['profile_id'])
    ->condition('profile.uid', 0, '>')
    ->condition('profile.type', 'customer', '=')
    ->condition('profile.status', 1, '=');
  $query->leftJoin('commerce_order', NULL, 'commerce_order.billing_profile__target_id=profile.profile_id');
  $query->leftJoin('commerce_payment_method', NULL, 'commerce_payment_method.billing_profile__target_id=profile.profile_id');
  $query->leftJoin('commerce_shipment', NULL, 'commerce_shipment.shipping_profile__target_id=profile.profile_id');

  $query->isNull('commerce_order.order_id')
    ->isNull('commerce_payment_method.method_id')
    ->isNull('commerce_shipment.shipment_id');

  if (!isset($sandbox['progress'])) {
    $sandbox['progress'] = 0;
    $sandbox['current'] = 0;
    $sandbox['processed'] = 0;
    $count = clone $query;
    $sandbox['count'] = $count->countQuery()->execute()->fetchField();
  }

  $query->condition('profile_id', $sandbox['current'], '>');
  $records = $query
    ->range(0, $items_per_pass)
    ->orderBy('profile.profile_id', 'ASC')
    ->execute()
    ->fetchAllKeyed(0, 0);

  if ($records) {
    /** @var \Drupal\profile\Entity\ProfileInterface[] $profiles */
    $profiles = \Drupal::entityTypeManager()
      ->getStorage('profile')
      ->loadMultiple($records);
    /** @var \Drupal\dcom_profile\Util\ProfileComparatorInterface $profile_comparator */
    $profile_comparator = \Drupal::service('dcom_profile.profile_comparator');

    foreach ($profiles as $profile) {
      // Skip profiles if we can't compare a phone number and address.
      $phone = $profile->get('field_phone_number');
      $address = $profile->get('address');

      if (!$phone->isEmpty() && !$address->isEmpty()) {
        // The current profile has duplicates and has no references from orders
        // / shipments / payment methods.
        if ($profile_comparator->findProfileByProfile($profile)) {
          $profile->setActive(FALSE);
          $profile->save();
          $sandbox['processed']++;
        }
      }

      $sandbox['progress']++;
      $sandbox['current'] = $profile->id();
    }

    /** @var \Drupal\odoo_api_entity_sync\SyncManagerInterface $syncer */
    $syncer = \Drupal::service('odoo_api_entity_sync.sync');
    // Run sync.
    $syncer->syncAndFlush();
  }

  // Inform the batch engine that we are not finished,
  // and provide an estimation of the completion level we reached.
  if ($sandbox['progress'] != $sandbox['count']) {
    $sandbox['#finished'] = $sandbox['progress'] / $sandbox['count'];
  }
  else {
    $sandbox['#finished'] = 1;
    return t('Deactivated @number duplicated user profiles.',
    [
      '@number' => $sandbox['processed'],
    ]);
  }
}
