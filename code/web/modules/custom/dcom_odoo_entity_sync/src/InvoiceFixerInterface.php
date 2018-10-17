<?php

namespace Drupal\dcom_odoo_entity_sync;

/**
 * Interface InvoiceFixerInterface.
 */
interface InvoiceFixerInterface {

  /**
   * Pre-load orders data.
   *
   * Make sure to call this method before working with orders.
   *
   * @param array $order_ids
   *   Array of Odoo order IDs.
   *
   * @throws \Drupal\odoo_api\OdooApi\Exception\AuthException
   *   Odoo auth exception.
   * @throws \fXmlRpc\Exception\FaultException
   *   XMLRPC exception.
   */
  public function preloadOrders(array $order_ids);

  /**
   * Check if order invoice is correct and fix if needed.
   *
   * @param int $order_id
   *   Odoo order ID.
   *
   * @return bool
   *   TRUE if invoice was recreated, FALSE if it was correct.
   *
   * @throws \Drupal\odoo_api\OdooApi\Exception\AuthException
   *   Odoo auth exception.
   * @throws \fXmlRpc\Exception\FaultException
   *   XMLRPC exception.
   * @throws \Drupal\dcom_odoo_entity_sync\Exception\Invoice\InvoiceExceptionBase
   *   Error checking or re-creating invoice.
   */
  public function checkAndFixInvoice($order_id);

  /**
   * Check how many invoices given order has.
   *
   * @param int $order_id
   *   Odoo order ID.
   *
   * @return int
   *   Number of invoices.
   *
   * @throws \Drupal\dcom_odoo_entity_sync\Exception\Invoice\OrderNotExists
   *   Order does not exist.
   */
  public function invoiceCount($order_id);

  /**
   * Check if order invoice is correct.
   *
   * The invoice is correct if it's validated and amount total matches journal
   * entries.
   *
   * @param int $order_id
   *   Odoo order ID.
   *
   * @return bool
   *   Whether invoice is correct.
   *
   * @throws \Drupal\dcom_odoo_entity_sync\Exception\Invoice\MultipleInvoices
   *   Multiple invoices.
   * @throws \Drupal\dcom_odoo_entity_sync\Exception\Invoice\OrderNotExists
   *   Order does not exist.
   */
  public function orderInvoiceCorrect($order_id);

  /**
   * Check if order invoice may be cancelled.
   *
   * The invoice may be cancelled if there are not payments.
   *
   * @param int $order_id
   *   Odoo order ID.
   *
   * @return bool
   *   Whether invoice may be safely cancelled.
   *
   * @throws \Drupal\dcom_odoo_entity_sync\Exception\Invoice\MultipleInvoices
   *   Multiple invoices.
   * @throws \Drupal\dcom_odoo_entity_sync\Exception\Invoice\OrderNotExists
   *   Order does not exist.
   */
  public function orderInvoiceMayBeCancelled($order_id);

  /**
   * Cancel order invoice.
   *
   * @param int $order_id
   *   Odoo order ID.
   *
   * @throws \Drupal\odoo_api\OdooApi\Exception\AuthException
   *   Odoo auth exception.
   * @throws \fXmlRpc\Exception\FaultException
   *   XMLRPC exception.
   * @throws \Drupal\dcom_odoo_entity_sync\Exception\Invoice\MultipleInvoices
   *   Multiple invoices.
   * @throws \Drupal\dcom_odoo_entity_sync\Exception\Invoice\OrderNotExists
   *   Order does not exist.
   */
  public function cancelOrderInvoice($order_id);

  /**
   * Re-create invoice for an order.
   *
   * @param int $order_id
   *   Odoo order ID.
   *
   * @throws \Drupal\dcom_odoo_entity_sync\Exception\Invoice\MultipleInvoices
   *   Invoice already exists.
   * @throws \Drupal\dcom_odoo_entity_sync\Exception\Invoice\OrderNotExists
   *   Order does not exist.
   * @throws \Drupal\odoo_api_entity_sync\Exception\ExportException
   *   Export failure.
   */
  public function recreateInvoice($order_id);

}
