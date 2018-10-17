<?php

namespace Drupal\dcom_odoo_shipstation;

/**
 * Odoo transfer processor service interface.
 */
interface TransferProcessorInterface {

  /**
   * Fulfill given orders in Odoo.
   *
   * @param array $odoo_sale_ids
   *   Array of IDs of Odoo sales orders.
   *
   * @return array
   *   List of errors, keyed by order ID.
   */
  public function fulfillOdooOrders(array $odoo_sale_ids);

}
