<?php

namespace Drupal\commerce_affiliates\Plugin\Commerce\Affiliates;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginBase;

/**
 * Provides the base class for affiliates.
 */
abstract class AffiliateBase extends PluginBase implements AffiliateInterface {

  /**
   * The ID of the parent config entity.
   *
   * Not available while the plugin is being configured.
   *
   * @var string
   */
  protected $entityId;

  /**
   * A cache of instantiated store profiles.
   *
   * @var \Drupal\profile\Entity\ProfileInterface
   */
  protected $storeProfiles = [];

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    if (array_key_exists('_entity_id', $configuration)) {
      $this->entityId = $configuration['_entity_id'];
      unset($configuration['_entity_id']);
    }
    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  abstract public function defaultConfiguration();

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = NestedArray::mergeDeep($this->defaultConfiguration(), $configuration);
  }

  /**
   * {@inheritdoc}
   */
  abstract public function buildConfigurationForm(array $form, FormStateInterface $form_state);

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    if (!$form_state->getErrors()) {
      $this->configuration = [];
      foreach ($form_state->getValue($form['#parents']) as $key => $value) {
        $this->configuration[$key] = $value;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getTrackingType() {
    return (string) $this->pluginDefinition['trackingType'];
  }

  /**
   * {@inheritdoc}
   */
  abstract public function track(OrderInterface $order);

  /**
   * {@inheritdoc}
   */
  abstract public function cancelTransaction(OrderInterface $order, $event_type);

}
