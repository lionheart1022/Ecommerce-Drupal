<?php

/**
 * @file
 * Update file for Diamond Commerce - Common module.
 */

/**
 * Updates order of product variations based on the attributes.
 */
function dcom_common_post_update_product_variations_order(&$sandbox) {
  /** @var \Drupal\dcom_common\Util\ProductVariationsSorterInterface $sorter */
  $sorter = \Drupal::service('dcom_common.product_variation_subscriber');
  $products_storage = \Drupal::entityTypeManager()->getStorage('commerce_product');
  $entity_query = \Drupal::entityQuery('commerce_product')
    ->accessCheck(FALSE);

  if (!isset($sandbox['progress'])) {
    $count = clone $entity_query;
    $sandbox['progress'] = 0;
    $sandbox['current'] = 0;
    $sandbox['count'] = $count->count()->execute();
  }

  $limit = 10;
  $ids = $entity_query
    ->condition('product_id', $sandbox['current'], '>')
    ->sort('product_id', 'ASC')
    ->range(0, $limit)
    ->execute();

  /** @var \Drupal\commerce_product\Entity\ProductInterface $product */
  foreach ($products_storage->loadMultiple($ids) as $id => $product) {
    $variations = $product->getVariations();
    $sorted = FALSE;

    foreach ($sorter->sortByAttributes($variations) as $key => $variation) {
      if ($variation->id() != $variations[$key]->id()) {
        $sorted = TRUE;
        break;
      }
    }

    if ($sorted) {
      // Resave product to trigger product variations sort.
      $product->save();
    }
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
    return t('Resorted product variations.');
  }
}
