<?php

namespace Drupal\address_helper_example\Plugin\AddressHelperAutocomplete;

use CommerceGuys\Addressing\Address;
use Drupal\address_helper\Data\StaticSuggestionItem;
use Drupal\address_helper\Data\StaticSuggestionsCollection;
use Drupal\address_helper\Plugin\AutocompleteServiceBase;

/**
 * Example suggestion plugin.
 *
 * @AddressHelperAutocomplete(
 *   id = "address_helper_example",
 *   label = @Translation("Suggestion test"),
 * )
 */
class StaticSuggestionExample extends AutocompleteServiceBase {

  /**
   * {@inheritdoc}
   */
  public function queryAddress($address_line, $country_code = NULL) {
    $suggestions = [];

    // Found at \CommerceGuys\Addressing\Tests\AddressTest.
    $suggestions[] = new StaticSuggestionItem(new Address('US', 'CA', 'Mountain View', 'MV', '94043', '94044', '1600 Amphitheatre Parkway', 'Google Bldg 41'));
    // The Simpsons House. Found at Wikipedia.
    $suggestions[] = new StaticSuggestionItem(new Address('US', 'NV', 'Henderson', '', '89011', '', '712 Red Bark Lane'), 'Simpsons House', 'Henderson NV');

    return new StaticSuggestionsCollection($suggestions);
  }

}
