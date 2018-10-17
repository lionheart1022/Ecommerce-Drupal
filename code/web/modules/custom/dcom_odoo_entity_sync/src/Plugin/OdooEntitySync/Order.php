<?php

namespace Drupal\dcom_odoo_entity_sync\Plugin\OdooEntitySync;

use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_price\Calculator;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\dcom_odoo_entity_sync\CarrierResolverInterface;
use Drupal\dcom_odoo_entity_sync\Plugin\OdooEntitySync\Util\ShopifyShipmentsTrait;
use Drupal\dcom_odoo_entity_sync\SalesChannelResolverInterface;
use Drupal\dcom_odoo_entity_sync\Util\OrderSyncTrait;
use Drupal\dcom_odoo_entity_sync\Util\WholesaleSyncTrait;
use Drupal\odoo_api\OdooApi\ClientInterface;
use Drupal\odoo_api\OdooApi\Data\CurrencyResolverInterface;
use Drupal\odoo_api\OdooApi\Exception\DataException;
use Drupal\odoo_api_entity_sync\Exception\GenericException;
use Drupal\odoo_api_entity_sync\MappingManagerInterface;
use Drupal\odoo_api_entity_sync\Plugin\EntitySyncBase;
use Drupal\odoo_api_entity_sync\SyncManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Orders sync.
 *
 * @OdooEntitySync(
 *   id = "dcom_odoo_entity_sync_order",
 *   entityType = "commerce_order",
 *   odooModel = "sale.order",
 * )
 */
class Order extends EntitySyncBase {

  use OrderSyncTrait;
  use ShopifyShipmentsTrait;

  const ODOO_DISCOUNT_TYPE_FIXED = 'fixed';

  /**
   * Currency resolver service.
   *
   * @var \Drupal\odoo_api\OdooApi\Data\CurrencyResolverInterface
   */
  protected $currencyResolver;

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Carrier resolver service.
   *
   * @var \Drupal\dcom_odoo_entity_sync\CarrierResolverInterface
   */
  protected $carrierResolver;

  /**
   * Sales channel resolver service.
   *
   * @var \Drupal\dcom_odoo_entity_sync\SalesChannelResolverInterface
   */
  protected $salesChannelResolver;

  /**
   * The commerce_payment entity storage.
   *
   * @var \Drupal\commerce_payment\PaymentStorageInterface
   */
  protected $paymentStorage;

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
    DateFormatterInterface $date_formatter,
    CarrierResolverInterface $carrier_resolver,
    SalesChannelResolverInterface $sales_channel_resolver,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->currencyResolver = $currency_resolver;
    $this->dateFormatter = $date_formatter;
    $this->carrierResolver = $carrier_resolver;
    $this->salesChannelResolver = $sales_channel_resolver;
    $this->paymentStorage = $entity_type_manager->getStorage('commerce_payment');
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
      $container->get('date.formatter'),
      $container->get('dcom_odoo_entity_sync.carrier_resolver'),
      $container->get('dcom_odoo_entity_sync.sales_channel_resolver'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getOdooFields(EntityInterface $order) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $created_date = $this->dateFormatter
      ->format($order->getCreatedTime(), 'custom', ClientInterface::ODOO_DATETIME_FORMAT, 'UTC');

    list ($discount, $discount_type) = $this->getOrderDiscount($order);

    $fields = [
      // Diamond CBD - 	https://www.diamondcbd.com.
      'company_id' => 1,
      'name' => $order->getOrderNumber(),
      'partner_id' => $this->getReferencedEntityOdooId('user', 'res.partner', 'default', $order->getCustomerId()),
      // TODO in odoo we should know from what channel order came. From new orders from drupal.
      'date_order' => $created_date,
      'create_date' => $created_date,
      // The date when Odoo:order:status moved from draft or sent to sale.
      'confirmation_date' => $this->dateFormatter
        ->format($order->getPlacedTime(), 'custom', ClientInterface::ODOO_DATETIME_FORMAT, 'UTC'),
      // TODO Handle the case if Drupal:state && Odoo:state = completed. Then the order is locked on Odoo and can not be completed.
      // TODO Order items: It is forbidden to modify the following fields in a locked order: Description Product Quantity.
      'state' => strtr($order->getState()->value, [
        'fulfillment' => 'sale',
        'completed' => 'done',
        'canceled' => 'cancel',
      ]),
      'global_order_discount' => $discount,
      'global_discount_type' => $discount_type,
      // Sales channel: Drupal Website.
      'team_id' => $this->salesChannelResolver->resolveByOrder($order),
      'x_order_type' => $this->orderIsWholesale($order) ? 'wholesale' : 'retail',
      // Pricelist wholesale - 2, retail - 1.
      'pricelist_id' => $this->orderIsWholesale($order) ? 2 : 1,
    ];

    // Changing order state if not completed payment exists.
    $payments = $this->paymentStorage->loadMultipleByOrder($order);
    foreach ($payments as $payment) {
      if (!$payment->isCompleted()) {
        $fields['state'] = 'draft';
      }
    }

    if (!$this->entityExported($order)) {
      // Salesperson: Drupal API; email is noreply@diamondcbd.com.
      $fields['user_id'] = 7;
    }

    if ($order->hasField('field_order_source') && !$order->get('field_order_source')->isEmpty()) {
      $order_source = $order->get('field_order_source')->first()->getValue()['value'];

      if ($order_source == 'shopify_retail') {
        $fields['x_cancel_transfers'] = TRUE;
      }
    };

    if (!($total_price = $order->getTotalPrice())) {
      throw new GenericException($this->getEntityType(), $this->getOdooModel(), $this->getExportType(), $order->id(), 'Error getting order total.');
    }
    try {
      $fields['currency_id'] = $this->currencyResolver->findCurrencyIdByCode($total_price
        ->getCurrencyCode());
    }
    catch (DataException $e) {
      throw new GenericException($this->getEntityType(), $this->getOdooModel(), $this->getExportType(), $order->id(), 'Error resolving currency.', $e);
    }

    if ($billing = $order->getBillingProfile()) {
      $fields['partner_invoice_id'] = $this->getReferencedEntityOdooId('profile', 'res.partner', 'default', $billing->id());
    }

    if ($order->hasField('shipments') && !$order->get('shipments')->isEmpty()) {
      /** @var \Drupal\Core\Field\EntityReferenceFieldItemList $shipments */
      $shipments = $order->get('shipments');
      $shipments = $shipments->referencedEntities();
      /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
      if ($shipment = reset($shipments)) {
        $shipment_amount = $shipment->getAmount()->getNumber();
        $fields['delivery_price'] = (float) Calculator::round($shipment_amount, 2);
        try {
          $fields['carrier_id'] = $this->carrierResolver->getOdooCarrierId($order, $shipment);
        }
        catch (DataException $e) {
          throw new GenericException($this->getEntityType(), $this->getOdooModel(), $this->getExportType(), $order->id(), 'Error resolving carrier ID.', $e);
        }

        if ($shipping_profile = $shipment->getShippingProfile()) {
          $fields['partner_shipping_id'] = $this->getReferencedEntityOdooId('profile', 'res.partner', 'default', $shipping_profile->id());
        }
      }
    }

    $this->processShopifyOrder($fields, $order);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  protected function shouldSync(EntityInterface $entity) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $entity;
    return $this->shouldExportOrder($order);
  }

