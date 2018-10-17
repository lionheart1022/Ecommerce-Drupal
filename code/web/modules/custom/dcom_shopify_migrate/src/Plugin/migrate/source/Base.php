<?php

namespace Drupal\dcom_shopify_migrate\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;

/**
 * Abstract Shopify product base plugin.
 */
abstract class Base extends SourcePluginBase {

  protected $filenames;

  /**
   * {@inheritdoc}
   */
  public function __toString() {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'id' => [
        'type' => 'integer',
        'size' => 'big',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function initializeIterator() {
    foreach ($this->getJsonFiles() as $filename) {
      foreach ($this->loadJsonFile($filename) as $row) {
        yield (array) $row;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function doCount() {
    $files = $this->getJsonFiles();
    if (count($files) == 0) {
      return 0;
    }

    return (count($files) - 1) * 250 + count($this->loadJsonFile(end($files)));
  }

  /**
   * Returns a mask for the file_scan_directory() function.
   *
   * @return string
   *   The mask for the file_scan_directory() function.
   */
  protected function getFileScanDirectoryMask() {
    return '/^page_\d+\.json$/';
  }

  /**
   * Get list of JSON files.
   *
   * @return array
   *   List of JSON files URIs.
   */
  protected function getJsonFiles() {
    if (!isset($this->filenames)) {
      $this->filenames = [];
      $dirname = 'private://shopify_data/' . $this->getShopifyResource();
      foreach (file_scan_directory($dirname, $this->getFileScanDirectoryMask()) as $file) {
        $this->filenames[] = $file->uri;
      }
      usort($this->filenames, '\Drupal\dcom_shopify_migrate\Plugin\migrate\source\Base::comparePageFileNames');
    }

    return $this->filenames;
  }

  /**
   * Load JSON from file.
   *
   * @param string $filename
   *   Filename.
   *
   * @return mixed
   *   JSON data.
   */
  protected function loadJsonFile($filename) {
    // Using json_decode here since Shopify API client did so.
    return json_decode(file_get_contents($filename));
  }

  /**
   * Sort callback for page JSONs file names.
   *
   * @param string $uri_a
   *   Filename to compare.
   * @param string $uri_b
   *   Filename to compare.
   *
   * @return int
   *   usort() callback return value.
   */
  public static function comparePageFileNames($uri_a, $uri_b) {
    $a = static::getPageNum($uri_a);
    $b = static::getPageNum($uri_b);

    if ($a == $b) {
      return 0;
    }
    return ($a < $b) ? -1 : 1;
  }

  /**
   * Get page number from page file path/URI.
   *
   * @param string $uri
   *   JSON page file path.
   *
   * @return int|null
   *   Page number or NULL.
   */
  public static function getPageNum($uri) {
    if (!preg_match('/\/page_(\d+)\.json$/', $uri, $matches)) {
      return NULL;
    }

    return $matches[1];
  }

  /**
   * Provides Shopify resource name, e.g. 'products'.
   *
   * @return string
   *   Shopify API resource name.
   */
  abstract protected function getShopifyResource();

}
