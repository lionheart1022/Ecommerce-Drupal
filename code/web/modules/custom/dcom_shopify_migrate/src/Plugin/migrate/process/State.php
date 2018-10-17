<?php

namespace Drupal\dcom_shopify_migrate\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use LogicException;

/**
 * Transforms Shopify order info to status.
 *
 * @code
 * process:
 *   state:
 *     plugin: shopify_order_state
 *     source:
 *       - fulfillment_status
 *       - shipping_lines
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "shopify_order_state",
 *   handle_multiples = TRUE
 * )
 */
class State extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (count($value) != 4) {
      // @TODO: error
      throw new LogicException('shopify_order_state process plugin requires exact 4 sources.');
    }

    list ($cancelled_at, $closed_at, $fulfillment_status, $financial_status) = $value;

    // Not even paid yet, so that's a new order.
    if ($financial_status == 'pending') {
      return 'draft';
    }

    // cancelled_at is not null or fulfillment status is restocked.
    // That means the order was cancelled.
    if (!empty($cancelled_at) || $fulfillment_status == 'restocked') {
      return 'canceled';
    }

    // closed_at is not null OR is fulfilled.
    // The order is complete.
    if (!empty($closed_at) || $fulfillment_status == 'fulfilled') {
      return 'completed';
    }

    // The order is not yet fulfilled. Awaiting for shipment or similar.
    if (in_array($fulfillment_status, [NULL, 'partial'])) {
      return 'fulfillment';
    }

    // Return draft by default.
    return 'draft';
  }

}
