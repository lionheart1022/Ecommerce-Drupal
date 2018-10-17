<?php

namespace Drupal\address_helper_google\Plugin\AddressHelperAutocomplete;

use Drupal\address_helper\Data\StaticSuggestionsCollection;
use Drupal\address_helper\Plugin\AutocompleteServiceBase;
use Drupal\address_helper_google\SuggestionItem;
use SKAgarwal\GoogleApi\PlacesApi;

/**
 * Example suggestion plugin.
 *
 * @AddressHelperAutocomplete(
 *   id = "google_places",
 *   label = @Translation("Google Places"),
 * )
 */
class GooglePlaces extends AutocompleteServiceBase {

  /**
   * {@inheritdoc}
   */
  public function queryAddress($address_line, $country_code = NULL) {
    $api = $this->apiClient();
    $params = [
      'types' => 'address',
    ];
    if ($country_code) {
      $params['components'] = 'country:' . $country_code;
    }
    $response = $api->placeAutocomplete($address_line, $params)->toArray();

    $suggestions = [];
    if (!empty($response['predictions'])) {
      foreach ($response['predictions'] as $row) {
        if (empty($row['types'])
          || !in_array('street_address', $row['types'])) {
          // Skip if prediction type is not street_address.
          continue;
        }
        if (empty($row['place_id'])) {
          continue;
        }
        $suggestions[] = new SuggestionItem($row, $api->placeDetails($row['place_id'])->toArray());
      }
    }

    return new StaticSuggestionsCollection($suggestions);
  }

  /**
   * Get Google Places API client.
   *
   * @return \SKAgarwal\GoogleApi\PlacesApi
   *   Google Places API client.
   */
  protected function apiClient() {
    // @TODO: credentials.
    return new PlacesApi('AIzaSyBsufG2U5J581fwcMu3dCSfjr38BsAlVpE');
  }

}
