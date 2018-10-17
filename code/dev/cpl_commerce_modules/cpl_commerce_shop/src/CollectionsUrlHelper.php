<?php

namespace Drupal\cpl_commerce_shop;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\facets\FacetSource\FacetSourcePluginInterface;

/**
 * Helper class for parsing Collections URLs.
 */
class CollectionsUrlHelper {

  /**
   * Taxonomy terms storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $termStorage;

  /**
   * Attributes values storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $attributesStorage;

  /**
   * Cache backend service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * Static cache for getCollectionsMap()
   *
   * @var array
   */
  protected $maps;

  /**
   * Current route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * CollectionsUrlParser constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   Cache backend service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, CacheBackendInterface $cache, RouteMatchInterface $route_match) {
    $this->termStorage = $entity_type_manager->getStorage('taxonomy_term');
    $this->attributesStorage = $entity_type_manager->getStorage('commerce_product_attribute_value');
    $this->cache = $cache;
    $this->routeMatch = $route_match;
  }

  /**
   * Get collections facets mapping for given facet source.
   *
   * @param \Drupal\facets\FacetSource\FacetSourcePluginInterface $facet_source
   *   Facet source object.
   *
   * @return array
   *   Array of facet field name => term machine name => id, array of cache
   *   tags.
   */
  public function getCollectionsMap(FacetSourcePluginInterface $facet_source) {
    $plugin_id = $facet_source->getPluginId();

    if (!isset($this->maps[$plugin_id])) {
      $cid = 'cpl_commerce_shop.collections_map.' . $plugin_id;
      if ($cached_data = $this->cache->get($cid)) {
        // Try fetching the map from cache.
        $this->maps[$plugin_id]['fields'] = $cached_data->data;
        $this->maps[$plugin_id]['tags'] = $cached_data->tags;
      }
      else {
        // Cache failed.
        $this->maps[$plugin_id] = [];
        $cache_metadata = new CacheableMetadata();
        // I hope this is enough.
        $cache_metadata->addCacheTags($this->termStorage->getEntityType()->getListCacheTags());
        foreach ($facet_source->getFields() as $field => $label) {
          $data_definition = $facet_source->getDataDefinition($field);
          if ($data_definition->getDataType() == 'field_item:entity_reference') {
            $target_type = $data_definition->getSetting('target_type');
            if ($target_type == 'taxonomy_term') {
              $terms = $this->loadCollectionsForBundles($this->getTargetBundles($data_definition));
              foreach ($terms as $term) {
                $cache_metadata->addCacheableDependency($term);
                $this->maps[$plugin_id]['fields'][$field][$term->machine_name->value] = $term->id();
                if (!empty($term->field_url_value)) {
                  foreach ($term->field_url_value as $field_value) {
                    if (!empty($field_value->value)) {
                      $this->maps[$plugin_id]['fields'][$field][$field_value->value] = $term->id();
                    }
                  }
                }
              }
            }
            elseif ($target_type == 'commerce_product_attribute_value') {
              $attributes = $this->loadAttributesCollectionsForBundles($this->getTargetBundles($data_definition));
              foreach ($attributes as $attribute) {
                $cache_metadata->addCacheableDependency($attribute);
                $this->maps[$plugin_id]['fields'][$field][$attribute->name->value] = $attribute->id();
                if (!empty($attribute->field_url_value)) {
                  foreach ($attribute->field_url_value as $field_value) {
                    if (!empty($field_value->value)) {
                      $this->maps[$plugin_id]['fields'][$field][$field_value->value] = $attribute->id();
                    }
                  }
                }
              }
            }
          }

          if ($data_definition->getDataType() == 'field_item:entity_reference'
            && $data_definition->getSetting('target_type') == 'taxonomy_term') {
            $terms = $this->loadCollectionsForBundles($this->getTargetBundles($data_definition));
            foreach ($terms as $term) {
              $cache_metadata->addCacheableDependency($term);
              $this->maps[$plugin_id]['fields'][$field][$term->machine_name->value] = $term->id();
              if (!empty($term->field_url_value)) {
                foreach ($term->field_url_value as $field_value) {
                  if (!empty($field_value->value)) {
                    $this->maps[$plugin_id]['fields'][$field][$field_value->value] = $term->id();
                  }
                }
              }
            }
          }
        }
        $this->maps[$plugin_id]['tags'] = $cache_metadata->getCacheTags();
        $this->cache->set($cid, $this->maps[$plugin_id]['fields'], Cache::PERMANENT, $this->maps[$plugin_id]['tags']);
      }
    }

    return [$this->maps[$plugin_id]['fields'], $this->maps[$plugin_id]['tags']];
  }

