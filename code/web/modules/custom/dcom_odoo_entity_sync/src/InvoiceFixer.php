<?php

namespace Drupal\dcom_odoo_entity_sync;

use Drupal\dcom_odoo_entity_sync\Exception\Invoice\AccountMoveLineNotExists;
use Drupal\dcom_odoo_entity_sync\Exception\Invoice\AccountMoveNotExists;
use Drupal\dcom_odoo_entity_sync\Exception\Invoice\DuplicateOrder;
use Drupal\dcom_odoo_entity_sync\Exception\Invoice\InvoiceMayNotBeCancelled;
use Drupal\dcom_odoo_entity_sync\Exception\Invoice\MultipleInvoices;
use Drupal\dcom_odoo_entity_sync\Exception\Invoice\OrderNotExists;
use Drupal\odoo_api\OdooApi\ClientInterface;
use Drupal\Core\Database\Connection;
use Drupal\odoo_api_entity_sync\Exception\SyncExcludedException;
use Drupal\odoo_api_entity_sync\MappingManagerInterface;
use Drupal\odoo_api_entity_sync\SyncManagerInterface;
use LogicException;

/**
 * The Invoice Fixer service class.
 */
class InvoiceFixer implements InvoiceFixerInterface {

  /**
   * Drupal\Core\Database\Driver\mysql\Connection definition.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Odoo API client.
   *
   * @var \Drupal\odoo_api\OdooApi\ClientInterface
   */
  protected $api;

  /**
   * Odoo API entity sync service.
   *
   * @var \Drupal\odoo_api_entity_sync\SyncManagerInterface
   */
  protected $entitySync;

  /**
   * Entity sync mapping manager service.
   *
   * @var \Drupal\odoo_api_entity_sync\MappingManagerInterface
   */
  protected $mapping;

  /**
   * Orders data cache.
   *
   * @var array
   */
  protected $ordersCache;

  /**
   * Invoices data cache.
   *
   * @var array
   */
  protected $invoiceCache;

  /**
   * Account moves data cache.
   *
   * @var array
   */
  protected $accountMoveCache;

  /**
   * Account move lines cache.
   *
   * The array structure is:
   * Journal ID => move lines.
   *
   * @var array
   */
  protected $accountMoveLinesCache;

  /**
   * Array of found order duplicates, keyed by correct Odoo order IDs.
   *
   * @var array
   */
  protected $duplicateOrders;

  /**
   * Odoo IDs of invoices, keyed by Odoo order ID.
   *
   * @var array
   */
  protected $invoiceIds;

  /**
   * Constructs a new InvoiceFixer object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   Database connection.
   * @param \Drupal\odoo_api\OdooApi\ClientInterface $api
   *   Odoo API client.
   * @param \Drupal\odoo_api_entity_sync\SyncManagerInterface $sync
   *   Odoo API entity sync service.
   * @param \Drupal\odoo_api_entity_sync\MappingManagerInterface $mapping
   *   Entity sync mapping manager service.
   */
  public function __construct(Connection $database, ClientInterface $api, SyncManagerInterface $sync, MappingManagerInterface $mapping) {
    $this->database = $database;
    $this->api = $api;
    $this->entitySync = $sync;
    $this->mapping = $mapping;
  }

