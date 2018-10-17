<?php

namespace Drupal\dcom_odoo_inventory;

use Drupal\commerce_product\Entity\ProductVariationInterface;

/**
 * Interface InventorySyncInterface.
 */
interface InventorySyncInterface {

  /**
   * Query Odoo API for product variant inventory.
   *
   * @param \Drupal\commerce_product\Entity\ProductVariationInterface $entity
   *   Product variation entity.
   *
   * @return float
   *   Odoo quantity on hand.
   */
  public function queryProductVariantInventory(ProductVariationInterface $entity);

  /**
   * Get list of product variants which are no longer available.
   *
   * @return \Drupal\commerce_product\Entity\ProductVariationInterface[]
   *   List of product variants available at Drupal but not available at Odoo.
   */
  public function queryVariantsNotAvailableAnymore();

  /**
   * Get list of product variants which became available.
   *
   * @return \Drupal\commerce_product\Entity\ProductVariationInterface[]
   *   List of product variants not available at Drupal but available at Odoo.
   */
  public function queryVariantsNowAvailable();

}
