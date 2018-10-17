<?php

namespace Drupal\commerce_affiliates\Plugin\Commerce\Affiliates;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Component\Plugin\ConfigurablePluginInterface;
use Drupal\Component\Plugin\DerivativeInspectionInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Defines the interface for affiliate.
 */
interface AffiliateInterface extends ConfigurablePluginInterface, PluginFormInterface, PluginInspectionInterface, DerivativeInspectionInterface {

  /**
   * Gets the affiliate type label.
   *
   * @return string
   *   The affiliate type label.
   */
  public function getLabel();

  /**
   * Gets the affiliate "tracking type" ("html", "api", etc).
   *
   * @return string
   *   The affiliate "tracking type".
   */
  public function getTrackingType();

  /**
   * Send a request to the affiliate service.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   */
  public function track(OrderInterface $order);

  /**
   * Cancel tracking if order was canceled.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param string $event_type
   *   Could be "order_canceled" or "order_refunded".
   */
  public function cancelTransaction(OrderInterface $order, $event_type);

}
