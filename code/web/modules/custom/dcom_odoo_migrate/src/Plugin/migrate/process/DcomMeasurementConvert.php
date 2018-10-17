<?php

namespace Drupal\dcom_odoo_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Convert measurement.
 *
 * @code
 * process:
 *   field_product_volume/0/number:
 *     plugin: dcom_measurement_convert
 *     measurement_type: volume
 *     from_units: m3
 *     to_units: ml
 *     precision: 2
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "dcom_measurement_convert"
 * )
 */
class DcomMeasurementConvert extends ProcessPluginBase {

  /**
   * Measurement type mapping.
   *
   * @var array
   *   Callback function names keyed by measurement type.
   */
  protected static $measurementType = [
    'volume' => '\Drupal\physical\Volume',
    'weight' => '\Drupal\physical\Weight',
  ];

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (!isset($this->configuration['measurement_type'])
      || !isset($this->configuration['from_units'])
      || !isset($this->configuration['to_units'])
    ) {
      throw new MigrateException('Missing configuration for "dcom_measurement_convert" migration process plugin.');
    }

    if (!is_numeric($value)) {
      throw new MigrateException('Not numeric measurement value "' . $value . '"');
    }

    if ($measurement_processor = $this->getMeasurementProcessor($value)) {
      $precision = isset($this->configuration['precision']) ? $this->configuration['precision'] : 0;
      return $measurement_processor->convert($this->configuration['to_units'])
        ->round($precision)
        ->getNumber();
    }
    else {
      throw new MigrateSkipRowException("Couldn't find measurement converter for {$this->configuration['measurement_type']}");
    }
  }

  /**
   * Create Measurement object.
   *
   * @param float|string $value
   *   Value to convert.
   *
   * @return object|null
   *   Object that will convert measurement.
   */
  protected function getMeasurementProcessor($value) {
    if (isset(self::$measurementType[$this->configuration['measurement_type']])) {
      $class_name = self::$measurementType[$this->configuration['measurement_type']];
      if (class_exists($class_name)) {
        return new $class_name((string) $value, $this->configuration['from_units']);
      }
    }
    return NULL;
  }

}
