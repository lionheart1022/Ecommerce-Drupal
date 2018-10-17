<?php

namespace Drupal\dcom_odoo_entity_sync;

use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Interface CarrierResolverInterface.
 */
interface CarrierResolverInterface {

  /**
   * Get Odoo carrier ID for given shipment.
   *
   * @param \Drupal\Core\Entity\EntityInterface $order
   *   Order entity.
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   *   Shipment entity.
   *
   * @return int|null
   *   Odoo carrier ID or NULL.
   *
   * @throws \Drupal\odoo_api\OdooApi\Exception\DataException
   *   Data/mapping exception.
   */
  public function getOdooCarrierId(EntityInterface $order, ShipmentInterface $shipment);

  /**
   * Get Odoo shipping product ID.
   *
   * @param \Drupal\Core\Entity\EntityInterface $order
   *   Order entity.
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   *   Shipment entity.
   *
   * @return int
   *   Odoo shipping product ID.
   *
   * @throws \Drupal\odoo_api\OdooApi\Exception\DataException
   *   Missing shipping product.
   */
  public function getShippingProductId(EntityInterface $order, ShipmentInterface $shipment);

  /**
   * Get Odoo shipping product name.
   *
   * @param \Drupal\Core\Entity\EntityInterface $order
   *   Order entity.
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   *   Shipment entity.
   *
   * @return string
   *   Odoo shipping product name.
   *
   * @throws \Drupal\odoo_api\OdooApi\Exception\DataException
   *   Missing shipping product.
   */
  public function getShippingProductName(EntityInterface $order, ShipmentInterface $shipment);

  /**
   * Check if given ID is an ID or delivery product.
   *
   * @param int $odoo_product_id
   *   Odoo product ID.
   *
   * @return bool
   *   Whether given product is a delivery.
   */
  public function isDeliveryProduct($odoo_product_id);

  /**
   * Get all shipping products from Odoo.
   *
   * @return array
   *   Array of Odoo carrier ID => Odoo shipping product
   */
  public function getAllShippingProducts();

}
