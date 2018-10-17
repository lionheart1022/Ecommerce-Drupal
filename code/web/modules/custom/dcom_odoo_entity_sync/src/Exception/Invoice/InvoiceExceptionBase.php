<?php

namespace Drupal\dcom_odoo_entity_sync\Exception\Invoice;

use Exception;

/**
 * Order not exists exception.
 */
abstract class InvoiceExceptionBase extends Exception {

  protected $orderId;

  /**
   * InvoiceExceptionBase constructor.
   *
   * @param int $order_id
   *   Odoo order ID.
   */
  public function __construct($order_id) {
    $this->orderId = $order_id;
    $this->message = $this->getExceptionMessage();
  }

  /**
   * Get exception message.
   *
   * @return string
   *   Message string.
   */
  abstract protected function getExceptionMessage();

}
