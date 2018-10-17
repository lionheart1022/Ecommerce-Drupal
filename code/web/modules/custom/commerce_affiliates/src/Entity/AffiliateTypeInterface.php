<?php

namespace Drupal\commerce_affiliates\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;

/**
 * Defines the interface for affiliate types.
 *
 * This configuration entity stores configuration for affiliate plugins.
 */
interface AffiliateTypeInterface extends ConfigEntityInterface, EntityWithPluginCollectionInterface {

  /**
   * Gets the affiliate plugin.
   *
   * @return \Drupal\commerce_affiliates\Plugin\Commerce\Affiliates\AffiliateInterface
   *   The affiliate type plugin.
   */
  public function getPlugin();

  /**
   * Gets the affiliate plugin ID.
   *
   * @return string
   *   The affiliate plugin ID.
   */
  public function getPluginId();

  /**
   * Sets the affiliate plugin ID.
   *
   * @param string $plugin_id
   *   The affiliate plugin ID.
   *
   * @return $this
   */
  public function setPluginId($plugin_id);

  /**
   * Gets the affiliate plugin configuration.
   *
   * @return string
   *   The affiliate plugin configuration.
   */
  public function getPluginConfiguration();

  /**
   * Sets the affiliate plugin configuration.
   *
   * @param array $configuration
   *   The affiliate plugin configuration.
   *
   * @return $this
   */
  public function setPluginConfiguration(array $configuration);

}
