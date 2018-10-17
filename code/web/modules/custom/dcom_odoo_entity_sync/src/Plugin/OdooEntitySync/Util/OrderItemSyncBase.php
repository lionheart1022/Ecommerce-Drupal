<?php

namespace Drupal\dcom_odoo_entity_sync\Plugin\OdooEntitySync\Util;

use Drupal\Core\Entity\EntityInterface;
use Drupal\odoo_api_entity_sync\Event\OdooExportEvent;
use Drupal\odoo_api_entity_sync\Exception\GenericException;
use Drupal\odoo_api_entity_sync\Exception\ServerErrorException;
use Drupal\odoo_api_entity_sync\Plugin\EntitySyncBase;
use Exception;
use fXmlRpc\Exception\FaultException;

/**
 * Abstract order item sync class.
 *
 * Used to workaround updating items in a locked order.
 */
abstract class OrderItemSyncBase extends EntitySyncBase {

  /**
   * {@inheritdoc}
   */
  public function export(EntityInterface $entity) {
    try {
      if ($odoo_id = $this->getOwnOdooId($entity)) {
        $odoo_id = (int) $odoo_id;
        try {
          $this->odoo->write($this->getOdooModelName(), [$odoo_id], $this->getOdooFields($entity));
        }
        catch (FaultException $e) {
          if (strpos($e->getMessage(), 'It is forbidden to modify the following fields in a locked order') === FALSE) {
            throw $e;
          }
          $this->unlockAndWrite($entity, $odoo_id);
        }
        $this->eventDispatcher->dispatch(OdooExportEvent::WRITE, new OdooExportEvent($entity, $this->getOdooModelName(), $this->getExportType(), $odoo_id));
      }
      else {
        $odoo_id = $this->odoo->create($this->getOdooModelName(), $this->getOdooFields($entity));
        $this->eventDispatcher->dispatch(OdooExportEvent::CREATE, new OdooExportEvent($entity, $this->getOdooModelName(), $this->getExportType(), $odoo_id));
      }
      return $odoo_id;
    }
    catch (FaultException $e) {
      throw new ServerErrorException($this->getEntityType(), $this->getOdooModel(), $this->getExportType(), $entity->id(), $e);
    }
  }

  /**
   * Unlock parent order, save object, then lock order back again.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity being exported.
   * @param int $odoo_id
   *   Odoo object ID.
   *
   * @throws \Drupal\odoo_api_entity_sync\Exception\ExportException
   *   Export exceptions.
   * @throws \Exception
   *   Any exceptions caught during export.
   */
  protected function unlockAndWrite(EntityInterface $entity, $odoo_id) {
    $odoo_order_id = $this->getOdooOrderId($entity, $this->getOrderId($entity));
    $this->assertOrderLocked($entity, $odoo_order_id);
    $this->unlockOdooOrder($odoo_order_id);
    try {
      $this->odoo->write($this->getOdooModelName(), [$odoo_id], $this->getOdooFields($entity));
    }
    catch (Exception $e) {
      $this->lockOdooOrder($odoo_order_id);
      throw $e;
    }
    $this->lockOdooOrder($odoo_order_id);
  }

  /**
   * Finds Odoo order ID.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Exported entity object.
   * @param int $order_id
   *   Drupal order ID.
   *
   * @return int
   *   Odoo order ID.
   *
   * @throws \Drupal\odoo_api_entity_sync\Exception\GenericException
   */
  protected function getOdooOrderId(EntityInterface $entity, $order_id) {
    $ids = $this
      ->map
      ->getIdMap('commerce_order', 'sale.order', 'default', $order_id);

    if (empty($ids[$order_id])) {
      throw new GenericException($this->getEntityType(), $this->getOdooModel(), $this->getExportType(), $entity->id(), 'Error resolving Odoo ID for a parent order. This should never happen and is most likely a bug.');
    }

    return $ids[$order_id];
  }

  /**
   * Unlocks Odoo order.
   *
   * @param int $odoo_order_id
   *   Odoo order object ID.
   */
  protected function unlockOdooOrder($odoo_order_id) {
    $this->odoo->write('sale.order', [$odoo_order_id], ['state' => 'sale']);
  }

  /**
   * Locks Odoo order.
   *
   * @param int $odoo_order_id
   *   Odoo order object ID.
   */
  protected function lockOdooOrder($odoo_order_id) {
    $this->odoo->write('sale.order', [$odoo_order_id], ['state' => 'done']);
  }

  /**
   * Assert order is locked.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Exported entity object.
   * @param int $odoo_order_id
   *   Odoo order object ID.
   *
   * @throws \Drupal\odoo_api_entity_sync\Exception\GenericException
   *   Unexpected order state.
   */
  protected function assertOrderLocked(EntityInterface $entity, $odoo_order_id) {
    $rows = $this->odoo->read('sale.order', [$odoo_order_id], ['state']);
    $fields = reset($rows);
    if (empty($fields['state'])
      || $fields['state'] != 'done') {
      throw new GenericException($this->getEntityType(), $this->getOdooModel(), $this->getExportType(), $entity->id(), 'Error unlocking Odoo order: order is not locked. This should never happen and is most likely a bug.');
    }
  }

  /**
   * Get Drupal order ID.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Exported entity object.
   *
   * @return int
   *   Drupal order ID.
   *
   * @throws \Drupal\odoo_api_entity_sync\Exception\ExportException
   *   Generic export exception thrown by implementation.
   */
  abstract protected function getOrderId(EntityInterface $entity);

}
