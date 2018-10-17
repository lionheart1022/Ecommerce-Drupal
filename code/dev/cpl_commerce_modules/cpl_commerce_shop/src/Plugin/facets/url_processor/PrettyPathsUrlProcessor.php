<?php

namespace Drupal\cpl_commerce_shop\Plugin\facets\url_processor;

use Drupal\commerce_product\Entity\ProductAttributeValue;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Http\Exception\CacheableNotFoundHttpException;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\cpl_commerce_shop\CollectionsUrlHelper;
use Drupal\facets\Entity\Facet;
use Drupal\facets_pretty_paths\Coder\CoderPluginManager;
use Drupal\facets_pretty_paths\Plugin\facets\url_processor\FacetsPrettyPathsUrlProcessor;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Routing\RouteProviderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Pretty paths URL processor.
 *
 * @FacetsUrlProcessor(
 *   id = "cpl_commerce_facets_pretty_paths",
 *   label = @Translation("CPL Commerce - Pretty paths"),
 *   description = @Translation("Pretty paths uses slashes as separator, e.g. /edibles/brand/drupal/color/blue"),
 * )
 */
class PrettyPathsUrlProcessor extends FacetsPrettyPathsUrlProcessor implements ContainerFactoryPluginInterface {

  /**
   * Router service.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $router;

  /**
   * Collections URL parser service.
   *
   * @var \Drupal\cpl_commerce_shop\CollectionsUrlHelper
   */
  protected $collectionsUrlParser;

