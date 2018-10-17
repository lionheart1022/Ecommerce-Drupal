<?php

namespace Drupal\dcom_odoo_entity_sync;

use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Entity\EntityInterface;
use Drupal\odoo_api\OdooApi\ClientInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\odoo_api\OdooApi\Exception\DataException;
use Drupal\odoo_api\OdooApi\Util\ResponseCacheTrait;

/**
 * Shipping method/carrier resolver service.
 */
class CarrierResolver implements CarrierResolverInterface {

  use ResponseCacheTrait;

  /**
   * Drupal\odoo_api\OdooApi\ClientInterface definition.
   *
   * @var \Drupal\odoo_api\OdooApi\ClientInterface
   */
  protected $odooApiApiClient;

  /**
   * Constructs a new CarrierResolver object.
   */
  public function __construct(ClientInterface $odoo_api_api_client, CacheBackendInterface $cache_default, CacheTagsInvalidatorInterface $cache_tags_invalidator) {
    $this->odooApiApiClient = $odoo_api_api_client;
    $this->setCacheOptions($cache_default, $cache_tags_invalidator, 'dcom_odoo_entity_sync.carrier_resolver');
  }

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
  public function getOdooCarrierId(EntityInterface $order, ShipmentInterface $shipment) {
    $shipping_method = $shipment->getShippingMethod();
    if ($shipping_method->getPlugin()->getPluginId() == 'ups') {
      $service = $shipment->getShippingService();
      $ups_service_mapping = [
        // UPS Second Day Air.
        '02' => 7,
        // UPS Three-Day Select.
        '12' => 8,
      ];
      if (isset($ups_service_mapping[$service])) {
        return $ups_service_mapping[$service];
      }

      $message = (string) (new FormattableMarkup('Could not get Odoo carrier ID for UPS shipping service @code.', ['@code' => $service]));
      throw new DataException($message);
    }

    $shipping_method_id = $shipping_method->id();
    $mapping = [
      // Drupal:FedEx => Odoo FedEx.
      6 => 6,
      // Drupal:UPS Flat rate => UPS US (UPS Ground).
      2 => 2,
      // Drupal:UPS Flat rate free => UPS US (UPS Ground).
      1 => 2,
      // Drupal:UPS Free shipping (only for backend use) => UPS US (UPS Ground).
      7 => 2,
      // Drupal:USPS Flat rate => USPS Domestic (Ground) - Stamps.com.
      8 => 26,
      // Drupal:USPS Flat rate free => USPS Domestic (Ground) - Stamps.com.
      9 => 26,
      // Drupal:USPS Free shipping (only for backend use) => USPS Domestic (Ground) - Stamps.com.
      10 => 26,
      // Drupal:UPS => Odoo UPS.
      4 => 2,
    ];

    if (isset($mapping[$shipping_method_id])) {
      return $mapping[$shipping_method_id];
    }

    $args = [
      '@method' => $shipping_method->getPlugin()->getPluginId(),
      '@code' => $shipment->getShippingService(),
    ];
    $message = (string) (new FormattableMarkup('Could not get Odoo carrier ID shipping method @method, service @code.', $args));
    throw new DataException($message);
  }

  /**
   * {@inheritdoc}
   */
  public function getShippingProductId(EntityInterface $order, ShipmentInterface $shipment) {
    $odoo_carrier_id = $this->getOdooCarrierId($order, $shipment);
    $map = $this->getAllShippingProducts();
    if (!isset($map[$odoo_carrier_id]['product_id'][0])) {
      throw new DataException((string) new FormattableMarkup('Could not find Odoo shipping product "@code".', ['@code' => $odoo_carrier_id]));
    }
    return $map[$odoo_carrier_id]['product_id'][0];
  }

  /**
   * {@inheritdoc}
   */
  public function getShippingProductName(EntityInterface $order, ShipmentInterface $shipment) {
    $odoo_carrier_id = $this->getOdooCarrierId($order, $shipment);
    $map = $this->getAllShippingProducts();
    if (!isset($map[$odoo_carrier_id]['product_id'][1])) {
      throw new DataException((string) new FormattableMarkup('Could not find Odoo shipping product "@code".', ['@code' => $odoo_carrier_id]));
    }
    return $map[$odoo_carrier_id]['product_id'][1];
  }

  /**
   * {@inheritdoc}
   */
  public function isDeliveryProduct($odoo_product_id) {
    $map = $this->getAllShippingProducts();

    foreach ($map as $carrier) {
      if (!empty($carrier['product_id'][0]) && $carrier['product_id'][0] == $odoo_product_id) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getAllShippingProducts() {
    return $this->cacheResponse('all_shipping_products', function () {
      $data = [];
      $fields = [
        'id',
        'product_id',
      ];
      foreach ($this->odooApiApiClient->searchRead('delivery.carrier', [], $fields) as $carrier) {
        $data[$carrier['id']] = $carrier;
      }
      return $data;
    });
  }

}
