<?php

namespace Drupal\dcom_odoo_entity_sync\Plugin\OdooEntitySync;

use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\dcom_odoo_entity_sync\CarrierResolverInterface;
use Drupal\dcom_odoo_entity_sync\Plugin\OdooEntitySync\Util\OrderItemSyncBase;
use Drupal\dcom_odoo_entity_sync\Util\OrderSyncTrait;
use Drupal\odoo_api\OdooApi\ClientInterface;
use Drupal\odoo_api\OdooApi\Data\CurrencyResolverInterface;
use Drupal\odoo_api\OdooApi\Exception\DataException;
use Drupal\odoo_api_entity_sync\Exception\GenericException;
use Drupal\odoo_api_entity_sync\Exception\PluginLogicException;
use Drupal\odoo_api_entity_sync\MappingManagerInterface;
use Drupal\odoo_api_entity_sync\SyncManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Orders sync.
 *
 * @OdooEntitySync(
 *   id = "dcom_odoo_entity_sync_order_shipping",
 *   entityType = "commerce_order",
 *   odooModel = "sale.order.line",
 *   exportType = "shipping_line",
 * )
 */
class ShippingOrderItem extends OrderItemSyncBase {

  use OrderSyncTrait;

  /**
   * Currency resolver service.
   *
   * @var \Drupal\odoo_api\OdooApi\Data\CurrencyResolverInterface
   */
  protected $currencyResolver;

  /**
   * Carrier resolver service.
   *
   * @var \Drupal\dcom_odoo_entity_sync\CarrierResolverInterface
   */
  protected $carrierResolver;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ClientInterface $odoo_api,
    SyncManagerInterface $sync_manager,
    MappingManagerInterface $map,
    EventDispatcherInterface $event_dispatcher,
    CurrencyResolverInterface $currency_resolver,
    CarrierResolverInterface $carrier_resolver
  ) {
    $this->currencyResolver = $currency_resolver;
    $this->carrierResolver = $carrier_resolver;
    return parent::__construct($configuration, $plugin_id, $plugin_definition, $odoo_api, $sync_manager, $map, $event_dispatcher);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('odoo_api.api_client'),
      $container->get('odoo_api_entity_sync.sync'),
      $container->get('odoo_api_entity_sync.mapping'),
      $container->get('event_dispatcher'),
      $container->get('odoo_api.currency_resolver'),
      $container->get('dcom_odoo_entity_sync.carrier_resolver')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getOdooFields(EntityInterface $order) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */

    if ($this->hasShipment($order)
      && $amount = $order->shipments->entity->getAmount()) {
      /** @var \Drupal\commerce_price\Price $amount */

      try {
        $currency_id = $this
          ->currencyResolver
          ->findCurrencyIdByCode($amount->getCurrencyCode());
      }
      catch (DataException $e) {
        throw new GenericException($this->getEntityType(), $this->getOdooModel(), $this->getExportType(), $order->id(), 'Error resolving currency.', $e);
      }

      try {
        $shipping_product_id = $this->carrierResolver->getShippingProductId($order, $order->shipments->entity);
        $shipping_product_name = $this->carrierResolver->getShippingProductName($order, $order->shipments->entity);
      }
      catch (DataException $e) {
        throw new GenericException($this->getEntityType(), $this->getOdooModel(), $this->getExportType(), $order->id(), 'Error resolving shipping method details.', $e);
      }

      // Shipping line.
      return [
        'order_id' => $this->getReferencedEntityOdooId('commerce_order', 'sale.order', 'default', $order->id()),
        'product_id' => $shipping_product_id,
        'name' => $shipping_product_name,
        'product_uom_qty' => 1,
        'discount' => 0,
        'price_unit' => $amount->getNumber(),
        'currency_id' => $currency_id,
        'is_delivery' => TRUE,
      ];
    }
    else {
      if (!$this->entityExported($order)) {
        throw new PluginLogicException($this->getEntityType(), $this->getOdooModel(), $this->getExportType(), $order->id(), NULL, '');
      }

      try {
        $currency_id = $this
          ->currencyResolver
          ->findCurrencyIdByCode($order->getTotalPrice()->getCurrencyCode());
      }
      catch (DataException $e) {
        throw new GenericException($this->getEntityType(), $this->getOdooModel(), $this->getExportType(), $order->id(), 'Error resolving currency.', $e);
      }

      // Unset existing shipping line.
      // We're not setting the product_id since we assume it's already set.
      return [
        'order_id' => $this->getReferencedEntityOdooId('commerce_order', 'sale.order', 'default', $order->id()),
        'name' => 'Cancelled shipping fee',
        'product_uom_qty' => 0,
        'discount' => 0,
        'price_unit' => 0,
        'currency_id' => $currency_id,
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function shouldSync(EntityInterface $order) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $new_or_existing_shipment =
      $this->hasShipment($order) || $this->entityExported($order);

    // Only export if there's a new or existing shipment.
    return $this->shouldExportOrder($order) && $new_or_existing_shipment;
  }

  /**
   * {@inheritdoc}
   */
  protected function shouldDelete(EntityInterface $entity) {
    // Shipment order items are never removed.
    // Instead, the amount is set to 0.
    return FALSE;
  }

  /**
   * Get promotions adjustments off the order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   Order entity.
   *
   * @return \Drupal\commerce_order\Adjustment[]
   *   Promotions adjustments.
   */
  protected function getOrderPromotionAdjustments(OrderInterface $order) {
    $adj = $order->collectAdjustments();
    return array_filter($adj, function ($adjustment) {
      if (!($adjustment instanceof Adjustment)) {
        return FALSE;
      }
      return $adjustment->getType() == 'promotion';
    });
  }

  /**
   * Checks if given order has shipment.
   *
   * @param \Drupal\Core\Entity\EntityInterface $order
   *   Order entity.
   *
   * @return bool
   *   Whether given order has shipment.
   */
  protected function hasShipment(EntityInterface $order) {
    return !empty($order->shipments->entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function getOrderId(EntityInterface $order) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    return $order->id();
  }

}
