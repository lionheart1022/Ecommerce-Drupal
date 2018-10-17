<?php

namespace Drupal\dcom_odoo_entity_sync\Exception\Invoice;

/**
 * Exception thrown when invoice may not be cancelled.
 */
class InvoiceMayNotBeCancelled extends InvoiceExceptionBase {

  /**
   * {@inheritdoc}
   */
  protected function getExceptionMessage() {
    return "Invoice may not be cancelled for Odoo order ID {$this->orderId}";
  }

}
