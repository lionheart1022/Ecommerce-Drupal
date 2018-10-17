<?php

namespace Drupal\address_helper\Data;

/**
 * Suggestions collection interface.
 */
interface SuggestionsCollectionInterface {

  /**
   * Get suggestions.
   *
   * @return \Drupal\address_helper\Data\SuggestionItemInterface[]
   *   Suggestions lines.
   *
   * @throws \Drupal\address_helper\Exception\NoSuchAddressException
   *   Missing suggestions.
   */
  public function getSuggestions();

}
