<?php

namespace Drupal\commerce_affiliates;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Manages discovery and instantiation of affiliates plugins.
 *
 * @see \Drupal\commerce_affiliates\Annotation\CommerceAffiliate
 * @see plugin_api
 */
class CommerceAffiliatesPluginManager extends DefaultPluginManager {

  /**
   * Default values for each affiliate type plugin.
   *
   * @var array
   */
  protected $defaults = [
    'id' => '',
    'label' => '',
  ];

  /**
   * Constructs a new CommerceAffiliatesPluginManager object.
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
    parent::__construct(
      'Plugin/Commerce/Affiliates',
      $namespaces,
      $module_handler,
      'Drupal\commerce_affiliates\Plugin\Commerce\Affiliates\AffiliateInterface',
      'Drupal\commerce_affiliates\Annotation\CommerceAffiliate'
    );

    $this->alterInfo('commerce_affiliates_info');
    $this->setCacheBackend($cache_backend, 'commerce_affiliates_plugins');
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition(&$definition, $plugin_id) {
    parent::processDefinition($definition, $plugin_id);

    foreach (['id', 'label'] as $required_property) {
      if (empty($definition[$required_property])) {
        throw new PluginException(sprintf('The affiliate type %s must define the %s property.', $plugin_id, $required_property));
      }
    }
  }

}
