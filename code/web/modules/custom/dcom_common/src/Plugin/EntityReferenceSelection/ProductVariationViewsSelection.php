<?php

namespace Drupal\dcom_common\Plugin\EntityReferenceSelection;

use Drupal\views\Plugin\EntityReferenceSelection\ViewsSelection;

/**
 * Enhance the entity reference selection with additional details.
 *
 * @EntityReferenceSelection(
 *   id = "views_product_variation",
 *   label = @Translation("Product variation (Descriptive)"),
 *   group = "views_product_variation",
 *   weight = 10,
 *   deriver = "Drupal\Core\Entity\Plugin\Derivative\DefaultSelectionDeriver"
 * )
 */
class ProductVariationViewsSelection extends ViewsSelection {

  /**
   * {@inheritdoc}
   */
  public function getReferenceableEntities($match = NULL, $match_operator = 'CONTAINS', $limit = 0) {
    $options = parent::getReferenceableEntities($match, $match_operator, $limit);
    $target_type = $this->getConfiguration()['target_type'];

    if ($options && $target_type == 'commerce_product_variation') {
      $entity_ids = [];
      foreach ($options as $bundle => $entities) {
        $entity_ids = array_merge($entity_ids, array_keys($entities));
      }

      // Should load from cache.
      /** @var \Drupal\commerce_product\Entity\ProductVariationInterface[] $entities */
      $entities = $this->entityManager->getStorage($target_type)->loadMultiple($entity_ids);
      foreach ($entities as $entity_id => $entity) {
        $bundle = $entity->bundle();
        $suffix = ' [' . $entity->getSku() . ']';
        $options[$bundle][$entity_id] = $options[$bundle][$entity_id] . $suffix;
      }
    }

    return $options;
  }

}
