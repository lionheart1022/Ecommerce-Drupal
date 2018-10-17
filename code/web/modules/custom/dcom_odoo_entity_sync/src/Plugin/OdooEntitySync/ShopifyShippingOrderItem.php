<?php

namespace Drupal\dcom_odoo_entity_sync\Plugin\OdooEntitySync;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Entity\EntityInterface;
use Drupal\dcom_odoo_entity_sync\Plugin\OdooEntitySync\Util\ShopifyShipmentsTrait;
use Drupal\odoo_api\OdooApi\Exception\DataException;
use Drupal\odoo_api_entity_sync\Exception\GenericException;

/**
 * Orders sync.
 *
 * @OdooEntitySync(
 *   id = "dcom_odoo_entity_sync_order_shopify_shipping",
 *   entityType = "commerce_order",
 *   odooModel = "sale.order.line",
 *   exportType = "shopify_shipping_line",
 * )
 */
class ShopifyShippingOrderItem extends ShippingOrderItem {

  use ShopifyShipmentsTrait;

  /**
   * {@inheritdoc}
   */
  protected function getOdooFields(EntityInterface $order) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $shopify_shipments = $order->getData('shopify_shipping_lines');
    $shopify_shipment = reset($shopify_shipments);

    try {
      $currency_id = $this->currencyResolver->findCurrencyIdByCode('USD');
    }
    catch (DataException $e) {
      throw new GenericException($this->getEntityType(), $this->getOdooModel(), $this->getExportType(), $order->id(), 'Error resolving currency.', $e);
    }

    try {
      $odoo_carrier_id = $this->getCarrierIdFromShopifyShipment($shopify_shipment);
    }
    catch (DataException $e) {
      throw new GenericException($this->getEntityType(), $this->getOdooModel(), $this->getExportType(), $order->id(), 'Error resolving carrier ID.', $e);
    }

    $map = $this->carrierResolver->getAllShippingProducts();

    if (!isset($map[$odoo_carrier_id]['product_id'][0]) || !isset($map[$odoo_carrier_id]['product_id'][1])) {
      $e =  new DataException((string) new FormattableMarkup('Could not find Odoo shipping product "@code".', ['@code' => $odoo_carrier_id]));
      throw new GenericException($this->getEntityType(), $this->getOdooModel(), $this->getExportType(), $order->id(), 'Error resolving shipping method details.', $e);
    }

    $shipping_product_id = $map[$odoo_carrier_id]['product_id'][0];
    $shipping_product_name = $map[$odoo_carrier_id]['product_id'][1];
    // Shipping line.
    return [
      'order_id' => $this->getReferencedEntityOdooId('commerce_order', 'sale.order', 'default', $order->id()),
      'product_id' => $shipping_product_id,
      'name' => $shipping_product_name,
      'product_uom_qty' => 1,
      'discount' => 0,
      'price_unit' => $this->getDeliveryPriceFromShopifyShipment($shopify_shipment),
      'currency_id' => $currency_id,
      'is_delivery' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function shouldSync(EntityInterface $order) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $shopify_shipments = $order->getData('shopify_shipping_lines');
    // Only export if there's a shopify shipment.
    if ($shopify_shipments
      && ($shopify_shipment = reset($shopify_shipments))) {
      return $this->shouldExportOrder($order);
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function shouldDelete(EntityInterface $entity) {
    // Shipment order items are never removed.
    // Instead, the amount is set to 0.
    return FALSE;
  }

}
