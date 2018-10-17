<?php

namespace Drupal\dcom_odoo_entity_sync\Exception\Invoice;

/**
 * Duplicate order exception.
 *
 * Thrown when there are two order with same number.
 */
class DuplicateOrder extends InvoiceExceptionBase {

  /**
   * {@inheritdoc}
   */
  protected function getExceptionMessage() {
    return "Duplicate order: an order with same name is found for Odoo order ID {$this->orderId}";
  }

}
