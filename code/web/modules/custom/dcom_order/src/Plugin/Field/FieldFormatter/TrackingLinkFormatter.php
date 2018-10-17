<?php

namespace Drupal\dcom_order\Plugin\Field\FieldFormatter;

use Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\SupportsTrackingInterface;
use Drupal\commerce_shipping\Plugin\Field\FieldFormatter\TrackingLinkFormatter as TrackingLinkFormatterBase;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin implementation of the 'commerce_tracking_link' formatter.
 *
 * @FieldFormatter(
 *   id = "dcom_commerce_tracking_link",
 *   label = @Translation("Dcom tracking link"),
 *   field_types = {
 *     "string"
 *   }
 * )
 */
class TrackingLinkFormatter extends TrackingLinkFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    if ($items->isEmpty()) {
      return [];
    }
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    $shipment = $items[0]->getEntity();
    $shipping_method = $shipment->getShippingMethod();
    if (!$shipping_method) {
      return [];
    }
    /** @var \Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\SupportsTrackingInterface $shipping_method_plugin */
    $shipping_method_plugin = $shipment->getShippingMethod()->getPlugin();
    if (!($shipping_method_plugin instanceof SupportsTrackingInterface)) {
      return [];
    }
    $tracking_url = $shipping_method_plugin->getTrackingUrl($shipment);
    if (!$tracking_url) {
      return [];
    }

    $attributes = $tracking_url->getOption('attributes') ?: [];
    $attributes['target'] = '_blank';
    $tracking_url->setOption('attributes', $attributes);
    $elements = [];
    $elements[] = [
      '#type' => 'link',
      '#title' => $shipment->getTrackingCode(),
      '#url' => $tracking_url,
    ];

    return $elements;
  }

}
