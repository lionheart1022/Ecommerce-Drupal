<?php

namespace Drupal\shopify_migrate\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * Simple basename process plugin.
 *
 * @code
 * process:
 *   filename:
 *     plugin: file_basename
 *     source: uri
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "file_basename"
 * )
 */
class FileBasename extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    // Try trimming GET parameters, if any.
    if ($filename = parse_url($value, PHP_URL_PATH)) {
      $value = $filename;
    }

    return basename($value);
  }

}
