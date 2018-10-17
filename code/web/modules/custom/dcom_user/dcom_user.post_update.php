<?php

/**
 * @file
 * Diamond Commerce - User module post update file.
 */

use Drupal\user\UserInterface;

/**
 * Sets timezone for all users.
 */
function dcom_user_post_update_timezones(&$sandbox) {
  $items_per_pass = 10;

  if (!isset($sandbox['progress'])) {
    $sandbox['progress'] = 0;
    $sandbox['current'] = 0;

    $sandbox['count'] = \Drupal::entityQuery('user')
      ->accessCheck(FALSE)
      ->condition('timezone', 'America/New_York', '<>')
      ->condition('uid', 0, '>')
      ->count()
      ->execute();
  }

  $records = \Drupal::entityQuery('user')
    ->accessCheck(FALSE)
    ->condition('timezone', 'America/New_York', '<>')
    ->condition('uid', $sandbox['current'], '>')
    ->range(0, $items_per_pass)
    ->sort('uid')
    ->execute();

  if ($records) {
    $users = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->loadMultiple($records);

    foreach ($users as $user) {
      $user->set('timezone', 'America/New_York');
      $user->save();

      // Increases progress.
      $sandbox['progress']++;
      $sandbox['current'] = $user->id();
    }
  }

  // Inform the batch engine that we are not finished,
  // and provide an estimation of the completion level we reached.
  if ($sandbox['progress'] != $sandbox['count']) {
    $sandbox['#finished'] = $sandbox['progress'] / $sandbox['count'];
  }
  else {
    $sandbox['#finished'] = 1;
    return t('Set timezone for old users.');
  }
}

/**
 * Merge duplicate user accounts.
 */
function dcom_user_post_update_merge_duplicates(&$sandbox) {
  $items_per_pass = 10;

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

    foreach ($users as $user) {
      /** @var \Drupal\user\Entity\User $user */
      dcom_user_merge_duplicates($user);

      // Increases progress.
      $sandbox['progress']++;
      $sandbox['current'] = $user->id();
    }
  }

  // Inform the batch engine that we are not finished,
  // and provide an estimation of the completion level we reached.
  if ($sandbox['progress'] != $sandbox['count']) {
    $sandbox['#finished'] = $sandbox['progress'] / $sandbox['count'];
  }
  else {
    $sandbox['#finished'] = 1;
    return t('Set timezone for old users.');
  }
}

/**
 * Find duplicates for given user and merge them.
 *
 * @param \Drupal\user\UserInterface $user
 *   User account entity.
 */
function dcom_user_merge_duplicates(UserInterface $user) {
  $uid = $user->id();
  $email = $user->getEmail();

  $uids = \Drupal::entityQuery('user')
    ->condition('uid', $uid, '<>')
    ->condition('mail', $email)
    ->execute();
  if (!$uids) {
    // This user does not have duplicates. Be like this user.
    return;
  }

  $duplicates = \Drupal::entityTypeManager()
    ->getStorage('user')
    ->loadMultiple($uids);
  /** @var \Drupal\user\UserInterface $duplicate_user */
  foreach ($duplicates as $duplicate_user) {
    // Migrate orders.
    $order_ids = \Drupal::entityQuery('commerce_order')
      ->condition('uid', $duplicate_user->id())
      ->execute();
    $orders = \Drupal::entityTypeManager()
      ->getStorage('commerce_order')
      ->loadMultiple($order_ids);
    foreach ($orders as $order) {
      /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
      if (!empty($order->cart->value)) {
        // Drop carts.
        $order->delete();
      }
      else {
        // Change order owner.
        $order->setCustomer($user)->save();
      }
    }

    // Migrate profiles.
    $profile_ids = \Drupal::entityQuery('profile')
      ->condition('uid', $duplicate_user->id())
      ->execute();
    $profiles = \Drupal::entityTypeManager()
      ->getStorage('profile')
      ->loadMultiple($profile_ids);
    foreach ($profiles as $profile) {
      /** @var \Drupal\profile\Entity\ProfileInterface $profile */
      $profile->setOwner($user)->save();
    }

    // Change email of duplicate user and block the account.
    $duplicate_user
      ->setEmail($email . '+duplicate_' . $duplicate_user->id())
      ->block()
      ->save();
  }
}

/**
 * Fix missing users first name / last name.
 */
function dcom_user_post_update_fix_missing_names_even_once_more(&$sandbox) {
  $items_per_pass = 50;

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

    foreach ($users as $user) {
      /** @var \Drupal\user\Entity\User $user */
      dcom_user_fix_name($user);

      // Increases progress.
      $sandbox['progress']++;
      $sandbox['current'] = $user->id();
    }

    // Flush sync queue.
    \Drupal::service('odoo_api_entity_sync.sync')->syncAndFlush();
  }

  // Inform the batch engine that we are not finished,
  // and provide an estimation of the completion level we reached.
  if ($sandbox['progress'] != $sandbox['count']) {
    $sandbox['#finished'] = $sandbox['progress'] / $sandbox['count'];
  }
  else {
    $sandbox['#finished'] = 1;
    return t('Updated usernames.');
  }
}

/**
 * Check user name fields and fix if some value is missing.
 *
 * @param \Drupal\user\UserInterface $user
 *   User entity.
 */
function dcom_user_fix_name(UserInterface $user) {
  if (!empty($user->field_first_name->value)
    && !empty($user->field_last_name->value)) {
    return;
  }

  if ($profile = dcom_user_find_shipping_profile($user)) {
    $address_field = $profile->address->first();
    $user->set('field_first_name', $address_field->getGivenName());
    $user->set('field_last_name', $address_field->getFamilyName());
    $user->save();
  }
}

