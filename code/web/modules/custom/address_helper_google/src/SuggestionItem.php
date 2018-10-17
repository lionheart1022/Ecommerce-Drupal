<?php

namespace Drupal\address_helper_google;

use CommerceGuys\Addressing\Address;
use Drupal\address_helper\Data\SuggestionItemBase;

/**
 * Google Places suggestion item.
 */
class SuggestionItem extends SuggestionItemBase {

  /**
   * Google Places API autocomplete response.
   *
   * @var array
   */
  protected $suggestion;

  /**
   * Google Places API details response.
   *
   * @var array
   */
  protected $details;

  /**
   * SuggestionItem constructor.
   *
   * @param array $suggestion
   *   Google Places API autocomplete response.
   * @param array $details
   *   Google Places API details response.
   */
  public function __construct(array $suggestion, array $details) {
    $this->suggestion = $suggestion;
    $this->details = $details;
  }

  /**
   * {@inheritdoc}
   */
  public function getSuggestionLabel() {
    if (!empty($this->suggestion['structured_formatting']['main_text'])) {
      return $this->suggestion['structured_formatting']['main_text'];
    }

    if (!empty($this->suggestion['description'])) {
      return $this->suggestion['description'];
    }

    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getSecondaryLabel() {
    return !empty($this->suggestion['structured_formatting']['secondary_text'])
      ? $this->suggestion['structured_formatting']['secondary_text']
      : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getAddress() {
    if (!isset($this->address)) {
      $this->address = new Address(
        $this->getPlaceAddressComponent('country'),
        $this->getPlaceAddressComponent('administrative_area_level_1'),
        $this->getPlaceAddressComponent('locality'),
        '',
        $this->getPlaceAddressComponent('postal_code'),
        '',
        $this->getAddressLine()
      );
    }

    return $this->address;
  }

  /**
   * Extract address component from Places API results.
   *
   * @param string $type
   *   Address component type.
   * @param bool $long
   *   Whether a long name should be returned.
   * @param mixed $default
   *   Default value returned if component is not found.
   *
   * @return string|mixed
   *   Address component or default value, if not found.
   */
  protected function getPlaceAddressComponent($type, $long = FALSE, $default = '') {
    if (empty($this->details['result']['address_components'])) {
      return '';
    }

    foreach ($this->details['result']['address_components'] as $component) {
      if (empty($component['types'])) {
        // Empty address.
        return $default;
      }
      if (in_array($type, $component['types'])) {
        if ($long && !empty($component['long_name'])) {
          return $component['long_name'];
        }
        return !empty($component['short_name']) ? $component['short_name'] : '';
      }
    }

    // Component not found.
    return $default;
  }

  /**
   * Get address street line.
   *
   * @param string $default
   *   Default value to return if street line is not found.
   *
   * @return string|mixed
   *   Street line or default value.
   */
  protected function getAddressLine($default = '') {
    if (($street_number = $this->getPlaceAddressComponent('street_number'))
      && ($route = $this->getPlaceAddressComponent('route'))) {
      return $street_number . ' ' . $route;
    }

    return $default;
  }

}
