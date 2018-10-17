<?php

namespace Drupal\dcom_odoo_shipstation;

/**
 * Interface ShipstationFormatterInterface.
 */
interface ShipstationFormatterInterface {

  /**
   * Format orders as ShipStation XML.
   *
   * @param array $orders
   *   Array of Odoo orders, as returned by OrdersListInterface::getOrdersList.
   * @param int $count
   *   Total items count.
   * @param int $page_size
   *   Page size, used to count pages.
   *
   * @return string
   *   Shipstation XML response.
   *
   * @see \Drupal\dcom_odoo_shipstation\OrdersListInterface::getOrdersList()
   */
  public function formatFeedResponse(array $orders, $count, $page_size);

}
