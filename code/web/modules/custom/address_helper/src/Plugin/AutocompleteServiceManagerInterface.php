<?php

namespace Drupal\address_helper\Plugin;

use Drupal\Component\Plugin\PluginManagerInterface;

/**
 * Address suggestion service plugin manager interface.
 */
interface AutocompleteServiceManagerInterface extends PluginManagerInterface {

  /**
   * Get list of plugins available.
   *
   * @return array
   *   Array of ID => Label.
   */
  public function getOptionsList();

}
