<?php

namespace Drupal\dcom_common\Util;

/**
 * Interface ProductVariationsSorterInterface.
 *
 * @package Drupal\dcom_common\Util
 */
interface ProductVariationsSorterInterface {

  /**
   * Sorts the product variation based on the attributes.
   *
   * @param \Drupal\commerce_product\Entity\ProductVariationInterface[] $variations
   *   The product variations array.
   *
   * @return \Drupal\commerce_product\Entity\ProductVariationInterface[]
   *   The sorted product variations array.
   */
  public function sortByAttributes(array $variations);

}
