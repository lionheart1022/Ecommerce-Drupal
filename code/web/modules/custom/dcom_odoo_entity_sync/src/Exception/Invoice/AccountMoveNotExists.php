<?php

namespace Drupal\dcom_odoo_entity_sync\Exception\Invoice;

/**
 * Account move not exists exception.
 */
class AccountMoveNotExists extends InvoiceExceptionBase {

  /**
   * {@inheritdoc}
   */
  protected function getExceptionMessage() {
    return "Account move (invoice journal entry) does not exist. Odoo order ID {$this->orderId}";
  }

}
