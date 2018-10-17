<?php

namespace Drupal\shopify_migrate\Plugin\migrate\process;

use Drupal\commerce_order\Adjustment;
use Drupal\commerce_price\Price;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use LogicException;

/**
 * Transforms Shopify order shipment and discounts to adjustments.
 *
 * @code
 * process:
 *   adjustments:
 *     plugin: shopify_adjustments
 *     source:
 *       - discount_codes
 *       - shipping_lines
 *       - currency
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "shopify_adjustments",
 *   handle_multiples = TRUE
 * )
 */
class Adjustments extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (count($value) != 3) {
      throw new LogicException('shopify_adjustments process plugin requires exact three sources.');
    }

    list ($discounts, $shippings, $currency_code) = $value;
    if (empty($discounts)) {
      $discounts = [];
    }
    if (empty($shippings)) {
      $shippings = [];
    }

    $adjustments = [];

    // Add discounts.
    foreach ($discounts as $discount) {
      $definition = [
        'type' => 'promotion',
        'label' => $discount->code,
        'amount' => new Price((string) ($discount->amount * -1), $currency_code),
      ];
      $adjustments[] = new Adjustment($definition);
    }

    // Add shippings.
    foreach ($shippings as $shipping) {
      $definition = [
        'type' => 'shipping',
        'label' => $shipping->title,
        'amount' => new Price($shipping->discounted_price, $currency_code),
      ];
      $adjustments[] = new Adjustment($definition);
    }

    return $adjustments;
  }

}
