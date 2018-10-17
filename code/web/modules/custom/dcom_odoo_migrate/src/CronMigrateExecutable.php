<?php

namespace Drupal\dcom_odoo_migrate;

use Drupal\migrate_tools\MigrateExecutable;
use Drupal\odoo_api_migrate\OdooCronMigrationSourceInterface;

/**
 * Cron Migrate executable.
 */
class CronMigrateExecutable extends MigrateExecutable {

  const CRON_RUN_ROWS_LIMIT = 100;

  /**
   * {@inheritdoc}
   */
  protected function getSource() {
    // Set Cron mode if source plugin supports it.
    $source = parent::getSource();
    if ($source instanceof OdooCronMigrationSourceInterface) {
      $source->setCronMode(TRUE);
    }
    return $source;
  }

  /**
   * Set migration fetch limit.
   *
   * @param int $limit
   *   New items limit.
   */
  public function setLimit($limit) {
    $this->itemLimit = $limit;
  }

}