  /**
   * {@inheritdoc}
   */
  protected function shouldDelete(EntityInterface $entity) {
    return FALSE;
  }

  /**
   * Get order discount fields.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   Order object.
   *
   * @return array
   *   Array of two Odoo fields values: global_order_discount,
   *   global_discount_type.
   *
   * @throws \Drupal\odoo_api_entity_sync\Exception\GenericException
   *   Error fetching discounts.
   */
  protected function getOrderDiscount(OrderInterface $order) {
    $adjustments = $this->getPromotionAdjustments($order);

    // No adjustments means no discount.
    if (!$adjustments) {
      return [0, NULL];
    }

    if (count($adjustments) > 1) {
      $sum = 0;
      foreach ($adjustments as $adjustment) {
        if ($adjustment->getType() != 'promotion') {
          throw new GenericException($this->getEntityType(), $this->getOdooModel(), $this->getExportType(), $order->id(), 'Multiple discount types are not supported.');
        }
        $sum += $adjustment->getAmount()->getNumber() * -1;
      }
      return [
        $sum,
        self::ODOO_DISCOUNT_TYPE_FIXED,
      ];
    }

    $adjustment = reset($adjustments);
    return [
      $adjustment->getAmount()->getNumber() * -1,
      self::ODOO_DISCOUNT_TYPE_FIXED,
    ];
  }

  /**
   * Get all order promotion adjustments.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   Order object.
   *
   * @return \Drupal\commerce_order\Adjustment[]
   *   Array of adjustments.
   */
  protected function getPromotionAdjustments(OrderInterface $order) {
    return array_filter($order->getAdjustments(), function (Adjustment $adjustment) {
      return $adjustment->getType() == 'promotion';
    });
  }

  /**
   * Processes shopify order.
   *
   * @param array $fields
   *   An array of fields to send to Odoo.
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order entity.
   *
   * @throws \Drupal\odoo_api_entity_sync\Exception\GenericException
   *   Thrown if Odoo carrier ID is not found.
   */
  protected function processShopifyOrder(array &$fields, OrderInterface $order) {
    if ($order->hasField('field_order_source') && !$order->get('field_order_source')->isEmpty()) {
      $order_source = $order->get('field_order_source')->first()->getValue()['value'];

      if ($order_source == 'shopify_wholesale') {
        $fields['x_cancel_transfers'] = TRUE;
      }
    };

    $no_drupal_shipments = empty($fields['delivery_price']) && empty($fields['carrier_id']);
    if ($no_drupal_shipments && ($shopify_shipments = $order->getData('shopify_shipping_lines'))) {
      /** @var \stdClass $shopify_shipment */
      $shopify_shipment = reset($shopify_shipments);
      $fields['delivery_price'] = $this->getDeliveryPriceFromShopifyShipment($shopify_shipment);

      try {
        $fields['carrier_id'] = $this->getCarrierIdFromShopifyShipment($shopify_shipment);
      }
      catch (DataException $e) {
        throw new GenericException($this->getEntityType(), $this->getOdooModel(), $this->getExportType(), $order->id(), 'Error resolving carrier ID.', $e);
      }
    }
  }

}
