<?php

namespace Drupal\dcom_odoo_entity_sync\Plugin\OdooEntitySync;

use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_price\Price;
use Drupal\Core\Entity\EntityInterface;
use Drupal\dcom_odoo_entity_sync\Util\MigratedEntityTrait;
use Drupal\dcom_odoo_entity_sync\Plugin\OdooEntitySync\Util\OrderItemSyncBase;
use Drupal\dcom_odoo_entity_sync\Util\OrderSyncTrait;
use Drupal\dcom_odoo_entity_sync\TaxResolverInterface;
use Drupal\odoo_api\OdooApi\ClientInterface;
use Drupal\odoo_api\OdooApi\Data\CurrencyResolverInterface;
use Drupal\odoo_api\OdooApi\Exception\DataException;
use Drupal\odoo_api_entity_sync\Exception\GenericException;
use Drupal\odoo_api_entity_sync\MappingManagerInterface;
use Drupal\odoo_api_entity_sync\SyncManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Orders sync.
 *
 * @OdooEntitySync(
 *   id = "dcom_odoo_entity_sync_order_item",
 *   entityType = "commerce_order_item",
 *   odooModel = "sale.order.line",
 * )
 */
class OrderItem extends OrderItemSyncBase {

  use MigratedEntityTrait;
  use OrderSyncTrait;

  const ODOO_DISCOUNT_TYPE_FIXED = 'fixed';

  /**
   * Currency resolver service.
   *
   * @var \Drupal\odoo_api\OdooApi\Data\CurrencyResolverInterface
   */
  protected $currencyResolver;

  /**
   * The tax resolver.
   *
   * @var \Drupal\dcom_odoo_entity_sync\TaxResolverInterface
   */
  protected $taxResolver;

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
    TaxResolverInterface $tax_resolver
  ) {
    $this->currencyResolver = $currency_resolver;
    $this->taxResolver = $tax_resolver;
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
      $container->get('dcom_odoo_entity_sync.tax_resolver')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getOdooFields(EntityInterface $order_item) {
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
    try {
      $currency_id = $this
        ->currencyResolver
        ->findCurrencyIdByCode($order_item->getUnitPrice()->getCurrencyCode());
    }
    catch (DataException $e) {
      throw new GenericException($this->getEntityType(), $this->getOdooModel(), $this->getExportType(), $order_item->id(), 'Error resolving currency.', $e);
    }
    list ($discount, $discount_type) = $this->getOrderItemDiscount($order_item);
    $fields = [
      'order_id' => $this->getReferencedEntityOdooId('commerce_order', 'sale.order', 'default', $order_item->getOrderId()),
      'product_id' => $this->getMigratedEntityOdooId($order_item->getPurchasedEntity(), 'product.product'),
      'name' => $order_item->label(),
      'product_uom_qty' => (float) $order_item->getQuantity(),
      'discount' => $discount,
      'discount_type' => $discount_type,
      'price_unit' => $order_item->getUnitPrice()->getNumber(),
      'currency_id' => $currency_id,
      'tax_id' => [[6, 0, $this->getOdooTaxIds($order_item)]],
    ];

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  protected function shouldSync(EntityInterface $order_item) {
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
    $order = $order_item->getOrder();
    return $order
      && $order_item->getPurchasedEntity()
      && $this->shouldExportOrder($order);
  }

  /**
   * {@inheritdoc}
   */
  protected function shouldDelete(EntityInterface $entity) {
    return FALSE;
  }

  /**
   * Gets Odoo tax IDs for the order item.
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $order_item
   *   The order item entity.
   *
   * @return array
   *   An array of tax IDs.
   */
  protected function getOdooTaxIds(OrderItemInterface $order_item) {
    $taxes_ids = [];

    foreach ($order_item->getAdjustments() as $adjustment) {
      if ($adjustment->getType() == 'tax') {
        $tax_percentage = $adjustment->getPercentage() * 100;
        $adjustment_tax_ids = $this->taxResolver->findOdooTaxIdsByPercentage($tax_percentage);
        $odoo_tax_id = $adjustment_tax_ids
          ? reset($adjustment_tax_ids)
          : $this->taxResolver->createOdooTax($tax_percentage);
        $taxes_ids[$odoo_tax_id] = $odoo_tax_id;
      }
    }

    return array_values($taxes_ids);
  }

  /**
   * Get order item discount fields.
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $order_item
   *   Order item object.
   *
   * @return array
   *   Array of two Odoo fields values: discount, discount_type.
   *
   * @throws \Drupal\odoo_api_entity_sync\Exception\GenericException
   *   Error fetching discounts.
   */
  protected function getOrderItemDiscount(OrderItemInterface $order_item) {
    $adjustments = $this->getPromotionAdjustments($order_item);

    // No adjustments means no discount.
    if (!$adjustments) {
      return [0, NULL];
    }

    $first_adjustment = reset($adjustments);
    $discount_amount = new Price(0, $first_adjustment->getAmount()->getCurrencyCode());

    // Sum all discounts.
    foreach ($adjustments as $adjustment) {
      /** @var \Drupal\commerce_price\Price $discount_amount */
      $discount_amount = $discount_amount->add($adjustment->getAmount());
    }

    return [
      $discount_amount
        ->multiply($order_item->getQuantity())
        ->multiply(-1)
        ->getNumber(),
      self::ODOO_DISCOUNT_TYPE_FIXED,
    ];
  }

  /**
   * Get all order item promotion adjustments.
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $order_item
   *   Order item object.
   *
   * @return \Drupal\commerce_order\Adjustment[]
   *   Array of adjustments.
   */
  protected function getPromotionAdjustments(OrderItemInterface $order_item) {
    return array_filter($order_item->getAdjustments(), function (Adjustment $adjustment) {
      return $adjustment->getType() == 'promotion';
    });
  }

  /**
   * {@inheritdoc}
   */
  protected function getOrderId(EntityInterface $order_item) {
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
    return $order_item->getOrderId();
  }

}
