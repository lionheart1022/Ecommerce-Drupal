<?php

namespace Drupal\address_helper_smartystreets\Data\US;

use CommerceGuys\Addressing\Address;
use Drupal\address_helper\Data\SuggestionItemBase;
use SmartyStreets\PhpSdk\US_Autocomplete\Suggestion as SmartyStreetsAutocompleteSuggestion;

/**
 * SmartyStreets US address autocomplete suggestion.
 *
 * This is a handy wrapper around SmartyStreet SDK class.
 */
class Suggestion extends SuggestionItemBase {

  /**
   * SmartyStreet SDK suggestion object.
   *
   * @var \SmartyStreets\PhpSdk\US_Autocomplete\Suggestion
   */
  protected $autocompleteSuggestion;

  /**
   * Suggestion item constructor.
   *
   * @param \SmartyStreets\PhpSdk\US_Autocomplete\Suggestion $suggestion
   *   SmartyStreet SDK suggestion object.
   */
  public function __construct(SmartyStreetsAutocompleteSuggestion $suggestion) {
    $this->autocompleteSuggestion = $suggestion;
  }

  /**
   * {@inheritdoc}
   */
  public function getSuggestionLabel() {
    return $this->autocompleteSuggestion->getText();
  }

  /**
   * {@inheritdoc}
   */
  public function getSecondaryLabel() {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getAddress() {
    if (!isset($this->address)) {
      $state = $this->autocompleteSuggestion->getState();
      $city = $this->autocompleteSuggestion->getCity();
      $street_line = $this->autocompleteSuggestion->getStreetLine();

      $this->address = (new Address('US', $state, $city))
        ->withAddressLine1($street_line);
    }

    return $this->address;
  }

}
