<?php

namespace Drupal\commerce_affiliates\Entity;

use Drupal\commerce\CommerceSinglePluginCollection;
use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the affiliate type entity class.
 *
 * @ConfigEntityType(
 *   id = "commerce_affiliate_type",
 *   label = @Translation("Affiliate Type"),
 *   label_collection = @Translation("Affiliates"),
 *   label_singular = @Translation("affiliate type"),
 *   label_plural = @Translation("affiliate types"),
 *   label_count = @PluralTranslation(
 *     singular = "@count affiliate type",
 *     plural = "@count affiliate types",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\commerce_affiliates\AffiliatesListBuilder",
 *     "form" = {
 *       "add" = "Drupal\commerce_affiliates\Form\AffiliateTypeForm",
 *       "edit" = "Drupal\commerce_affiliates\Form\AffiliateTypeForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "default" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "commerce_affiliates",
 *   admin_permission = "administer commerce_affiliates",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "plugin",
 *     "configuration",
 *   },
 *   links = {
 *     "add-form" = "/admin/commerce/config/affiliates/add",
 *     "edit-form" = "/admin/commerce/config/affiliates/manage/{commerce_affiliate_type}",
 *     "delete-form" = "/admin/commerce/config/affiliates/manage/{commerce_affiliate_type}/delete",
 *     "collection" =  "/admin/commerce/config/affiliates"
 *   }
 * )
 */
class AffiliateType extends ConfigEntityBase implements AffiliateTypeInterface {

  /**
   * The affiliate type ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The affiliate type label.
   *
   * @var string
   */
  protected $label;

  /**
   * The plugin ID.
   *
   * @var string
   */
  protected $plugin;

  /**
   * The plugin configuration.
   *
   * @var array
   */
  protected $configuration = [];

  /**
   * The plugin collection that holds the affiliate type plugin.
   *
   * @var \Drupal\commerce\CommerceSinglePluginCollection
   */
  protected $pluginCollection;

  /**
   * {@inheritdoc}
   */
  public function getPlugin() {
    return $this->getPluginCollection()->get($this->plugin);
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginId() {
    return $this->plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function setPluginId($plugin_id) {
    $this->plugin = $plugin_id;
    $this->configuration = [];
    $this->pluginCollection = NULL;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setPluginConfiguration(array $configuration) {
    $this->configuration = $configuration;
    $this->pluginCollection = NULL;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginCollections() {
    return [
      'configuration' => $this->getPluginCollection(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function set($property_name, $value) {
    // Invoke the setters to clear related properties.
    if ($property_name == 'plugin') {
      $this->setPluginId($value);
    }
    elseif ($property_name == 'configuration') {
      $this->setPluginConfiguration($value);
    }
    else {
      return parent::set($property_name, $value);
    }
  }

  /**
   * Gets the plugin collection that holds the affiliate type plugin.
   *
   * Ensures the plugin collection is initialized before returning it.
   *
   * @return \Drupal\commerce\CommerceSinglePluginCollection
   *   The plugin collection.
   */
  protected function getPluginCollection() {
    if (!$this->pluginCollection) {
      $plugin_manager = \Drupal::service('plugin.manager.commerce_affiliates');
      $this->pluginCollection = new CommerceSinglePluginCollection($plugin_manager, $this->plugin, $this->configuration, $this->id);
    }
    return $this->pluginCollection;
  }

}
