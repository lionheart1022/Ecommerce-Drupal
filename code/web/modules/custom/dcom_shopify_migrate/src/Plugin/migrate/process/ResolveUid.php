<?php

namespace Drupal\dcom_shopify_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\migrate\process\MigrationLookup;
use Drupal\migrate\Row;

/**
 * Custom process plugin for mapping Shopify orders without customers.
 *
 * @code
 * process:
 *   name:
 *     plugin: dcom_shopify_resolve_uid
 *     source: customer
 *     order_id_source: order_id
 *
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "dcom_shopify_resolve_uid"
 * )
 */
class ResolveUid extends MigrationLookup {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (!empty($value)) {
      return parent::transform($value, $migrate_executable, $row, $destination_property);
    }

    $order_id_property = empty($this->configuration['order_id_source']) ? 'id' : $this->configuration['order_id_source'];
    if ($order_id = $row->getSourceProperty($order_id_property)) {
      // The following code will work for both id types:
      // billing_address_123 and just "123".
      $shopify_order_id = explode('_', $order_id);
      $shopify_order_id = end($shopify_order_id);
      $replace_pairs = [
        // Shopify order ID => Drupal user ID.
        // Email: roscop95@aol.com.
        217288081449 => 5828,
        // Email: garyjayqn@yahoo.com.
        468492484699 => 5408,
      ];
      if (in_array($shopify_order_id, array_keys($replace_pairs))) {
        return strtr($shopify_order_id, $replace_pairs);
      }
    }

    return FALSE;
  }

}
