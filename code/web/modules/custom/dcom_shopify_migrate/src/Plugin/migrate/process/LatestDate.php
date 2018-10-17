<?php

namespace Drupal\dcom_shopify_migrate\Plugin\migrate\process;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * Finds latest date from the list.
 *
 * @code
 * process:
 *   state:
 *     plugin: shopify_latest_date
 *     source:
 *       - created_at
 *       - processed_at
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "shopify_latest_date",
 *   handle_multiples = TRUE
 * )
 */
class LatestDate extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $max_date = NULL;

    foreach ($value as $date) {
      try {
        $datetime = DrupalDateTime::createFromFormat('Y-m-d\TH:i:sO', $date, NULL, ['validate_format' => FALSE])
          ->getTimestamp();
        if ($datetime > $max_date) {
          $max_date = $datetime;
        }
      }
      catch (\Exception $e) {
        continue;
      }
    }

    return $max_date;
  }

}
