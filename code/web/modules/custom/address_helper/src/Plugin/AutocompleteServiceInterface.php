<?php

namespace Drupal\address_helper\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Address suggestion service plugin interface.
 */
interface AutocompleteServiceInterface extends PluginInspectionInterface {

  /**
   * Get address suggestion.
   *
   * @param string $address_line
   *   Address line input.
   * @param string $country_code
   *   Country code.
   *
   * @return \Drupal\address_helper\Data\SuggestionsCollectionInterface
   *   Suggestions collection object.
   *
   * @throws \Drupal\address_helper\Exception\ServiceException
   *   Third-party service exception.
   */
  public function queryAddress($address_line, $country_code = NULL);

}
