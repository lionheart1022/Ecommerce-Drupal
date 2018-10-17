<?php

namespace Drupal\dcom_shopify_migrate\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * Provides a 'AttachString' migrate process plugin.
 *
 * The attach_string plugin is used to attach a string to the input. For
 * example, prefixing "Description: " to a text field.
 *
 * Available configuration keys:
 * - string: The string to attach.
 * - location: (optional) The location where the string should be attached,
 *   either at the beginning, "prefix", or at the end, "suffix". If not
 *   specified, the string will be prefixed.
 *
 * Examples:
 *
 * @code
 * process:
 *   new_text_field:
 *     plugin: attach_string
 *     source: field_old
 *     string: 'Foo: '
 *     location: prefix
 * @endcode
 *
 * This will set new_text_field to the field_old value prefixed with "Foo: ". So
 * if the value of that field is "Bar", the result will be "Foo: Bar".
 *
 * @see \Drupal\migrate\Plugin\MigrateProcessInterface
 *
 * @MigrateProcessPlugin(
 *  id = "attach_string"
 * )
 */
class AttachString extends ProcessPluginBase {

  const LOCATIONS = [
    'prefix',
    'suffix',
  ];

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (is_string($value)) {
      $string = $this->getString();

      if ($this->getLocation() == $this->getAllPossibleLocations()[0]) {
        return $string . $value;
      }
      return $value . $string;
    }
    throw new MigrateException(sprintf('%s is not a string', var_export($value, TRUE)));
  }

  /**
   * Fetches the string to attach from the configuration.
   *
   * @return string
   *   The string to attach.
   */
  protected function getString() {
    return isset($this->configuration['string']) ? $this->configuration['string'] : '';
  }

  /**
   * Fetches the location from the configuration.
   *
   * @return string
   *   The location string.
   */
  protected function getLocation() {
    if (!empty($this->configuration['location']) && in_array($this->configuration['location'], static::LOCATIONS)) {
      return $this->configuration['location'];
    }
    return $this->getAllPossibleLocations()[0];
  }

  /**
   * Fetches a list of all possible locations for adding the string.
   *
   * @return array
   *   The list of locations where the string can be added.
   */
  protected function getAllPossibleLocations() {
    return static::LOCATIONS;
  }

}
