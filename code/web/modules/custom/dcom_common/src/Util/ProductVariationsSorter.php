<?php

namespace Drupal\dcom_common\Util;

use Drupal\commerce_product\ProductAttributeFieldManagerInterface;

/**
 * The ProductVariationsSorter service.
 *
 * @package Drupal\dcom_common\Util
 */
class ProductVariationsSorter implements ProductVariationsSorterInterface {

  /**
   * The product attribute field manager.
   *
   * @var \Drupal\commerce_product\ProductAttributeFieldManagerInterface
   */
  protected $attributeFieldManager;

  /**
   * ProductVariationsSorter constructor.
   *
   * @param \Drupal\commerce_product\ProductAttributeFieldManagerInterface $attribute_field_manager
   *   The product attribute field manager.
   */
  public function __construct(ProductAttributeFieldManagerInterface $attribute_field_manager) {
    $this->attributeFieldManager = $attribute_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function sortByAttributes(array $variations) {
    /** @var \Drupal\commerce_product\Entity\ProductVariationInterface[] $variations */
    foreach ($variations as $key => $variation) {
      $field_map = $this->attributeFieldManager->getFieldMap($variation->bundle());

      foreach ($field_map as $attribute) {
        $attribute_value = $variation->getAttributeValue($attribute['field_name']);
        if ($attribute_value) {
          // If this is Natural Flavor (id 83) - save it.
          if ($attribute_value->getAttributeId() == 'flavor' && $attribute_value->id() == 83) {
            $natural_flavor_variations[] = $variation;
            // Do not sort natural flavor.
            unset($variations[$key]);
          }
          else {
            $attributes[$attribute['attribute_id']][$variation->id()] = $attribute_value->label();
          }
        }
      }
    }

    if (!empty($attributes)) {
      if (!empty($attributes['flavor'])) {
        // Ignore case.
        $attributes['flavor'] = array_map('strtolower', $attributes['flavor']);
      }

      // To support multiple attributes we need to call array_multisort with
      // call_user_func_array. But it doesn't sort the array for some reason.
      // I didn't bother.
      $sort_attributes = reset($attributes);
      $attribute_id = key($attributes);
      $sort_flag = $attribute_id == 'strength' ? SORT_NUMERIC : SORT_REGULAR;
      array_multisort($sort_attributes, SORT_ASC, $sort_flag, $variations);

      if (!empty($natural_flavor_variations)) {
        // Put Natural flavors at the top.
        $variations = array_merge($natural_flavor_variations, $variations);
      }
    }
    return $variations;
  }

}
