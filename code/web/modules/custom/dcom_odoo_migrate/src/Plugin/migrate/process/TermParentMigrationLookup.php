<?php

namespace Drupal\dcom_odoo_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateSkipProcessException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\migrate\process\MigrationLookup;
use Drupal\migrate\Row;

/**
 * This plugin figures out taxonomy term parent IDs.
 *
 * @MigrateProcessPlugin(
 *   id = "term_parent_migration_lookup"
 * )
 */
class TermParentMigrationLookup extends MigrationLookup {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    try {
      $value = parent::transform($value, $migrate_executable, $row, $destination_property);
    }
    catch (MigrateSkipProcessException $e) {
      // Reset parent.
      $value = 0;
    }
    return $value;
  }

}
