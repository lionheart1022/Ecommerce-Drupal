<?php

namespace Drupal\dcom_odoo_entity_sync\Exception\Invoice;

/**
 * Multiple invoices exception.
 *
 * Thrown when there are already two or more invoices for the order.
 */
class MultipleInvoices extends InvoiceExceptionBase {

  /**
   * {@inheritdoc}
   */
  protected function getExceptionMessage() {
    return "There are multiple invoices for Odoo order ID {$this->orderId}";
  }

}
