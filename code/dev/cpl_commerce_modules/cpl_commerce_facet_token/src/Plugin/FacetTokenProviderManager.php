<?php

namespace Drupal\cpl_commerce_facet_token\Plugin;

use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Provides the Facet-based tokens provider plugin manager.
 */
class FacetTokenProviderManager extends DefaultPluginManager {

  /**
   * {@inheritdoc}
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/FacetTokenProvider', $namespaces, $module_handler, 'Drupal\cpl_commerce_facet_token\Plugin\FacetTokenProviderInterface', 'Drupal\cpl_commerce_facet_token\Annotation\FacetTokenProvider');

    $this->alterInfo('cpl_commerce_facet_token_facet_token_provider_info');
    $this->setCacheBackend($cache_backend, 'cpl_commerce_facet_token_facet_token_provider_plugins');
  }

}
