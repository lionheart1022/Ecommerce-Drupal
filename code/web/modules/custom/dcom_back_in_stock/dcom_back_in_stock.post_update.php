<?php

/**
 * @file
 * Back in Stock module post-update file.
 */

/**
 * Set default field value for products stock policy.
 */
function dcom_back_in_stock_post_update_products_policy(&$sandbox) {
  dcom_back_in_stock_default_policy_update_batch($sandbox, 'commerce_product', 'product_id');
}

/**
 * Set default field value for product variations stock policy.
 */
function dcom_back_in_stock_post_update_product_variations_policy(&$sandbox) {
  dcom_back_in_stock_default_policy_update_batch($sandbox, 'commerce_product_variation', 'variation_id');
}

/**
 * Helper function for updating default inventory policy.
 */
function dcom_back_in_stock_default_policy_update_batch(&$sandbox, $entity_type, $id_field) {
  $items_per_pass = 20;

  if (!isset($sandbox['progress'])) {
    $sandbox['progress'] = 0;
    $sandbox['current'] = 0;

    $sandbox['count'] = \Drupal::entityQuery($entity_type)
      ->accessCheck(FALSE)
      ->count()
      ->execute();
  }

  $ids = \Drupal::entityQuery($entity_type)
    ->accessCheck(FALSE)
    ->condition($id_field, $sandbox['current'], '>')
    ->range(0, $items_per_pass)
    ->sort($id_field)
    ->execute();

  if ($ids) {
    $entities = \Drupal::entityTypeManager()
      ->getStorage($entity_type)
      ->loadMultiple($ids);

    foreach ($entities as $entity) {
      $entity->set('field_force_availability', 'GLOBAL');
      $entity->save();

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
    return t('Updated default inventory policy for entity type @entity_type.', ['@entity_type' => $entity_type]);
  }
}
