<?php

namespace Drupal\cpl_commerce_facet_token\Plugin;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\cpl_commerce_shop\CollectionsUrlHelper;
use Drupal\facets\Exception\InvalidProcessorException;
use Drupal\facets\FacetManager\DefaultFacetManager;
use Drupal\facets\FacetSource\FacetSourcePluginManager;
use Drupal\facets\Result\ResultInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for Facet-based tokens provider plugins.
 */
abstract class FacetTokenProviderBase extends PluginBase implements FacetTokenProviderInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * Facet source plugin manager.
   *
   * @var \Drupal\facets\FacetSource\FacetSourcePluginManager
   */
  protected $facetSourcePluginManager;

  /**
   * Facets manager service.
   *
   * @var \Drupal\facets\FacetManager\DefaultFacetManager
   */
  protected $facetsManager;

  /**
   * Active facet results keyed by facet ID.
   *
   * @var array
   */
  protected $activeFacetResults;

  /**
   * Collections URL helper service.
   *
   * @var \Drupal\cpl_commerce_shop\CollectionsUrlHelper
   */
  protected $collectionsHelper;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FacetSourcePluginManager $facet_source_plugin_manager, DefaultFacetManager $facets_manager, CollectionsUrlHelper $collections_helper) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->facetSourcePluginManager = $facet_source_plugin_manager;
    $this->facetsManager = $facets_manager;
    $this->collectionsHelper = $collections_helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.facets.facet_source'),
      $container->get('facets.manager'),
      $container->get('cpl_commerce_shop.collections_url_helper')
    );
  }

  /**
   * Get active results for a given facet.
   *
   * @param string $facet_id
   *   Facet ID.
   *
   * @return \Drupal\facets\Result\ResultInterface[]|null
   *   Facets results array or NULL.
   */
  protected function getActiveFacetResults($facet_id) {
    $this->initActiveFacetResults();
    return isset($this->activeFacetResults[$facet_id]) ? $this->activeFacetResults[$facet_id] : NULL;
  }

  /**
   * Format multiple facet labels as a string.
   *
   * @param array $labels
   *   Facet labels.
   * @param bool $use_and
   *   Whether to use 'and' word before last word.
   *
   * @return string|null
   *   Formatted labels string or NULL if labels array is empty.
   */
  protected function formatMultipleLabels(array $labels, $use_and = TRUE) {
    if (count($labels) < 2) {
      return reset($labels);
    }

    if ($use_and) {
      $last_label = array_pop($labels);
      $vars = ['@items' => implode(', ', $labels), '@last' => $last_label];
      return $this->t('@items and @last', $vars);
    }

    return implode(', ', $labels);
  }

  /**
   * Get active collection title.
   *
   * @param bool $use_meta_title
   *   Whether field_meta_title should be used instead of term label.
   *
   * @return string|null
   *   Collection term title if the page is a collection front page.
   *   NULL otherwise.
   */
  protected function getActiveCollectionTitle($use_meta_title) {
    if ($this->getAllActiveFacetResultsCount() < 2
      && $collection = $this->collectionsHelper->getCollectionTermFromUrl()) {
      $title = $collection->label();
      if ($use_meta_title && !empty($collection->field_meta_title->value)) {
        $title = $collection->field_meta_title->value;
      }
      return html_entity_decode(strip_tags($title));
    }

    return NULL;
  }

  /**
   * Get active collection description.
   *
   * @param bool $use_meta_description
   *   Whether field_meta_description should be used instead of term
   *   description.
   *
   * @return string|null
   *   Collection term description if the page is a collection front page.
   *   NULL otherwise.
   */
  protected function getActiveCollectionDescription($use_meta_description) {
    if ($this->getAllActiveFacetResultsCount() < 2
      && $collection = $this->getCollectionTerm()) {

      $description = $collection->getDescription();

      if ($use_meta_description
        && !empty($collection->field_meta_description->value)) {
        $description = $collection->field_meta_description->value;
      }

      return html_entity_decode(strip_tags($description));
    }

    return NULL;
  }

  /**
   * Get active facet values labels string.
   *
   * @param string $facet_id
   *   Facet ID.
   * @param bool $use_and
   *   Whether to use 'and' word before last word.
   *
   * @return string|null
   *   Active facet values labels, e.g. '10mg, 20mg and 50mg' or NULL.
   */
  protected function getActiveFacetValuesString($facet_id, $use_and = TRUE) {
    return $this->formatMultipleLabels($this->getActiveFacetLabels($facet_id), $use_and);
  }

  /**
   * Get active facet values labels.
   *
   * @param string $facet_id
   *   Facet ID.
   *
   * @return array
   *   Array of labels.
   */
  protected function getActiveFacetLabels($facet_id) {
    $labels = [];
    if ($results = $this->getActiveFacetResults($facet_id)) {
      foreach ($results as $result) {
        $labels[] = $result->getDisplayValue();
      }
    }
    return $labels;
  }

  /**
   * Fetch active facet resluts.
   */
  protected function initActiveFacetResults() {
    if (!isset($this->activeFacetResults)) {
      $this->activeFacetResults = [];

      $facet_sources_definitions = $this->facetSourcePluginManager->getDefinitions();

      foreach ($facet_sources_definitions as $definition) {
        $facetsource_id = $definition['id'];
        $facets = $this->facetsManager->getFacetsByFacetSourceId($facetsource_id);

        foreach ($facets as $facet) {
          // Make sure facet is initialized first.
          try {
            $this->facetsManager->build($facet);
          }
          catch (InvalidProcessorException $e) {
            // Ignore any exceptions, we don't care about them.
          }

          $this->activeFacetResults[$facet->id()] = array_filter($facet->getResults(), function (ResultInterface $result) {
            return $result->isActive();
          });
        }
      }
    }
  }

  /**
   * Get count of all active facets.
   *
   * @return int
   *   Active facet values count.
   */
  protected function getAllActiveFacetResultsCount() {
    $this->initActiveFacetResults();

    $count = 0;
    foreach ($this->activeFacetResults as $results) {
      $count += count($results);
    }
    return $count;
  }

  /**
   * @return \Drupal\taxonomy\TermInterface
   */
  protected function getCollectionTerm() {
    $term = $this->collectionsHelper->getCollectionTermFromUrl();
    return $term;
  }

}
