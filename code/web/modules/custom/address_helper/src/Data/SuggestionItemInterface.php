<?php

namespace Drupal\address_helper\Data;

/**
 * Single address suggestion item interface.
 */
interface SuggestionItemInterface {

  /**
   * Get address object.
   *
   * @return \CommerceGuys\Addressing\Address
   *   Address object.
   */
  public function getAddress();

  /**
   * Convert suggestion to array.
   *
   * @return array
   *   Associative array with the following elements:
   *   - label: visible suggestion text.
   *   - secondary_label: secondary suggestion text; may be NULL.
   *   - address: array of address fields values.
   */
  public function toArray();

  /**
   * Returns suggestion label/text.
   *
   * This is usually an address line.
   *
   * @return string
   *   Suggestion text.
   */
  public function getSuggestionLabel();

  /**
   * Returns secondary label/text.
   *
   * @return string|null
   *   Secondary suggestion text or NULL.
   */
  public function getSecondaryLabel();

}
