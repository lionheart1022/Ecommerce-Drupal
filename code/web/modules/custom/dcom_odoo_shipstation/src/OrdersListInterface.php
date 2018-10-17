<?php

namespace Drupal\dcom_odoo_shipstation;

/**
 * Interface OrdersListInterface.
 */
interface OrdersListInterface {

  /**
   * Get latest Odoo orders.
   *
   * @param int $offset
   *   Query offset.
   * @param int $limit
   *   Query limit.
   * @param int|null $start_date
   *   Only get orders changed since given timestamp. NULL to omit filter.
   * @param int|null $end_date
   *   Only get orders changed before given timestamp. NULL to omit filter.
   *
   * @return array
   *   Array of Odoo orders.
   */
  public function getOrdersList($offset = 0, $limit = 100, $start_date = NULL, $end_date = NULL);

  /**
   * Get latest Odoo orders count.
   *
   * @param int|null $start_date
   *   Only get orders changed since given timestamp. NULL to omit filter.
   * @param int|null $end_date
   *   Only get orders changed before given timestamp. NULL to omit filter.
   *
   * @return int
   *   Odoo orders count.
   */
  public function getOrdersCount($start_date = NULL, $end_date = NULL);

}
