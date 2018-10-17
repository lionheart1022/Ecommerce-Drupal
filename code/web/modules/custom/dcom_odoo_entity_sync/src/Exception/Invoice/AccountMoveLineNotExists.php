<?php

namespace Drupal\dcom_odoo_entity_sync\Exception\Invoice;

/**
 * Account move not exists exception.
 */
class AccountMoveLineNotExists extends InvoiceExceptionBase {

  /**
   * {@inheritdoc}
   */
  protected function getExceptionMessage() {
    return "Account move line (invoice journal entry line) does not exist. Odoo order ID {$this->orderId}";
  }

}
