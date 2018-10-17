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
 *     plugin: skip_row_if_multiple_values_array
 *     source: picking_ids
 *     message: 'Something is missing'
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "skip_row_if_multiple_values_array",
 *   handle_multiples = TRUE
 * )
 */
class SkipRowIfMultipleValuesArray extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (!is_array($value)) {
      throw new MigrateException('Input should be an array.');
    }

    if (count($value) > 1) {
      $message = !empty($this->configuration['message']) ? $this->configuration['message'] : '';
      throw new MigrateSkipRowException($message);
    }

    return $value;
  }

}
