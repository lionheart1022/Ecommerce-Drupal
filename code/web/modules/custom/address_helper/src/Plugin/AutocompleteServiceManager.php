<?php

namespace Drupal\address_helper\Plugin;

use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Provides the Address suggestion service plugin manager.
 */
class AutocompleteServiceManager extends DefaultPluginManager implements AutocompleteServiceManagerInterface {

  /**
   * Constructs a new AddressHelperAutocompleteManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/AddressHelperAutocomplete', $namespaces, $module_handler, 'Drupal\address_helper\Plugin\AutocompleteServiceInterface', 'Drupal\address_helper\Annotation\AddressHelperAutocomplete');

    $this->alterInfo('address_helper_address_helper_service_info');
    $this->setCacheBackend($cache_backend, 'address_helper_address_helper_service_plugins');
  }

  /**
   * Get list of plugins available.
   *
   * @return array
   *   Array of ID => Label.
   */
  public function getOptionsList() {
    $options = [];
    foreach ($this->getDefinitions() as $id => $definition) {
      $options[$id] = $definition['label'];
    }
    return $options;
  }

}
