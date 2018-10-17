<?php

/**
 * Creates value for field_domain for taxonomy terms (Vocabularies:
 * product_category, diamond_commerce_brand, product_type,
 * product_characteristics).
 */
function dcom_sitemap_post_update_1(&$sandbox) {
  $items_per_pass = 10;

  if (!isset($sandbox['progress'])) {
    $sandbox['progress'] = 0;
    $sandbox['current'] = 0;

    $sandbox['max'] = \Drupal::entityQuery('taxonomy_term')
      ->accessCheck(FALSE)
      ->condition('vid', [
        'product_category',
        'diamond_commerce_brand',
        'product_type',
        'product_characteristics',
      ], 'IN')
      ->notExists('field_domain')
      ->count()
      ->execute();
  }

  $records = \Drupal::entityQuery('taxonomy_term')
    ->accessCheck(FALSE)
    ->condition('vid', [
      'product_category',
      'diamond_commerce_brand',
      'product_type',
      'product_characteristics',
    ], 'IN')
    ->notExists('field_domain')
    ->condition('tid', $sandbox['current'], '>')
    ->range(0, $items_per_pass)
    ->sort('tid')
    ->execute();

  if ($records) {
    $entities = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadMultiple($records);

    foreach ($entities as $entity) {
      $entity->set('field_domain', 'diamondcbd_domain');
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
    return t('Created a value for field_domain for taxonomy terms.');
  }
}
