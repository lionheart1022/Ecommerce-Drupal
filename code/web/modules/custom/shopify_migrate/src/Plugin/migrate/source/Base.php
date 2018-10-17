<?php

namespace Drupal\shopify_migrate\Plugin\migrate\source;

use Drupal\migrate\MigrateException;
use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Shopify\PrivateApp;

/**
 * Abstract Shopify product base plugin.
 */
abstract class Base extends SourcePluginBase {

  /**
   * API client storage.
   *
   * @var \Shopify\PrivateApp
   *   API client object.
   */
  private $apiClient;

  /**
   * Shopify API pager limit.
   *
   * @var int
   */
  protected $pagerLimit = 50;

  /**
   * {@inheritdoc}
   */
  public function __toString() {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'id' => [
        'type' => 'integer',
        'size' => 'big',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function initializeIterator() {
    foreach ($this->getApiClient()->getResourcePager($this->getShopifyResource(), $this->pagerLimit, ['query' => $this->getQueryOptions()]) as $object) {
      yield (array) $object;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function doCount() {
    return $this->getApiClient()->getResourceCount($this->getShopifyResource(), $this->getQueryOptions());
  }

  /**
   * Initializes Shopify API client.
   *
   * @return \Shopify\PrivateApp
   *   Shopify API client.
   *
   * @throws \Drupal\migrate\MigrateException
   *   Missing required configuration.
   */
  protected function getApiClient() {
    if (!isset($this->apiClient)) {
      $required_fields = [
        'shop_domain',
        'api_key',
        'password',
        'shared_secret',
      ];
      foreach ($required_fields as $field) {
        if (empty($this->configuration['shopify'][$field])) {
          throw new MigrateException('Missing required Shopify Migration source plugin configuration item: ' . $field);
        }
      }

      $this->apiClient = new PrivateApp($this->configuration['shopify']['shop_domain'], $this->configuration['shopify']['api_key'], $this->configuration['shopify']['password'], $this->configuration['shopify']['shared_secret']);
    }

    return $this->apiClient;
  }

  /**
   * Provides Shopify resource name, e.g. 'products'.
   *
   * @return string
   *   Shopify API resource name.
   */
  abstract protected function getShopifyResource();

  /**
   * Get query options.
   *
   * @return array
   *   Shopify query options.
   */
  protected function getQueryOptions() {
    return [];
  }

}