  /**
   * URL validation cache.
   *
   * @var bool
   */
  protected $urlValid;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Request $request, EntityTypeManagerInterface $entity_type_manager, RouteMatchInterface $route_match, CoderPluginManager $coder_plugin_manager, RouteProviderInterface $router, CollectionsUrlHelper $url_parser) {
    $this->router = $router;
    $this->collectionsUrlParser = $url_parser;
    parent::__construct($configuration, $plugin_id, $plugin_definition, $request, $entity_type_manager, $route_match, $coder_plugin_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('request_stack')->getMasterRequest(),
      $container->get('entity_type.manager'),
      $container->get('current_route_match'),
      $container->get('plugin.manager.facets_pretty_paths.coder'),
      $container->get('router.route_provider'),
      $container->get('cpl_commerce_shop.collections_url_helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function buildPrettyFacetUrl($base_path, array $filters_current_result) {
    foreach ($this->router->getRoutesByPattern($base_path)->getIterator() as $route) {
      $facet_query = $this->buildFacetPathString($filters_current_result);
      $url = Url::fromRoute($route)
        ->setRouteParameter('facets_query', ltrim($facet_query, '/'));
      $get_params = clone $this->request->query;
      if ($get_params->has('page')) {
        $get_params->remove('page');
      }
      $url->setOption('query', $get_params->all());
      return $url;
    }

    // Fall back to default behavior if route didn't match.
    return parent::buildPrettyFacetUrl($base_path, $filters_current_result);
  }

  /**
   * {@inheritdoc}
   */
  protected function buildFacetPathString(array $filters_current_result) {
    $collections_path = $this->getCollectionsFacetPathString($filters_current_result);
    $other_facets = parent::buildFacetPathString($filters_current_result);
    return $collections_path . $other_facets;
  }

  /**
   * Generate Collections facets URL part.
   *
   * @param array $filters
   *   Active facet filters.
   *
   * @return string
   *   Pretty facet path prefix.
   */
  protected function getCollectionsFacetPathString(array &$filters) {
    // Applying filters order.
    $ordered_filters = [];
    foreach ($this->getFacetsOrder() as $filter_key) {
      if (isset($filters[$filter_key])) {
        // Sorting filters to by id() to have the same order all times.
        sort($filters[$filter_key]);
        $ordered_filters[$filter_key] = $filters[$filter_key];
        unset($filters[$filter_key]);
      }
    }
    // Add missing filters.
    foreach ($filters as $key => $filter) {
      $ordered_filters[$key] = $filter;
    }
    $filters = $ordered_filters;

    $path = '';
    foreach ($filters as $facet_id => &$active_values) {
      if (empty($active_values)) {
        // Just in case.
        continue;
      }
      /** @var \Drupal\facets\Entity\Facet $facet */
      $facet = Facet::load($facet_id);
      $data_definition = $facet->getDataDefinition();

      if ($data_definition->getDataType() == 'field_item:entity_reference') {
        $target_type = $data_definition->getSetting('target_type');
        if ($target_type == 'taxonomy_term' && $collections = $this->loadCollectionsTerms($active_values)) {
          $reindex = TRUE;
          foreach ($collections as $id => $collection_term) {
            $path .= '/' . $this->getTermSlugValue($collection_term);
            $array_index = array_search($id, $active_values);
            if ($array_index !== FALSE) {
              unset($active_values[$array_index]);
            }
            // Re-index array.
            $active_values = array_values($active_values);
            // Unset values array if it's empty.
            if (empty($active_values)) {
              unset($filters[$facet_id]);
            }
          }
        }
        elseif ($target_type == 'commerce_product_attribute_value' && $collections = $this->loadCollectionsAttributes($active_values)) {
          foreach ($collections as $id => $collection_attr_value) {
            if ($slug_value = $this->getAttributeSlugValue($collection_attr_value)) {
              $reindex = TRUE;
              $path .= '/' . $slug_value;
              $array_index = array_search($id, $active_values);
              if ($array_index !== FALSE) {
                unset($active_values[$array_index]);
              }
            }
          }
        }
      }

      if (!empty($reindex)) {
        // Re-index array.
        $active_values = array_values($active_values);
        // Unset values array if it's empty.
        if (empty($active_values)) {
          unset($filters[$facet_id]);
        }
      }

      $facet->getFieldAlias();
    }

    if (empty($path)) {
      return '/all';
    }
    return $path;
  }

  /**
   * Hardcoded facets order.
   *
   * You may want to override this method to provide your own order of facets.
   */
  protected function getFacetsOrder() {
    return [];
  }

  /**
   * Load collections taxonomies with given IDs.
   *
   * @param array $ids
   *   IDs of taxonomy terms.
   *
   * @return \Drupal\taxonomy\Entity\Term[]
   *   Array of collections terms.
   *
   *   Note that only collections terms will be returned; taxonomies which
   *   aren't collections will be ignored.
   */
  protected function loadCollectionsTerms(array $ids) {
    $query = \Drupal::entityQuery('taxonomy_term');
    $results = $query->condition('tid', $ids, 'IN')
      ->condition('field_is_collection', 1)
      ->sort('tid', 'ASC')
      ->execute();
    return Term::loadMultiple($results);
  }

  /**
   * Load product attributes values with given IDs.
   *
   * @param array $ids
   *   IDs of product attributes values.
   *
   * @return \Drupal\commerce_product\Entity\ProductAttributeValue[]
   *   Array of attribute values.
   */
  protected function loadCollectionsAttributes(array $ids) {
    return ProductAttributeValue::loadMultiple($ids);
  }

  /**
   * {@inheritdoc}
   */
  protected function initializeActiveFilters($configuration) {
    parent::initializeActiveFilters($configuration);
    $this->validateUrl();
    $this->initializeCollectionsFacets();
  }

  /**
   * Initialize collections facets values.
   */
  protected function initializeCollectionsFacets() {
    /** @var \Drupal\facets\FacetInterface $facet */
    $facet = $this->configuration['facet'];
    $facet_source = $facet->getFacetSource();
    $facet_source_id = $facet->getFacetSourceId();
    list($map, $cache_tags) = $this->collectionsUrlParser->getCollectionsMap($facet_source);
    if (!empty($map) && $filters = $this->routeMatch->getParameter('facets_query')) {
      $parts = explode('/', $filters);
      foreach ($parts as $url_part) {
        if ($url_part == 'all') {
          // Ignore '/all'.
          continue;
        }
        if ($facet_value = $this->findFacetValue($facet_source_id, $map, $url_part)) {
          list ($facet_id, $value) = $facet_value;
          $this->activeFilters[$facet_id][] = $value;
        }
      }
    }
  }

  /**
   * Validate URL with facets.
   *
   * @throws \Drupal\Core\Http\Exception\CacheableNotFoundHttpException
   *   Invalid collection URL.
   */
  protected function validateUrl() {
    if (!empty($this->urlValid)) {
      // Skip if URL is already validated.
      return;
    }

    /** @var \Drupal\facets\FacetInterface $facet */
    $facet = $this->configuration['facet'];
    list($map, $cache_tags) = $this->collectionsUrlParser->getCollectionsMap($facet->getFacetSource());

    $filters = $this->routeMatch->getParameter('facets_query');
    if ($filters === NULL) {
      // Page does not have facets, skip.
      $this->urlValid = TRUE;
      return;
    }

    if ($filters) {
      $parts = explode('/', $filters);
      if (isset($parts[0]) && $this->facetAccess($parts, $map)) {
        if ($parts[0] == 'all') {
          // Valid default URL.
          $this->urlValid = TRUE;
          return;
        }
        foreach ($map as $field_name => $values) {
          if (isset($values[$parts[0]])) {
            // First parameter is valid URL.
            $this->urlValid = TRUE;
            return;
          }
        }
      }
    }

    // Nothing passed, return 404.
    $cache_metadata = new CacheableMetadata();
    $cache_metadata->addCacheableDependency($facet);
    $cache_metadata->addCacheTags($cache_tags);
    $cache_metadata->addCacheContexts(['url']);
    throw new CacheableNotFoundHttpException($cache_metadata);
  }

  /**
   * Gets the facet id from the url alias & facet field name.
   *
   * @param string $field_name
   *   The field name.
   * @param string $facet_source_id
   *   The facet source id.
   *
   * @return bool|string
   *   Either the facet id, or FALSE if that can't be loaded.
   */
  protected function getFacetIdByFieldName($field_name, $facet_source_id) {
    $mapping = &drupal_static(__FUNCTION__);
    if (!isset($mapping[$facet_source_id][$field_name])) {
      $facet = current($this->facetsStorage->loadByProperties(['field_identifier' => $field_name, 'facet_source_id' => $facet_source_id]));
      if (!$facet) {
        return NULL;
      }
      $mapping[$facet_source_id][$field_name] = $facet->id();
    }
    return $mapping[$facet_source_id][$field_name];
  }

  /**
   * Find collection facet value.
   *
   * @param string $facet_source_id
   *   Facet source ID.
   * @param array $map
   *   Collections facets mapping, as returned by getCollectionsMap().
   * @param string $url_part
   *   URL path part.
   *
   * @return array|null
   *   Array of facet id, value term ID or NULL.
   */
  protected function findFacetValue($facet_source_id, array $map, $url_part) {
    foreach ($map as $field_name => $facet_values) {
      foreach ($facet_values as $text_value => $term_id) {
        if ($url_part == $text_value) {
          return [
            $this->getFacetIdByFieldName($field_name, $facet_source_id),
            $term_id,
          ];
        }
      }
    }
  }

  /**
   * Get taxonomy term slug URL value.
   *
   * @param \Drupal\taxonomy\Entity\Term $term
   *   Taxonomy term object.
   *
   * @return string
   *   Slug URL value.
   */
  protected function getTermSlugValue(Term $term) {
    if (!empty($term->field_url_value->value)) {
      return $term->field_url_value->value;
    }

    return $term->machine_name->value;
  }

  /**
   * Get attribute value slug URL value.
   *
   * @param \Drupal\commerce_product\Entity\ProductAttributeValue $attribute_value
   *   Attribute value object.
   *
   * @return string
   *   Slug URL value.
   */
  protected function getAttributeSlugValue(ProductAttributeValue $attribute_value) {
    if (!empty($attribute_value->field_url_value->value)) {
      return $attribute_value->field_url_value->value;
    }

    return FALSE;
  }

  /**
   * Check if facet URL is accessible.
   *
   * This method is meant to be overridden in child classes.
   *
   * @param array $parts
   *   URL parts.
   * @param array $map
   *   Facets mapping.
   *
   * @return bool
   *   Whether facet URL is accessible.
   */
  protected function facetAccess(array $parts, array $map) {
    return TRUE;
  }

}
