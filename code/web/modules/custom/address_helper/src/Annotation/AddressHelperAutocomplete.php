<?php

namespace Drupal\address_helper\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Address suggestion service item annotation object.
 *
 * @see \Drupal\address_helper\Plugin\AutocompleteServiceManager
 * @see plugin_api
 *
 * @Annotation
 */
class AddressHelperAutocomplete extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The label of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

}
