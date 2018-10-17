<?php

namespace Drupal\dcom_shopify_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateException;
use Drupal\migrate_plus\Plugin\migrate\process\SkipOnValue;

/**
 * If the source evaluates to a configured value, skip processing or whole row.
 *
 * @code
 * process:
 *   some_field:
 *     plugin: dcom_skip_on_value
 *     method: row
 *     source: order_id
 *     operator: '<'
 *     value: 3
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "dcom_skip_on_value"
 * )
 */
class DcomSkipOnValue extends SkipOnValue {

  /**
   * {@inheritdoc}
   */
  protected function compareValue($value, $skipValue, $equal = TRUE) {
    if (empty($this->configuration['operator'])) {
      return parent::compareValue($value, $skipValue, $equal);
    }
    else {
      if (!is_numeric($value) || !is_numeric($skipValue)) {
        throw new MigrateException('To compare numeric values the values must be numberic.');
      }

      switch ($this->configuration['operator']) {
        case '<':
          return $value < $skipValue;

        case '<=':
          return $value <= $skipValue;

        case '>':
          return $value > $skipValue;

        case '>=':
          return $value >= $skipValue;
      }

      throw new MigrateException('Unsupported operator ' . $this->configuration['operator'] . '.');
    }
  }

}
