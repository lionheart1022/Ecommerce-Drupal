<?php

namespace Drupal\dcom_odoo_entity_sync;

/**
 * Interface TaxResolverInterface.
 */
interface TaxResolverInterface {

  /**
   * Finds Odoo tax IDs.
   *
   * @param int $tax_percentage
   *   The tax percentage number.
   *
   * @return array
   *   An array of IDs or empty array if no taxes found.
   */
  public function findOdooTaxIdsByPercentage($tax_percentage);

  /**
   * Creates the Tax on Odoo.
   *
   * @param int $tax_percentage
   *   The tax percentage.
   *
   * @return int
   *   The Odoo ID.
   */
  public function createOdooTax($tax_percentage);

}
