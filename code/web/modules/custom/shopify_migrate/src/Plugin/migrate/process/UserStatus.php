<?php

namespace Drupal\shopify_migrate\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * Transforms shopify customer's state to boolean.
 *
 * Enabled, invited => TRUE, other states => FALSE.
 *
 * @code
 * process:
 *   status:
 *     plugin: shopify_migrate_user_status
 *     source: state
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "shopify_migrate_user_status"
 * )
 */
class UserStatus extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    return $value == 'enabled' || $value == 'invited';
  }

}
