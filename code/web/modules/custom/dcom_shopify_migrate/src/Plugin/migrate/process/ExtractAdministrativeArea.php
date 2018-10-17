<?php

namespace Drupal\dcom_shopify_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Custom process plugin for extracting a state.
 *
 * @code
 * process:
 *   name:
 *     plugin: dcom_shopify_extract_administrative_area
 *     source:
 *       - province_code
 *       - country_code
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "dcom_shopify_extract_administrative_area",
 *   handle_multiples = TRUE
 * )
 */
class ExtractAdministrativeArea extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (empty($value)) {
      return $value;
    }
    if (!is_array($value) || count($value) != 2) {
      throw new MigrateException('Country code and state are missing.');
    }

    $country_code = end($value);
    $state = reset($value);
    if ($country_code && $state) {

      switch ($country_code) {
        case 'JP':
          return preg_replace('/^(?:JP\-([0-9]{1,2})+)$/s', '${1}', $state, 1);

        case 'MX':
          $replace_pairs = [
            // MICH = Michoacán, Mexico.
            'MICH' => 'MIC',
            // BC = Baja California, Mexico.
            'BC' => 'BCN',
            // DF = Ciudad de México, Mexico.
            'DF' => 'DIF',
            // Q ROO = Quintana Roo, Mexico.
            'Q ROO' => 'ROO',
          ];
          if (in_array($state, array_keys($replace_pairs))) {
            $state = strtr($state, $replace_pairs);
          }

          return $state;

        case 'KR':
          // Do not save a state for Ireland for now.
          return NULL;
      }

      return $state;
    }
    return $state;
  }

}
