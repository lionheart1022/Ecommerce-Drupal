<?php

namespace Drupal\dcom_shopify_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\migrate\process\MigrationLookup;
use Drupal\migrate\Row;

/**
 * Custom process plugin for mapping Shopify orders without billing addresses.
 *
 * @code
 * process:
 *   name:
 *     plugin: dcom_shopify_resolve_billing_profile
 *     source: billing_address
 *     migration: my_migration
 *     order_id_source: my_migration
 *
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "dcom_shopify_resolve_billing_profile"
 * )
 */
class ResolveBillingProfile extends MigrationLookup {

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
        // Shopify order ID => Drupal profile ID.
        468492484699 => 52010,
      ];

      if (!empty($replace_pairs[$shopify_order_id])) {
        /** @var \Drupal\profile\Entity\ProfileInterface $profile */
        $profile = \Drupal::entityTypeManager()
          ->getStorage('profile')
          ->load($replace_pairs[$shopify_order_id]);

        if ($profile) {
          return [$profile->id(), $profile->getRevisionId()];
        }
      }
    }

    return FALSE;
  }

}
