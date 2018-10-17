<?php

namespace Drupal\dcom_odoo_entity_sync\Plugin\OdooEntitySync\Util;

use Drupal\commerce_price\Calculator;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\odoo_api\OdooApi\Exception\DataException;

/**
 * Trait ShopifyShipmentsTrait.
 *
 * Contains helper method to process shopify shipments.
 *
 * @package Drupal\dcom_odoo_entity_sync\Plugin\OdooEntitySync\Util
 */
trait ShopifyShipmentsTrait {

  /**
   * Returns Odoo carrier id based on the shopify shipment title.
   *
   * @param \stdClass $shopify_shipment
   *   The shopify shipment object.
   *
   * @return int
   *   The Odoo carrier ID.
   *
   * @throws \Drupal\odoo_api\OdooApi\Exception\DataException
   *   Throw an exceptions if carrier id is not found.
   */
  protected function getCarrierIdFromShopifyShipment(\stdClass $shopify_shipment) {
    $mapping = [
      // Map to Odoo: UPS Three-Day Select (ID 8).
      'UPS 3 Day Select' => 8,
      '3 Day Select' => 8,
      'UPS Three-Day Select' => 8,

      // Map to Odoo: UPS Ground (ID 2).
      'UPS Ground' => 2,
      'UPS Ground 2' => 2,
      'GROUND' => 2,
      'call in' => 2,
      'call in shipping' => 2,
      'call-in' => 2,
      'call-in shipping' => 2,
      'call-in shopping' => 2,
      'Custom' => 2,
      'over the phone' => 2,
      'over the phone fee' => 2,
      'OVERPHONE FEE' => 2,
      'phone' => 2,
      'phone fee' => 2,
      'phoneFEE' => 2,
      'shipping' => 2,
      'Standard - Free' => 2,
      'UPS Ground Insured (3-5 business days)' => 2,

      // Map to Odoo: UPS Second Day Air (ID 7).
      'UPS Next Day Air' => 7,
      'UPS Second Day Air A.M.' => 7,
      'UPS 2nd Day Air' => 7,
      '2nd Day Air' => 7,
      '2 - DAY SHIPPING' => 7,
      '2 DAY' => 7,
      '2-day' => 7,
      '2 day shipping' => 7,
      '2nd day' => 7,
      'EXPEDITE SHIPPING' => 7,
      'Two Day Shipping' => 7,
      'UPS 2 day' => 7,
      'UPS 2ND DAY' => 7,
      'UPS Second Day Air' => 7,

      // Map to Odoo: Fedex US (Ground).
      'FedEx Ground' => 20,
      'FedEx Ground Home Delivery' => 20,

      // Map to Odoo: Fedex International (ID 5).
      'FedEx International Priority' => 5,
      'FedEx International Economy' => 5,
      'First Class Package International' => 5,
      'Priority Mail Express International' => 5,
      'Priority Mail International' => 5,

      // Map to Odoo: UPS Overnight (ID 31).
      'UPS Next Day' => 31,
      'overnight and call in fee' => 31,
      'OVER NIGHT + CALL IN' => 31,
      'OVER NIGHT SHIPPING + CALL-IN FEE' => 31,
      'CALL IN AND OVERNIGHT' => 31,
      'call in and overnight!' => 31,
      'overnight' => 31,
      'over night' => 31,
      'overnight shipping' => 31,
      'shipping over night' => 31,
      'OVER NIGHT - SATURDAY' => 31,
      'OVER NIGHT SATURDAY' => 31,
      'OVERNIGHT, over night' => 31,
      'UPS Next Day Air Saver' => 31,

      // Map to Odoo: Fedex US (Standard-Overnight) (ID 19).
      'FedEx Priority Overnight' => 19,
      'FedEx Standard Overnight' => 19,
      'FedEx First Overnight' => 19,
      'FedEx OVERNIGHT' => 19,

      // Map to Odoo: Fedex US (2-Day) (ID 18).
      'FedEx 2 Day' => 18,
      'FedEx 2 Day Am' => 18,
      'FedEx Express Saver' => 18,
      'Fed Ex 2-day' => 18,

      // Map to Odoo: Free delivery charges (ID 1).
      'Free shipping' => 1,
      'FREE SHIPPING (7-10 days) to a shipping method' => 1,
      'FREE SHIPPING (7-10 days)' => 1,

      // Map to Odoo: USPS Domestic (Express) - Stamps.com (ID 10).
      'express' => 10,
      'Express shipping' => 10,
      'call-in and express' => 10,
      'call-in + express shipping' => 10,
      'EXPRESS SHIPPING + CALL-IN ORDER' => 10,
      'EXPRESS SHIPPING (3-5 days)' => 10,
      'USPS EXPRESS SHIPPING (3-5 days)' => 10,

      // Map to Odoo: USPS Domestic (Ground) - Stamps.com (ID 26).
      'USPS Standard - Free' => 26,
      'USPS Standard - Free (7 - 10 working days)' => 26,
      'USPS Standard (7 - 10 business days)' => 26,
      'USPS Standard Shipping' => 26,

      // Map to Odoo: USPS Domestic (Priority Mail) - Stamps.com (ID 27).
      'USPS Priority (5 - 7 business days)' => 27,
      'USPS PRIORITY SHIPPING (3-5 days)' => 27,

      // Map to Odoo: Flat rate (ID 4).
      'Standard' => 4,
      'Standard Shipping' => 4,
    ];

    $shopify_shipping_methods = array_keys($mapping);
    $search = array_search(strtolower($shopify_shipment->title), array_map('strtolower', $shopify_shipping_methods));
    if ($search !== FALSE) {
      $shopify_shipping_method = $shopify_shipping_methods[$search];
      return $mapping[$shopify_shipping_method];
    }

    $args = ['@method' => $shopify_shipment->title];
    $message = (string) (new FormattableMarkup('Could not get Odoo carrier ID shipping method for the shopify shipping method: @method.', $args));
    throw new DataException($message);
  }

  /**
   * Returns a delivery price of the shipment.
   *
   * @param \stdClass $shopify_shipment
   *   The shopify shipment object.
   *
   * @return float
   *   The delivery price.
   */
  protected function getDeliveryPriceFromShopifyShipment(\stdClass $shopify_shipment) {
    return (float) Calculator::round($shopify_shipment->discounted_price, 2);
  }

}
