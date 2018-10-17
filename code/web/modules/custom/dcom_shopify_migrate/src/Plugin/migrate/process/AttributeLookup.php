<?php

namespace Drupal\dcom_shopify_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateException;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * DiamondCBD product variation attributes lookup.
 *
 * Tries guessing product attributes like weight or size from title and/or tags.
 *
 * @code
 * process:
 *   some_attribute_field:
 *     plugin: shopify_attribute_lookup
 *     attrbiute: volume_value
 *     source:
 *       - title
 *       - tags
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "shopify_attribute_lookup",
 *   handle_multiples = TRUE
 * )
 */
class AttributeLookup extends ProcessPluginBase {

  /**
   * Tag type mapping.
   *
   * @var array
   *   Callback function names keyed by tag type.
   */
  protected static $tagTypes = [
    'strength_value' => 'processMgValue',
    'volume_value' => 'processMlValue',
    'pack_value' => 'processPackValue',
  ];

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (!isset($this->configuration['attribute'])) {
      throw new MigrateException('Missing attrbiute configuration.');
    }

    list ($title, $tags) = $value;
    $tag_type = $this->configuration['attribute'];
    $filtered_title = TagsFilter::filterValues($tag_type, $title);

    // Try extracting from title.
    if (!empty($filtered_title)) {
      return reset($filtered_title);
    }

    $filtered_tags = TagsFilter::filterValues($tag_type, $tags);
    // Only one value found, that's success.
    if (count($filtered_tags) == 1) {
      return reset($filtered_tags);
    }

    return FALSE;
  }

}
