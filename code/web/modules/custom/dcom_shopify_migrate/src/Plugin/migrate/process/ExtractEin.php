<?php

namespace Drupal\dcom_shopify_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Custom process plugin for extracting EIN number.
 *
 * @code
 * process:
 *   name:
 *     plugin: dcom_extract_ein
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "dcom_extract_ein"
 * )
 */
class ExtractEin extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $regex = '/ein[ a-zA-Z\_\:\- ]+([\w-]+\d)/i';
    if (preg_match($regex, $value, $matches)) {
      return $matches[1];
    }
  }

}
