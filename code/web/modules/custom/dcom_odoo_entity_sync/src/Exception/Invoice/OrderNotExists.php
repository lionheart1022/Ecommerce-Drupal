<?php

namespace Drupal\dcom_odoo_entity_sync\Exception\Invoice;

/**
 * Order not exists exception.
 */
class OrderNotExists extends InvoiceExceptionBase {

  /**
   * {@inheritdoc}
   */
  protected function getExceptionMessage() {
    return "No such order. Odoo order ID {$this->orderId}";
  }

}
