<?php

namespace Drupal\dcom_shopify_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Returns an order data friendly array.
 *
 * @code
 * process:
 *   some_field:
 *     plugin: dcom_shipping_lines_data
 *     source: shipping_lines
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "dcom_shipping_lines_data",
 *   handle_multiples = TRUE
 * )
 */
class DcomShippingLinesData extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    return ['shopify_shipping_lines' => $value];
  }

}
