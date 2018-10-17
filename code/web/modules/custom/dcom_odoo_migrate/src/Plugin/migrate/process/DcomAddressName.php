<?php

namespace Drupal\dcom_odoo_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Returns Odoo:user:name if a Odoo:other_address:name is empty.
 *
 * @code
 * process:
 *   address/0/given_name:
 *     plugin: dcom_address_name
 *     source:
 *       - name
 *       - parent_id
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "dcom_address_name",
 *   handle_multiples = TRUE
 * )
 */
class DcomAddressName extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (!is_array($value) || count($value) != 2) {
      throw new MigrateException('The dcom_address_name plugin is supposed to process the user address and the user name.');
    }

    $names = [reset($value)];
    $parents = end($value);

    if (!empty($parents) && is_array($parents)) {
      $parent = reset($parents);
      if (isset($parent['name'])) {
        $names[] = $parent['name'];
      }
    }

    $names = array_filter($names, function ($name) {
      // Skip spaces only.
      return !empty($name) && $name !== ' ';
    });

    return reset($names);
  }

}
