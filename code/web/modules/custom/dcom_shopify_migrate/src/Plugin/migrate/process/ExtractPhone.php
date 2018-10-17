<?php

namespace Drupal\dcom_shopify_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Custom process plugin for extracting the phone number.
 *
 * @code
 * process:
 *   name:
 *     plugin: dcom_extract_phone
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "dcom_extract_phone"
 * )
 */
class ExtractPhone extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $regex = '/Phone:\s+(?:.*?)([\d-\.\+\(\) ]+\d)/';
    if (preg_match($regex, $value, $matches)) {
      return $matches[1];
    }

    $phone = $row->getSourceProperty('default_address/phone');
    if (!empty($phone)) {
      return $phone;
    }

    return '000-000-0000';
  }

}
