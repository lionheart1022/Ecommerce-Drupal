<?php

namespace Drupal\address_helper_smartystreets\Plugin\AddressHelperAutocomplete;

use Drupal\address_helper\Data\StaticSuggestionsCollection;
use Drupal\address_helper\Exception\ServiceException;
use Drupal\address_helper\Plugin\AutocompleteServiceBase;
use Drupal\address_helper_smartystreets\Data\US\Suggestion;
use SmartyStreets\PhpSdk\ClientBuilder;
use SmartyStreets\PhpSdk\StaticCredentials;
use SmartyStreets\PhpSdk\US_Autocomplete\Lookup as AutocompleteLookup;

/**
 * SmartyStreets suggestion plugin.
 *
 * @AddressHelperAutocomplete(
 *   id = "smartystreets_us",
 *   label = @Translation("SmartyStreets US"),
 * )
 */
class SmartyStreetsUS extends AutocompleteServiceBase {

  /**
   * {@inheritdoc}
   */
  public function queryAddress($address_line, $country_code = NULL) {
    if ($country_code != 'US') {
      throw new ServiceException('Only US addresses are supported.');
    }

    $suggestions = [];

    $lookup = new AutocompleteLookup();
    $lookup->setPrefix($address_line);

    try {
      $this->autocompleteApiClient()->sendLookup($lookup);

      /** @var \SmartyStreets\PhpSdk\US_Autocomplete\Suggestion $autocomplete_result */
      $autocomplete_result = $lookup->getResult();

      foreach ($autocomplete_result as $row) {
        $suggestions[] = new Suggestion($row);
      }
    }
    catch (\Exception $e) {
      throw new ServiceException($e->getMessage());
    }

    return new StaticSuggestionsCollection($suggestions);
  }

  /**
   * Get autocomplete API client.
   *
   * @return \SmartyStreets\PhpSdk\US_Autocomplete\Client
   *   Autocomplete API client.
   */
  protected function autocompleteApiClient() {
    return $this->clientBuilder()->buildUSAutocompleteApiClient();
  }

  /**
   * Get API client builder.
   *
   * @return \SmartyStreets\PhpSdk\ClientBuilder
   *   Client builder
   */
  protected function clientBuilder() {
    // @TODO: Configurable credentials.
    $auth_id = 'abe95651-9e9a-9ff1-2691-6f89d427c7b6';
    $token = 'PzBspd1xGmk8pYppDazm';

    return new ClientBuilder(new StaticCredentials($auth_id, $token));
  }

}
