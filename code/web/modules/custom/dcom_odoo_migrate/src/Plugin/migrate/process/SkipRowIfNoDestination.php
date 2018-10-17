<?php

namespace Drupal\dcom_odoo_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Skips the row if there is no destination.
 *
 * @code
 * process:
 *   uid:
 *     plugin: skip_row_if_no_destination
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "skip_row_if_no_destination"
 * )
 */
class SkipRowIfNoDestination extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (empty($row->getIdMap()['destid1'])) {
      throw new MigrateSkipRowException();
    }

    return $value;
  }

}
