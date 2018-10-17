<?php

namespace Drupal\dcom_odoo_entity_sync\Plugin\OdooEntitySync;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\dcom_odoo_entity_sync\Util\OrderSyncTrait;
use Drupal\odoo_api_entity_sync\Exception\GenericException;
use Drupal\odoo_api_entity_sync\Exception\SyncExcludedException;
use Drupal\odoo_api_entity_sync\Plugin\EntitySyncBase;
use Drupal\odoo_api_entity_sync\Event\OdooExportEvent;
use Drupal\odoo_api_entity_sync\Exception\ServerErrorException;
use fXmlRpc\Exception\FaultException;

/**
 * Invoices sync.
 *
 * @OdooEntitySync(
 *   id = "dcom_odoo_entity_sync_invoice",
 *   entityType = "commerce_order",
 *   odooModel = "account.invoice"
 * )
 */
class Invoice extends EntitySyncBase {

  use OrderSyncTrait;

  /**
   * {@inheritdoc}
   */
  protected function getOdooFields(EntityInterface $entity) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function shouldSync(EntityInterface $entity) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $entity;
    // Only fulfilled or completed orders can be invoiced.
    // See odoo-11.0\addons\sale\models\sale.py line 73:
    // if order.state not in ('sale', 'done'): invoice_status = 'no'.
    return $this->shouldExportOrder($order) && in_array($order->getState()->value, ['fulfillment', 'completed']);
  }

  /**
   * {@inheritdoc}
   */
  protected function shouldDelete(EntityInterface $entity) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function export(EntityInterface $entity) {
    try {
      // Assert entity.
      if (!($entity instanceof OrderInterface)) {
        // This should never happen.
        throw new \InvalidArgumentException();
      }

      // Make sure the order and order items are exported.
      $this->ensureDependenciesExported($entity);

      if ($odoo_id = $this->getOwnOdooId($entity)) {
        // No need to support update invoice operation for now.
        $this->eventDispatcher->dispatch(OdooExportEvent::WRITE, new OdooExportEvent($entity, $this->getOdooModelName(), $this->getExportType(), $odoo_id));
      }
      else {
        $order_odoo_id = $this->getReferencedEntityOdooId('commerce_order', 'sale.order', 'default', $entity->id());
        $odoo_id = $this->odoo->rawModelApiCall('sale.order', 'action_invoice_create', [$order_odoo_id]);
        $odoo_id = reset($odoo_id);

        // Validate the invoice: change the state from Draft => Open.
        $this->odoo->rawModelApiCall('account.invoice', 'action_invoice_open', [$odoo_id]);
        $this->eventDispatcher->dispatch(OdooExportEvent::CREATE, new OdooExportEvent($entity, $this->getOdooModelName(), $this->getExportType(), $odoo_id));
      }
      return $odoo_id;
    }
    catch (FaultException $e) {
      throw new ServerErrorException($this->getEntityType(), $this->getOdooModel(), $this->getExportType(), $entity->id(), $e);
    }
  }

  /**
   * Make sure the order and all order items are exported and up to date.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   Order entity.
   *
   * @throws \Drupal\odoo_api_entity_sync\Exception\ExportException
   * @throws \Drupal\odoo_api_entity_sync\Plugin\Exception\MissingPluginException
   */
  protected function ensureDependenciesExported(OrderInterface $order) {
    foreach (dcom_odoo_entity_sync_map() as $entity_type => $odoo_models) {
      foreach ($odoo_models as $odoo_model => $export_types) {
        foreach ($export_types as $export_type) {
          if ($entity_type == 'commerce_order'
            && $odoo_model == 'account.invoice'
            && $export_type == 'default') {
            // Avoid infinite recursion.
            continue;
          }

          switch ($entity_type) {
            case 'commerce_order':
              // Export all order stuff, like the order itself, shipping line
              // etc.
              $this->ensureDependency('commerce_order', $odoo_model, $export_type, $order->id());
              break;

            case 'commerce_order_item':
              // Export everything related to the order item.
              if ($order_items = $order->getItems()) {
                foreach ($order_items as $order_item) {
                  $this->ensureDependency('commerce_order_item', $odoo_model, $export_type, $order_item->id());
                }
              }
              else {
                throw new GenericException($this->getEntityType(), $this->getOdooModel(), $this->getExportType(), $order->id(), 'Could not create invoice for an order without order lines.');
              }
              break;

            default:
              // Do nothing. We are only interested in order and order items.
              break;
          }
        }
      }
    }
  }

  /**
   * Ensure given entity is exported and up to date.
   *
   * This method is a wrapper around SyncManager's export() method but it also
   * catches SyncExcludedException.
   *
   * @param string $entity_type
   *   Entity type.
   * @param string $odoo_model
   *   Odoo model name.
   * @param string $export_type
   *   Export type.
   * @param int $entity_id
   *   Entity ID.
   *
   * @return array
   *   An array of entity ID => Odoo object ID.
   *
   * @throws \Drupal\odoo_api_entity_sync\Plugin\Exception\MissingPluginException
   *   Missing sync plugin.
   * @throws \Drupal\odoo_api_entity_sync\Exception\ExportException
   *   Export failure.
   */
  protected function ensureDependency($entity_type, $odoo_model, $export_type, $entity_id) {
    try {
      return $this->syncManager->export($entity_type, $odoo_model, $export_type, $entity_id, TRUE);
    }
    catch (SyncExcludedException $e) {
      // Just skip if referenced entity is excluded from sync.
      return [$entity_id => FALSE];
    }
  }

}