  /**
   * {@inheritdoc}
   */
  public function preloadOrders(array $order_ids) {
    $this->ordersCache = [];
    $this->invoiceCache = [];
    $this->accountMoveCache = [];
    $this->accountMoveLinesCache = [];
    $this->duplicateOrders = [];

    $orders = $this
      ->api
      // @TODO: load only required fields.
      ->read('sale.order', $order_ids);

    $invoice_ids = [];

    foreach ($orders as $order) {
      $this->ordersCache[$order['id']] = $order;
      $this->invoiceIds[$order['id']] = isset($order['invoice_ids']) ? $order['invoice_ids'] : [];
      $invoice_ids = array_merge($invoice_ids, $this->invoiceIds[$order['id']]);
    }

    if ($invoice_ids) {
      $account_move_ids = [];
      // @TODO: load only required fields.
      foreach ($this->api->read('account.invoice', $invoice_ids) as $invoice) {
        $this->invoiceCache[$invoice['id']] = $invoice;
        if (isset($invoice['move_id'][0])) {
          $account_move_ids[] = $invoice['move_id'][0];
        }
      }

      if ($account_move_ids) {
        $line_ids = [];
        // @TODO: load only required fields.
        foreach ($this->api->read('account.move', $account_move_ids) as $account_move) {
          $this->accountMoveCache[$account_move['id']] = $account_move;
          if (!empty($account_move['line_ids'])) {
            $line_ids = array_merge($line_ids, $account_move['line_ids']);
          }
        }

        if ($line_ids) {
          foreach ($this->api->read('account.move.line', $line_ids) as $account_move_line) {
            $account_id = $account_move_line['account_id'][0];
            $this->accountMoveLinesCache[$account_id][$account_move_line['id']] = $account_move_line;
          }
        }
      }
    }

    $filter = [
      ['id', 'not in', array_values($order_ids)],
      ['name', 'in', array_values($this->findDrupalOrderIds($order_ids))],
    ];
    $duplicate_odoo_orders = $this->api->searchRead('sale.order', $filter, ['id', 'name']);

    foreach ($duplicate_odoo_orders as $duplicate_odoo_order) {
      // Find *correct* Odoo order.
      $found = FALSE;
      foreach ($this->ordersCache as $order_id => $order) {
        if ($order['name'] == $duplicate_odoo_order['name']) {
          $this->duplicateOrders[$order_id][] = $duplicate_odoo_order['id'];
          $found = TRUE;
          break;
        }
      }

      if (!$found) {
        throw new \LogicException('No correct source order found for duplicate.');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function checkAndFixInvoice($order_id) {
    // Make sure there's only one order with such number.
    $this->assertSingleOrder($order_id);

    // No invoice? Just create one then.
    $count = $this->invoiceCount($order_id);
    if (!$count) {
      if ($this->orderCancelled($order_id)) {
        // Do not re-create an invoice if order is cancelled.
        return FALSE;
      }

      if ($this->orderAmountIsZero($order_id)) {
        // Do not re-create an invoice if order amount is zero. We don't need
        // invoices for orders where customer gets something for free.
        return FALSE;
      }

      // No invoice found. Creating new one.
      $this->recreateInvoice($order_id);

      return TRUE;
    }

    // Make sure we only have 1 invoice.
    if ($count == 1) {
      if ($this->orderInvoiceCorrect($order_id)) {
        // Invoice is fine. Just skip it.
        return FALSE;
      }

      if (!$this->orderInvoiceMayBeCancelled($order_id)) {
        throw new InvoiceMayNotBeCancelled($order_id);
      }

      $this->cancelOrderInvoice($order_id);
      $this->recreateInvoice($order_id);

      return TRUE;
    }
    elseif ($count > 1) {
      throw new MultipleInvoices($order_id);
    }
    else {
      throw new LogicException('Wut?');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function invoiceCount($order_id) {
    if (!isset($this->ordersCache[$order_id])) {
      throw new OrderNotExists($order_id);
    }

    if (!isset($this->invoiceIds[$order_id])) {
      return 0;
    }

    return count($this->invoiceIds[$order_id]);
  }

  /**
   * {@inheritdoc}
   */
  public function orderInvoiceCorrect($order_id) {
    $this
      ->assertSingleInvoice($order_id);

    return $this->invoiceValidated($order_id)
      && $this->orderTotalMatchesInvoiceTotal($order_id)
      && $this->invoiceTotalMatchesInvoiceJournal($order_id)
      && $this->allOrderItemsInvoiced($order_id);
  }

  /**
   * {@inheritdoc}
   */
  public function orderInvoiceMayBeCancelled($order_id) {
    $invoice_id = $this->getOrderInvoiceId($order_id);

    return empty($this->invoiceCache[$invoice_id]['payment_ids'])
      && empty($this->invoiceCache[$invoice_id]['payment_move_line_ids']);
  }

  /**
   * {@inheritdoc}
   */
  public function cancelOrderInvoice($order_id) {
    $drupal_order_ids = $this->findDrupalOrderIds([$order_id]);
    $drupal_order_id = reset($drupal_order_ids);
    if (!$drupal_order_id) {
      throw new \LogicException();
    }

    $invoice_id = $this->getOrderInvoiceId($order_id);

    $this->api->rawModelApiCall('account.invoice', 'action_cancel', [$invoice_id]);
    $this->api->write('account.invoice', [$invoice_id], ['move_name' => NULL]);
    $this->api->unlink('account.invoice', [$invoice_id]);

    // Delete invoice mapping from Drupal.
    $this
      ->mapping
      ->setSyncStatus('commerce_order', 'account.invoice', 'default', [$drupal_order_id => NULL], MappingManagerInterface::STATUS_DELETED);

    // Delete invoice ID from Order -> Invoice mapping.
    // @TODO: Do we need to remove any cached invoice data as well?
    unset($this->invoiceIds[$order_id]);
  }

  /**
   * {@inheritdoc}
   */
  public function recreateInvoice($order_id) {
    $this->assertNoInvoice($order_id);
    try {
      $drupal_order_ids = $this->findDrupalOrderIds([$order_id]);
      $drupal_order_id = reset($drupal_order_ids);
      if (!$drupal_order_id) {
        throw new \LogicException();
      }
      $mapping = $this->entitySync->sync('commerce_order', 'account.invoice', 'default', [$drupal_order_id]);
      // Save new invoice ID to the map.
      $this->invoiceIds[$order_id] = $mapping[$drupal_order_id];
      // @TODO: Save new invoice and journal entry to cache.

    }
    catch (SyncExcludedException $e) {
      // Just skip if invoice export is skipped.
    }
  }

  /**
   * Make sure there's exactly one order with such name.
   *
   * @param int $order_id
   *   Odoo order ID.
   *
   * @return $this
   *   Self.
   *
   * @throws \Drupal\dcom_odoo_entity_sync\Exception\Invoice\OrderNotExists
   *   No such order exists.
   * @throws \Drupal\dcom_odoo_entity_sync\Exception\Invoice\DuplicateOrder
   *   An order with same order name (title) exists.
   */
  protected function assertSingleOrder($order_id) {
    $this->assertOrderExists($order_id);

    if (!empty($this->duplicateOrders[$order_id])) {
      throw new DuplicateOrder($order_id);
    }

    return $this;
  }

  /**
   * Make sure the order with given ID exists.
   *
   * @param int $order_id
   *   Odoo order ID.
   *
   * @return $this
   *   Self.
   *
   * @throws \Drupal\dcom_odoo_entity_sync\Exception\Invoice\OrderNotExists
   *   No such order exists.
   */
  protected function assertOrderExists($order_id) {
    if (empty($this->ordersCache[$order_id])) {
      throw new OrderNotExists($order_id);
    }

    return $this;
  }

  /**
   * Check if invoice is validated.
   *
   * @param int $order_id
   *   Odoo order ID.
   *
   * @return bool
   *   Whether the invoice is validated.
   *
   * @throws \Drupal\dcom_odoo_entity_sync\Exception\Invoice\MultipleInvoices
   *   Multiple invoices.
   * @throws \Drupal\dcom_odoo_entity_sync\Exception\Invoice\OrderNotExists
   *   Order does not exist.
   */
  protected function invoiceValidated($order_id) {
    $invoice_id = $this->getOrderInvoiceId($order_id);

    return isset($this->invoiceCache[$invoice_id]['state'])
      && !in_array($this->invoiceCache[$invoice_id]['state'], ['draft']);
  }

  /**
   * Check if invoice total matches order total.
   *
   * @param int $order_id
   *   Odoo order ID.
   *
   * @return bool
   *   Whether the invoice total matches order total.
   *
   * @throws \Drupal\dcom_odoo_entity_sync\Exception\Invoice\MultipleInvoices
   *   Multiple invoices.
   * @throws \Drupal\dcom_odoo_entity_sync\Exception\Invoice\OrderNotExists
   *   Order does not exist.
   */
  protected function orderTotalMatchesInvoiceTotal($order_id) {
    $invoice_id = $this->getOrderInvoiceId($order_id);

    return isset($this->invoiceCache[$invoice_id]['amount_total'])
      && isset($this->ordersCache[$order_id]['amount_total'])
      && (bccomp($this->invoiceCache[$invoice_id]['amount_total'], $this->ordersCache[$order_id]['amount_total']) == 0);
  }

  /**
   * Check if invoice total matches journal.
   *
   * @param int $order_id
   *   Odoo order ID.
   *
   * @return bool
   *   Whether the invoice total matches journal entry.
   *
   * @throws \Drupal\dcom_odoo_entity_sync\Exception\Invoice\MultipleInvoices
   *   Multiple invoices.
   * @throws \Drupal\dcom_odoo_entity_sync\Exception\Invoice\OrderNotExists
   *   Order does not exist.
   */
  protected function invoiceTotalMatchesInvoiceJournal($order_id) {
    $invoice_id = $this->getOrderInvoiceId($order_id);
    try {
      $account_move_id = $this->getAccountMoveId($order_id);
    }
    catch (AccountMoveNotExists $e) {
      // Simply return FALSE if account move does not exist. We should re-create
      // such invoices.
      return FALSE;
    }

    if (!isset($this->accountMoveCache[$account_move_id])) {
      throw new \LogicException();
    }

    $account_move_line = $this->findAccountMoveDebitLine($order_id);

    return isset($this->invoiceCache[$invoice_id]['amount_total'])
      && isset($account_move_line['debit'])
      && (bccomp($this->invoiceCache[$invoice_id]['amount_total'], $account_move_line['debit']) == 0);
  }

  /**
   * Find Drupal order IDs for given Odoo order IDs.
   *
   * @param array $order_ids
   *   Array of Odoo order IDs.
   *
   * @return array
   *   Array of Drupal order IDs, keyed by Odoo order IDs.
   *   The array values may be FALSE if there's no corresponding Drupal order.
   */
  protected function findDrupalOrderIds(array $order_ids) {
    $mapping = $this->mapping->findMappedEntities('sale.order', $order_ids);
    $drupal_ids = array_fill_keys($order_ids, FALSE);
    foreach ($mapping as $order_id => $entity_types) {
      if ($entity_types === FALSE) {
        // No corresponding Drupal entity.
        continue;
      }
      foreach ($entity_types as $entity_type => $export_types) {
        foreach ($export_types as $export_type => $entity_id) {
          if ($entity_type == 'commerce_order'
            && $export_type == 'default') {
            $drupal_ids[$order_id] = $entity_id;
          }
        }
      }
    }

    return $drupal_ids;
  }

  /**
   * Get invoice ID by order ID.
   *
   * @param int $order_id
   *   Odoo order ID.
   *
   * @return int
   *   Odoo invoice ID.
   *
   * @throws \Drupal\dcom_odoo_entity_sync\Exception\Invoice\MultipleInvoices
   *   Multiple invoices.
   * @throws \Drupal\dcom_odoo_entity_sync\Exception\Invoice\OrderNotExists
   *   Order does not exist.
   */
  protected function getOrderInvoiceId($order_id) {
    $this->assertSingleInvoice($order_id);

    return reset($this->invoiceIds[$order_id]);
  }

  /**
   * Make sure there are no invoices on the given order.
   *
   * @param int $order_id
   *   Order ID.
   *
   * @return $this
   *   Self.
   *
   * @throws \Drupal\dcom_odoo_entity_sync\Exception\Invoice\MultipleInvoices
   *   Invoice exists.
   * @throws \Drupal\dcom_odoo_entity_sync\Exception\Invoice\OrderNotExists
   *   Order does not exist.
   */
  protected function assertNoInvoice($order_id) {
    if ($this->invoiceCount($order_id) != 0) {
      throw new MultipleInvoices($order_id);
    }

    return $this;
  }

  /**
   * Make sure there's only one invoice on the given order.
   *
   * @param int $order_id
   *   Order ID.
   *
   * @return $this
   *   Self.
   *
   * @throws \Drupal\dcom_odoo_entity_sync\Exception\Invoice\MultipleInvoices
   *   Multiple invoices.
   * @throws \Drupal\dcom_odoo_entity_sync\Exception\Invoice\OrderNotExists
   *   Order does not exist.
   */
  protected function assertSingleInvoice($order_id) {
    if ($this->invoiceCount($order_id) != 1) {
      throw new MultipleInvoices($order_id);
    }

    return $this;
  }

  /**
   * Get Odoo account move ID for given order ID.
   *
   * @param int $order_id
   *   Odoo order ID.
   *
   * @return int
   *   Odoo account move (journal entry) ID.
   *
   * @throws \Drupal\dcom_odoo_entity_sync\Exception\Invoice\MultipleInvoices
   *   Multiple invoices.
   * @throws \Drupal\dcom_odoo_entity_sync\Exception\Invoice\OrderNotExists
   *   Order does not exist.
   * @throws \Drupal\dcom_odoo_entity_sync\Exception\Invoice\AccountMoveNotExists
   *   Account move does not exist.
   */
  protected function getAccountMoveId($order_id) {
    $invoice_id = $this->getOrderInvoiceId($order_id);

    if (!isset($this->invoiceCache[$invoice_id]['move_id'][0])) {
      throw new AccountMoveNotExists($order_id);
    }

    return $this->invoiceCache[$invoice_id]['move_id'][0];
  }

  /**
   * Find Account move debit line for a given orders invoice.
   *
   * @param int $order_id
   *   Odoo order ID.
   *
   * @return array
   *   Account move debit line object as array.
   *
   * @throws \Drupal\dcom_odoo_entity_sync\Exception\Invoice\MultipleInvoices
   *   Multiple invoices.
   * @throws \Drupal\dcom_odoo_entity_sync\Exception\Invoice\OrderNotExists
   *   Order does not exist.
   * @throws \Drupal\dcom_odoo_entity_sync\Exception\Invoice\AccountMoveLineNotExists
   *   Account move debit line does not exist.
   */
  protected function findAccountMoveDebitLine($order_id) {
    $invoice_id = $this->getOrderInvoiceId($order_id);

    if (!isset($this->invoiceCache[$invoice_id]['account_id'][0])) {
      throw new \LogicException();
    }

    $invoice_account_id = $this->invoiceCache[$invoice_id]['account_id'][0];
    if (!isset($this->accountMoveLinesCache[$invoice_account_id])) {
      throw new AccountMoveLineNotExists($order_id);
    }

    foreach ($this->accountMoveLinesCache[$invoice_account_id] as $account_move_line) {
      if ($account_move_line['invoice_id'][0] == $invoice_id) {
        return $account_move_line;
      }
    }

    throw new AccountMoveLineNotExists($order_id);
  }

  /**
   * Check if order is cancelled.
   *
   * @param int $order_id
   *   Odoo order ID.
   *
   * @return bool
   *   Whether given order was cancelled.
   */
  protected function orderCancelled($order_id) {
    return $this->ordersCache[$order_id]['state'] == 'cancel';
  }

  /**
   * Check if order amount is zero.
   *
   * @param int $order_id
   *   Odoo order ID.
   *
   * @return bool
   *   Whether given order amount is zero.
   */
  protected function orderAmountIsZero($order_id) {
    return $this->ordersCache[$order_id]['amount_total'] == 0;
  }

  /**
   * Check if all order items are invoiced.
   *
   * @param int $order_id
   *   Odoo order ID.
   *
   * @return bool
   *   Whether all item of a given order are invoiced.
   *
   * @throws \Drupal\dcom_odoo_entity_sync\Exception\Invoice\OrderNotExists
   *   No such order exists.
   */
  protected function allOrderItemsInvoiced($order_id) {
    $this->assertOrderExists($order_id);

    if (empty($this->ordersCache[$order_id]['order_line'])) {
      return TRUE;
    }

    $order_lines = $this->api->read('sale.order.line', $this->ordersCache[$order_id]['order_line'], ['invoice_status']);

    $not_invoiced_lines = array_filter($order_lines, function ($order_line) {
      return empty($order_line['invoice_status'])
        || $order_line['invoice_status'] != 'invoiced';
    });

    return count($not_invoiced_lines) == 0;
  }

}
