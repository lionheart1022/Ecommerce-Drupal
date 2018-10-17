<?php

namespace Drupal\dcom_odoo_entity_sync\Plugin\OdooEntitySync;

use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_promotion\Entity\Coupon;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\dcom_odoo_entity_sync\Plugin\OdooEntitySync\Util\OrderItemSyncBase;
use Drupal\dcom_odoo_entity_sync\Util\OrderSyncTrait;
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
 *   id = "dcom_odoo_entity_sync_order_discount",
 *   entityType = "commerce_order",
 *   odooModel = "sale.order.line",
 *   exportType = "discount_line",
 * )
 */
class DiscountOrderItem extends OrderItemSyncBase {

  use OrderSyncTrait;

  /**
   * ID of special promotion product in Odoo.
   */
  const ODOO_PROMOTION_PRODUCT_ID = 4410;

  /**
   * Currency resolver service.
   *
   * @var \Drupal\odoo_api\OdooApi\Data\CurrencyResolverInterface
   */
  protected $currencyResolver;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

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
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->currencyResolver = $currency_resolver;
    $this->entityTypeManager = $entity_type_manager;
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
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getOdooFields(EntityInterface $order) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */

    if ($total = $this->getTotalDiscountAmount($order)) {
      try {
        $currency_id = $this
          ->currencyResolver
          ->findCurrencyIdByCode($total->getCurrencyCode());
      }
      catch (DataException $e) {
        throw new GenericException($this->getEntityType(), $this->getOdooModel(), $this->getExportType(), $order->id(), 'Error resolving currency.', $e);
      }

      /** @var \Drupal\commerce_price\Price $total */
      $fields = [
        'order_id' => $this->getReferencedEntityOdooId('commerce_order', 'sale.order', 'default', $order->id()),
        'product_id' => static::ODOO_PROMOTION_PRODUCT_ID,
        'name' => implode(PHP_EOL, $this->getDiscountDescriptionLines($order)),
        'product_uom_qty' => 1,
        'discount' => 0,
        'price_unit' => 0,
        'currency_id' => $currency_id,
      ];
    }
    else {
      try {
        $currency_id = $this
          ->currencyResolver
          ->findCurrencyIdByCode($order->getTotalPrice()->getCurrencyCode());
      }
      catch (DataException $e) {
        throw new GenericException($this->getEntityType(), $this->getOdooModel(), $this->getExportType(), $order->id(), 'Error resolving currency.', $e);
      }

      $fields = [
        'order_id' => $this->getReferencedEntityOdooId('commerce_order', 'sale.order', 'default', $order->id()),
        'product_id' => static::ODOO_PROMOTION_PRODUCT_ID,
        'name' => 'Cancelled discount.',
        'product_uom_qty' => 0,
        'discount' => 0,
        'price_unit' => 0,
        'currency_id' => $currency_id,
      ];
    }

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  protected function shouldSync(EntityInterface $order) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $new_or_existing_discount =
      $this->getTotalDiscountAmount($order)
      || $this->entityExported($order);

    // Only export if there's a new or existing discount.
    return $this->shouldExportOrder($order) && $new_or_existing_discount;
  }

  /**
   * {@inheritdoc}
   */
  protected function shouldDelete(EntityInterface $entity) {
    // Discount order items are never removed.
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
   * Get list of adjustments, grouped by coupon.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   Order entity.
   *
   * @return array
   *   Adjustments list.
   */
  protected function groupAdjustmentsByCoupon(OrderInterface $order) {
    $ret = [];
    $promotion_adjustments = $this->getOrderPromotionAdjustments($order);

    // Map adjustments to coupons.
    foreach ($order->get('coupons')->referencedEntities() as $coupon) {
      if (!($coupon instanceof Coupon)) {
        // @TODO: log error.
        continue;
      }
      $line = [
        'coupon_id' => $coupon->id(),
        'code' => $coupon->getCode(),
        'promotion_id' => [],
        'amount' => NULL,
      ];
      foreach ($promotion_adjustments as $index => $adjustment) {
        if ($adjustment->getSourceId() != $coupon->getPromotionId()) {
          continue;
        }
        if (empty($line['amount'])) {
          $line['amount'] = $adjustment->getAmount();
        }
        else {
          $line['amount'] = $adjustment->getAmount()->add($line['amount']);
        }
        $line['promotion_id'][] = $adjustment->getSourceId();
        // Do not process this adjustment anymore.
        unset($promotion_adjustments[$index]);
      }

      // Only add coupon if there is a corresponding adjustment.
      if (!empty($line['amount'])) {
        $ret[] = $line;
      }
    }

    // Add adjustments without coupons, if any.
    if (!empty($promotion_adjustments)) {
      foreach ($promotion_adjustments as $adjustment) {
        $ret[] = [
          'coupon_id' => NULL,
          'code' => NULL,
          'promotion_id' => [$adjustment->getSourceId()],
          'amount' => $adjustment->getAmount(),
          'promotion_name' => $this->getPromotionName($adjustment->getSourceId()),
        ];
      }
    }

    return $ret;
  }

  /**
   * Get promotion name.
   *
   * @param int $id
   *   Promotion ID.
   *
   * @return string|null
   *   Promotion name or NULL if no promotion found.
   */
  protected function getPromotionName($id) {
    try {
      $promotion = $this
        ->entityTypeManager
        ->getStorage('commerce_promotion')
        ->load($id);
      if ($promotion) {
        return $promotion->getName();
      }
    }
    catch (InvalidPluginDefinitionException $e) {
      return NULL;
    }

    return NULL;
  }

  /**
   * Get total discount amount.
   *
   * @param \Drupal\Core\Entity\EntityInterface $order
   *   Order entity.
   *
   * @return \Drupal\commerce_price\Price|null
   *   Total discount amount or NULL.
   */
  protected function getTotalDiscountAmount(EntityInterface $order) {
    $total = NULL;
    foreach ($this->groupAdjustmentsByCoupon($order) as $line) {
      $total = isset($total) ? $total->add($line['amount']) : $line['amount'];
    }
    return $total;
  }

  /**
   * Get description lines for discount item.
   *
   * @param \Drupal\Core\Entity\EntityInterface $order
   *   Order entity.
   *
   * @return array
   *   Array of lines to be used as order item description.
   */
  protected function getDiscountDescriptionLines(EntityInterface $order) {
    $description_lines = [
      'Promotions/discounts data below. DO NOT EDIT.',
      '',
    ];
    foreach ($this->groupAdjustmentsByCoupon($order) as $line) {
      $parts = [
        !empty($line['code']) ? 'COUPON' : 'PROMOTION',
        !empty($line['code']) ? $line['code'] : $line['promotion_name'],
        $line['amount']->getNumber(),
        $line['amount']->getCurrencyCode(),
      ];
      $description_lines[] = Json::encode($parts);
    }
    return $description_lines;
  }

  /**
   * {@inheritdoc}
   */
  protected function getOrderId(EntityInterface $order) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    return $order->id();
  }

}