  /**
   * Returns whether current route path is /collections/{facets_query} or not.
   *
   * @return bool
   *   Whether current route path is /collections/{facets_query} or not.
   */
  public function isCollectionPage() {
    $url = Url::fromUri('internal:/collections/{facets_query}');
    return $url && $this->routeMatch->getRouteName() == $url->getRouteName();
  }

  /**
   * Gets the taxonomy term collection for the URL.
   *
   * @param bool $single_only
   *   Make sure only one collection is queried.
   * @param bool $allow_all
   *   TRUE to allow loading 'all' term.
   *
   * @return bool|\Drupal\taxonomy\TermInterface
   *   The taxonomy term collection or FALSE otherwise.
   */
  public function getCollectionTermFromUrl($single_only = FALSE, $allow_all = FALSE) {
    /** @var \Drupal\Core\Routing\CurrentRouteMatch $route */
    $route = \Drupal::routeMatch();
    $facets_query = $route->getParameter('facets_query');

    if (!$this->isCollectionPage() || !$facets_query) {
      return FALSE;
    }

    $facets_query = explode('/', $facets_query);

    if (!$allow_all
      && count($facets_query) > 1
      && $facets_query[0] == 'all') {
      return FALSE;
    }

    if (empty($facets_query)) {
      return FALSE;
    }

    $tids = $this->termStorage
      ->getQuery('OR')
      ->condition('field_url_value', $facets_query, 'IN')
      ->condition('machine_name', $facets_query, 'IN')
      ->execute();

    if ($single_only && (count($tids) > 1)) {
      return FALSE;
    }

    $tid = reset($tids);
    if ($tid && ($term = $this->termStorage->load($tid))) {
      return $term;
    }

    // Return 'all' if nothing else was found.
    if (!in_array('all', $facets_query)) {
      $tids = $this->termStorage
        ->getQuery('OR')
        ->condition('field_url_value', $facets_query, 'IN')
        ->condition('machine_name', $facets_query, 'IN')
        ->execute();
      $tid = reset($tids);
      if ($tid && ($term = $this->termStorage->load($tid))) {
        return $term;
      }
    }

    return FALSE;
  }

  /**
   * Load collections terms for given bundles.
   *
   * @param array|null $target_bundles
   *   Array of taxonomy vocabulary names or NULL.
   *
   * @return \Drupal\taxonomy\Entity\Term[]
   *   List of collections terms.
   */
  protected function loadCollectionsForBundles($target_bundles) {
    $query = $this->termStorage->getQuery();
    $query->condition('field_is_collection', 1);
    $or = $query->orConditionGroup()
      ->condition('machine_name', NULL, '<>')
      ->condition('field_url_value', NULL, '<>');
    $query->condition($or);
    if ($target_bundles !== NULL) {
      $query->condition('vid', $target_bundles, 'IN');
    }

    return $this->termStorage->loadMultiple($query->execute());
  }

  /**
   * Load attributes values for given bundles.
   *
   * @param array|null $target_bundles
   *   Array of attributes values bundles.
   *
   * @return \Drupal\commerce_product\Entity\ProductAttributeValue[]
   *   List of attributes values.
   */
  protected function loadAttributesCollectionsForBundles($target_bundles) {
    $query = $this->attributesStorage->getQuery();
    $query->condition('field_url_value', NULL, '<>');;
    if ($target_bundles !== NULL) {
      $query->condition('attribute', $target_bundles, 'IN');
    }

    return $this->attributesStorage->loadMultiple($query->execute());
  }

  /**
   * Get list of target bundles for given reference field definition.
   *
   * @param \Drupal\Core\TypedData\DataDefinitionInterface $data_definition
   *   Field data definition.
   *
   * @return array|null
   *   List of target bundles or NULL.
   */
  protected function getTargetBundles(DataDefinitionInterface $data_definition) {
    $handler_settings = $data_definition->getSetting('handler_settings');
    return !empty($handler_settings['target_bundles']) ? $handler_settings['target_bundles'] : NULL;
  }

}
