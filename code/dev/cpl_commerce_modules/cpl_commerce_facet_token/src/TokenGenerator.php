<?php

namespace Drupal\cpl_commerce_facet_token;

use Drupal\cpl_commerce_facet_token\Plugin\FacetTokenProviderManager;
use Drupal\facets\FacetSource\FacetSourcePluginManager;
use Drupal\facets\UrlProcessor\UrlProcessorPluginManager;
use Drupal\facets\FacetManager\DefaultFacetManager;
use Drupal\token\TokenInterface;
use Drupal\cpl_commerce_shop\CollectionsUrlHelper;

/**
 * Class TokenGenerator.
 */
class TokenGenerator implements TokenGeneratorInterface {

  /**
   * Facet token provider plugin manager.
   *
   * @var \Drupal\cpl_commerce_facet_token\Plugin\FacetTokenProviderManager
   */
  protected $tokenProivderPluginManager;

  /**
   * Facet source plugin manager.
   *
   * @var \Drupal\facets\FacetSource\FacetSourcePluginManager
   */
  protected $facetSourcePluginManager;

  /**
   * Facet URL processor plugin manager.
   *
   * @var \Drupal\facets\UrlProcessor\UrlProcessorPluginManager
   */
  protected $facetUrlProcessorPluginManager;

  /**
   * Facet manager service.
   *
   * @var \Drupal\facets\FacetManager\DefaultFacetManager
   */
  protected $facetsManager;

  /**
   * Token service.
   *
   * @var \Drupal\token\TokenInterface
   */
  protected $token;

  /**
   * Collections URL helper service.
   *
   * @var \Drupal\cpl_commerce_shop\CollectionsUrlHelper
   */
  protected $collectionsHelper;

  /**
   * Tokens values cache.
   *
   * @var array
   */
  protected $tokensCache;

  /**
   * Constructs a new TokenGenerator object.
   */
  public function __construct(FacetTokenProviderManager $plugin_manager_facet_token_provider, FacetSourcePluginManager $plugin_manager_facets_facet_source, UrlProcessorPluginManager $plugin_manager_facets_url_processor, DefaultFacetManager $facets_manager, TokenInterface $token, CollectionsUrlHelper $cpl_commerce_shop_collections_url_helper) {
    $this->tokenProivderPluginManager = $plugin_manager_facet_token_provider;
    $this->facetSourcePluginManager = $plugin_manager_facets_facet_source;
    $this->facetUrlProcessorPluginManager = $plugin_manager_facets_url_processor;
    $this->facetsManager = $facets_manager;
    $this->token = $token;
    $this->collectionsHelper = $cpl_commerce_shop_collections_url_helper;
  }

  /**
   * {@inheritdoc}
   */
  public function getTokenValue($name) {
    if (isset($this->tokensCache[$name])) {
      return $this->tokensCache[$name];
    }

    switch ($name) {
      case TokenGeneratorInterface::TOKEN_TITLE:
        $this->tokensCache[$name] = $this->getTokenProvider()->getTitleToken(TRUE);
        return $this->tokensCache[$name];

      case TokenGeneratorInterface::TOKEN_TERM_TITLE:
        $this->tokensCache[$name] = $this->getTokenProvider()->getTitleToken(FALSE);
        return $this->tokensCache[$name];

      case TokenGeneratorInterface::TOKEN_DESCRIPTION:
        $this->tokensCache[$name] = $this->getTokenProvider()->getDescriptionToken(FALSE);
        return $this->tokensCache[$name];

      case TokenGeneratorInterface::TOKEN_TERM_DESCRIPTION:
        $this->tokensCache[$name] = $this->getTokenProvider()->getDescriptionToken(TRUE);
        return $this->tokensCache[$name];
    }

    throw new \InvalidArgumentException('Unknown token type ' . $name);
  }

  /**
   * {@inheritdoc}
   */
  public function getImageToken($name, $original) {
    if (!isset($this->tokensCache[$name])) {
      $this->tokensCache[$name] = $this->getTokenProvider()->getImageToken($original);
    }

    return $this->tokensCache[$name];
  }

  /**
   * Get facet token provider plugin instance.
   *
   * @return \Drupal\cpl_commerce_facet_token\Plugin\FacetTokenProviderInterface
   *   Facet token provider plugin instance
   */
  protected function getTokenProvider() {
    // @TODO: Fix me. Add configuration and error handling.
    $definitions = $this->tokenProivderPluginManager->getDefinitions();
    $first = reset($definitions);
    return $this->tokenProivderPluginManager->createInstance($first['id']);
  }

}
