<?php

/**
 * @file
 * Update file for Diamond Commerce - Facets module.
 */

/**
 * Creates URL values for collections.
 */
function dcom_facets_post_update_taxonomy_urls(&$sandbox) {
  $items_per_pass = 10;

  if (!isset($sandbox['progress'])) {
    $sandbox['progress'] = 0;
    $sandbox['current'] = 0;

    // Select all terms.
    $sandbox['entity_ids'] = \Drupal::entityQuery('taxonomy_term')
      ->accessCheck(FALSE)
      ->condition('field_is_collection', 1)
      ->execute();

    $sandbox['max'] = count($sandbox['entity_ids']);
  }

  $records = array_slice($sandbox['entity_ids'], $sandbox['progress'], $items_per_pass);

  if ($records) {
    $entities = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadMultiple($records);

    foreach ($entities as $entity) {
      if (!empty($entity->machine_name->value)
        && empty($entity->field_url_value->value)) {
        $entity->field_url_value->value = str_replace('_', '-', $entity->machine_name->value);
        $entity->save();
      }

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
    return t('Created collections URLs.');
  }
}

/**
 * Updates field_url_value in attributes.
 */
function dcom_facets_post_update_attributes_url_value(&$sandbox) {
  $items_per_pass = 10;

  if (!isset($sandbox['progress'])) {
    $sandbox['progress'] = 0;
    $sandbox['current'] = 0;

    // Select all terms.
    $sandbox['entity_ids'] = \Drupal::entityQuery('commerce_product_attribute_value')
      ->condition('attribute', 'strength')
      ->execute();

    $sandbox['max'] = count($sandbox['entity_ids']);
  }

  $records = array_slice($sandbox['entity_ids'], $sandbox['progress'], $items_per_pass);

  if ($records) {
    $entities = \Drupal::entityTypeManager()
      ->getStorage('commerce_product_attribute_value')
      ->loadMultiple($records);

    foreach ($entities as $entity) {
      if (!empty($entity->name->value)
        && empty($entity->field_url_value->value)) {
        $entity->field_url_value->value = str_replace('_', '-', $entity->name->value);
        $entity->save();
      }

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
    return t('Updated field_url_value in attributes.');
  }
}
