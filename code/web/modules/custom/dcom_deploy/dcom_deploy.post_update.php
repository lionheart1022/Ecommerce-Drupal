<?php

/**
 * @file
 * Update file for Diamond Commerce - Deploy module.
 */

/**
 * Generate product variation titles based on attribute values.
 */
function dcom_deploy_post_update_product_variations_title(&$sandbox) {
  $product_variations_storage = \Drupal::entityTypeManager()->getStorage('commerce_product_variation');
  $entity_query = \Drupal::entityQuery('commerce_product_variation')
    ->accessCheck(FALSE);

  if (!isset($sandbox['progress'])) {
    $count = clone $entity_query;
    $sandbox['progress'] = 0;
    $sandbox['current'] = 0;
    $sandbox['count'] = $count->count()->execute();
  }

  // Process the next 10 product variants.
  $limit = 10;
  $ids = $entity_query
    ->condition('variation_id', $sandbox['current'], '>')
    ->sort('variation_id', 'ASC')
    ->range(0, $limit)
    ->execute();

  /** @var \Drupal\commerce_product\Entity\ProductVariationInterface $product_variation */
  foreach ($product_variations_storage->loadMultiple($ids) as $id => $product_variation) {
    // Resave variations to trigger title generation.
    $product_variation->save();
    $sandbox['progress']++;
    $sandbox['current'] = $id;
  }

  // Inform the batch engine that we are not finished,
  // and provide an estimation of the completion level we reached.
  if ($sandbox['progress'] != $sandbox['count']) {
    $sandbox['#finished'] = $sandbox['progress'] / $sandbox['count'];
  }
  else {
    $sandbox['#finished'] = 1;
    return t('Generate product variation titles based on attribute values.');
  }
}
