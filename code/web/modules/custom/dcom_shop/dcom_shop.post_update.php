<?php

/**
 * Creates value for field_visibility_option (cbd_product).
 */
function dcom_shop_post_update_cbd_product_visibility(&$sandbox) {
  $items_per_pass = 10;

  if (!isset($sandbox['progress'])) {
    $sandbox['progress'] = 0;
    $sandbox['current'] = 0;

    // Select all work sessions.
    $sandbox['entity_ids'] = \Drupal::entityQuery('commerce_product')
      ->accessCheck(FALSE)
      ->condition('type', 'cbd_product')
      ->notExists('field_visibility_option')
      ->execute();

    $sandbox['max'] = count($sandbox['entity_ids']);
  }

  $records = array_slice($sandbox['entity_ids'], $sandbox['progress'], $items_per_pass);

  if ($records) {
    $entities = \Drupal::entityTypeManager()
      ->getStorage('commerce_product')
      ->loadMultiple($records);

    foreach ($entities as $entity) {
      $entity->set('field_visibility_option', 'everywhere');
      $entity->save();

      // Increases progress.
      $sandbox['progress']++;
      $sandbox['current'] = $entity->id();
    }
  }

  // Inform the batch engine that we are not finished,
  // and provide an estimation of the completion level we reached.
  if ($sandbox['progress'] != $sandbox['max']) {
    $sandbox['#finished'] = $sandbox['progress'] / $sandbox['max'];
  }
  else {
    $sandbox['#finished'] = 1;
    return t('Created a value for field_visibility_option.');
  }
}