/**
 * Find user's shipping profile.
 *
 * @param \Drupal\user\UserInterface $user
 *   User entity.
 *
 * @return \Drupal\profile\Entity\ProfileInterface|null
 *   Profile object or NULL.
 */
function dcom_user_find_shipping_profile(UserInterface $user) {
  $order_ids = \Drupal::entityQuery('commerce_order')
    ->accessCheck(FALSE)
    ->condition('uid', $user->id())
    ->condition('cart', FALSE)
    ->condition('state', 'draft', '!=')
    ->execute();
  if ($order_ids) {
    $orders = \Drupal::entityTypeManager()
      ->getStorage('commerce_order')
      ->loadMultiple($order_ids);

    foreach ($orders as $order) {
      /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
      if (!empty($order->shipments->entity->shipping_profile->entity->address)
        && $shipping_address = $order->shipments->entity->shipping_profile->entity->address->first()) {
        return $order->shipments->entity->shipping_profile->entity;
      }
    }
  }

  $profile_ids = \Drupal::entityQuery('profile')
    ->condition('uid', $user->id())
    ->execute();
  $profiles = \Drupal::entityTypeManager()
    ->getStorage('profile')
    ->loadMultiple($profile_ids);

  $profile = reset($profiles);
  return $profile ? $profile : NULL;
}

/**
 * Apply ucfirst to usernames.
 */
function dcom_user_post_update_usernames_ucfirst(&$sandbox) {
  $items_per_pass = 50;

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

    foreach ($users as $user) {
      /** @var \Drupal\user\Entity\User $user */
      $first_name = $user->field_first_name->value;
      $last_name = $user->field_last_name->value;
      if ($first_name != ucfirst(strtolower($first_name)) || $last_name != ucfirst(strtolower($last_name))) {
        $user->save();
      }

      // Increases progress.
      $sandbox['progress']++;
      $sandbox['current'] = $user->id();
    }

    // Flush sync queue.
    \Drupal::service('odoo_api_entity_sync.sync')->syncAndFlush();
  }

  // Inform the batch engine that we are not finished,
  // and provide an estimation of the completion level we reached.
  if ($sandbox['progress'] != $sandbox['count']) {
    $sandbox['#finished'] = $sandbox['progress'] / $sandbox['count'];
  }
  else {
    $sandbox['#finished'] = 1;
    return t('Updated usernames.');
  }
}


/**
 * Remove duplicate users from Odoo.
 */
function dcom_user_post_update_remove_duplicates(&$sandbox) {
  $items_per_pass = 50;

  if (!isset($sandbox['progress'])) {
    $sandbox['progress'] = 0;
    $sandbox['current'] = 0;

    $sandbox['count'] = \Drupal::entityQuery('user')
      ->accessCheck(FALSE)
      ->condition('mail', '+duplicate', 'CONTAINS')
      ->condition('uid', 0, '>')
      ->count()
      ->execute();
  }

  $records = \Drupal::entityQuery('user')
    ->accessCheck(FALSE)
    ->condition('mail', '+duplicate', 'CONTAINS')
    ->condition('uid', $sandbox['current'], '>')
    ->range(0, $items_per_pass)
    ->sort('uid')
    ->execute();

  if ($records) {
    $users = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->loadMultiple($records);

    foreach ($users as $user) {
      /** @var \Drupal\user\Entity\User $user */
      dcom_odoo_entity_sync_handle_entity($user);

      // Increases progress.
      $sandbox['progress']++;
      $sandbox['current'] = $user->id();
    }

    // Flush sync queue.
    \Drupal::service('odoo_api_entity_sync.sync')->syncAndFlush();
  }

  // Inform the batch engine that we are not finished,
  // and provide an estimation of the completion level we reached.
  if ($sandbox['progress'] != $sandbox['count']) {
    $sandbox['#finished'] = $sandbox['progress'] / $sandbox['count'];
  }
  else {
    $sandbox['#finished'] = 1;
    return t('Removed duplicates from Odoo.');
  }
}

/**
 * Triggers an Odoo sync for all wholesale users.
 */
function dcom_user_post_update_sync_wholesale_users(&$sandbox) {
  $items_per_pass = 10;
  $wholesale_roles = ['wholesale_1', 'wholesale_unapproved'];

  /** @var \Drupal\odoo_api_entity_sync\SyncManagerInterface $sync_service */
  $sync_service = \Drupal::service('odoo_api_entity_sync.sync');

  if (!isset($sandbox['progress'])) {
    $sandbox['progress'] = 0;
    $sandbox['current'] = 0;

    $sandbox['count'] = \Drupal::entityQuery('user')
      ->accessCheck(FALSE)
      ->condition('uid', 0, '>')
      ->condition('roles', $wholesale_roles, 'IN')
      ->count()
      ->execute();
  }

  $records = \Drupal::entityQuery('user')
    ->accessCheck(FALSE)
    ->condition('uid', $sandbox['current'], '>')
    ->condition('roles', $wholesale_roles, 'IN')
    ->range(0, $items_per_pass)
    ->sort('uid')
    ->execute();

  if ($records) {
    $users = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->loadMultiple($records);

    /** @var \Drupal\user\Entity\User $user */
    foreach ($users as $user) {
      dcom_odoo_entity_sync_handle_entity($user);

      // Increases progress.
      $sandbox['progress']++;
      $sandbox['current'] = $user->id();
    }

    // Flush sync queue.
    $sync_service->flush();
  }

  // Inform the batch engine that we are not finished,
  // and provide an estimation of the completion level we reached.
  if ($sandbox['progress'] != $sandbox['count']) {
    $sandbox['#finished'] = $sandbox['progress'] / $sandbox['count'];
  }
  else {
    $sandbox['#finished'] = 1;
    return t('Triggered an Odoo sync for all wholesale users');
  }
}
