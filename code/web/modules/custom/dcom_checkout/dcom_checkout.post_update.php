<?php

/**
 * @file
 * Update file for Diamond Commerce - Checkout module.
 */

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\user\Entity\User;

/**
 * Register users who made anonymous orders.
 */
function dcom_checkout_post_update_register_users(&$sandbox) {
  $items_per_pass = 10;

  if (!isset($sandbox['progress'])) {
    $sandbox['progress'] = 0;
    $sandbox['current'] = 0;

    // Select all anonymous orders.
    $sandbox['entity_ids'] = \Drupal::entityQuery('commerce_order')
      ->accessCheck(FALSE)
      ->condition('cart', FALSE)
      ->condition('state', 'draft', '!=')
      ->condition('uid', 0)
      ->execute();

    $sandbox['count'] = count($sandbox['entity_ids']);
  }

  $order_ids = array_slice($sandbox['entity_ids'], $sandbox['progress'], $items_per_pass);

  if ($order_ids) {
    $entities = \Drupal::entityTypeManager()
      ->getStorage('commerce_order')
      ->loadMultiple($order_ids);

    foreach ($entities as $entity) {
      dcom_checkout_register_anonymous_order_user($entity);

      // Increases progress.
      $sandbox['progress']++;
      $sandbox['current'] = $entity->id();
    }
  }

  // Inform the batch engine that we are not finished,
  // and provide an estimation of the completion level we reached.
  if ($sandbox['progress'] != $sandbox['count']) {
    $sandbox['#finished'] = $sandbox['progress'] / $sandbox['count'];
  }
  else {
    $sandbox['#finished'] = 1;
    return t('Created collections URLs.');
  }
}

/**
 * Create a new user account for anonymous order.
 *
 * @param \Drupal\commerce_order\Entity\OrderInterface $order
 *   Order entity.
 */
function dcom_checkout_register_anonymous_order_user(OrderInterface $order) {
  if (!($email = $order->getEmail())) {
    \Drupal::logger('dcom_checkout')->log('error', 'Missing email for order @id.', ['@id' => $order->id()]);
    return;
  }
  $language = \Drupal::languageManager()->getCurrentLanguage()->getId();

  $new_user = User::create();
  $new_user->enforceIsNew();
  $new_user->setUsername('email_registration_' . user_password());
  $new_user->setEmail($email);
  $new_user->set('langcode', $language);
  $new_user->set('preferred_langcode', $language);
  $new_user->activate();

  $billing_profile = $order->getBillingProfile();

  if (!empty($order->shipments->entity->shipping_profile->entity->address)
    && $shipping_address = $order->shipments->entity->shipping_profile->entity->address->first()) {
    // Get user's name from shipping profile.
    /** @var \Drupal\address\Plugin\Field\FieldType\AddressItem $shipping_address */
    $new_user->set('field_first_name', $shipping_address->getGivenName());
    $new_user->set('field_last_name', $shipping_address->getFamilyName());
  }
  elseif (!empty($billing_profile->address)
    && $billing_address = $billing_profile->address->first()) {
    // Get user's name from billing profile.
    /** @var \Drupal\address\Plugin\Field\FieldType\AddressItem $billing_address */
    $new_user->set('field_first_name', $billing_address->getGivenName());
    $new_user->set('field_last_name', $billing_address->getFamilyName());
  }

  // Save new user.
  // Now, once we have user, update referenced entities.
  $new_user->save();

  // Change shipping profile owner.
  if (!empty($order->shipments->entity->shipping_profile->entity)) {
    /** @var \Drupal\profile\Entity\ProfileInterface $shipping_profile */
    $shipping_profile = $order->shipments->entity->shipping_profile->entity;
    $shipping_profile->setOwnerId($new_user->id());
    $shipping_profile->save();
  }

  // Change billing profile owner.
  if (!empty($billing_profile)) {
    $billing_profile->setOwnerId($new_user->id());
    $billing_profile->save();
  }

  $order->setCustomer($new_user);
  $order->save();
}
