<?php

namespace Drupal\shopify_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateException;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * Transforms Shopify product variation option to product attribute.
 *
 * @code
 * process:
 *   some_attribute_field:
 *     plugin: shopify_product_variant_option_lookup
 *     option_name: Flavor
 *     source:
 *       - options
 *       - option1
 *       - option2
 *       - option3
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "shopify_product_variant_option_lookup",
 *   handle_multiples = TRUE
 * )
 */
class ProductVariantOptionLookup extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (!isset($this->configuration['option_name'])) {
      throw new MigrateException('Missing option_name configuration.');
    }

    $options = array_shift($value);

    if (empty($options) || !is_array($options)) {
      throw new MigrateException('Missing options source.');
    }

    foreach ($options as $option) {
      if ($option->name == $this->configuration['option_name']) {
        $position = $option->position;
        // Array indexing starts with 0. So 1 is 0, 2 is 1, 3 is 2 and so on.
        $position--;
        $source_option = $value[$position] ?: NULL;

        if ($source_option && in_array($source_option, $option->values)) {
          return $source_option;
        }
        break;
      }
    }

    return FALSE;
  }

}
