<?php

namespace Drupal\dcom_sitemap\Plugin\simple_sitemap\UrlGenerator;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Url;
use Drupal\dcom_facets\PublicFacetUrlInterface;
use Drupal\facets\FacetManager\DefaultFacetManager;
use Drupal\simple_sitemap\EntityHelper;
use Drupal\simple_sitemap\Logger;
use Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator\UrlGeneratorBase;
use Drupal\simple_sitemap\Simplesitemap;
use Drupal\simple_sitemap\SitemapGenerator;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\facets\Processor\ProcessorInterface;

/**
 * Class CollectionsUrlGenerator
 * @package Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator
 *
 * @UrlGenerator(
 *   id = "dcom_collections",
 *   weight = 20,
 *   instantiateForEachDataSet = true
 * )
 */
class CollectionsUrlGenerator extends UrlGeneratorBase {

  protected $moduleHandler;

  /**
   * CollectionsUrlGenerator constructor.
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   * @param \Drupal\simple_sitemap\Simplesitemap $generator
   * @param \Drupal\simple_sitemap\SitemapGenerator $sitemap_generator
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\simple_sitemap\Logger $logger
   * @param \Drupal\simple_sitemap\EntityHelper $entityHelper
   * @param \Drupal\Core\Extension\ModuleHandler $module_handler
   * @param \Drupal\Core\Entity\Query\QueryFactory $entity_query
   * @param \Drupal\Core\Database\Connection $database
   * @param \Drupal\facets\FacetManager\DefaultFacetManager $facet_manager
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    Simplesitemap $generator,
    SitemapGenerator $sitemap_generator,
    LanguageManagerInterface $language_manager,
    EntityTypeManagerInterface $entity_type_manager,
    Logger $logger,
    EntityHelper $entityHelper,
    ModuleHandler $module_handler,
    QueryFactory $entity_query,
    Connection $database,
    DefaultFacetManager $facet_manager
  ) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $generator,
      $sitemap_generator,
      $language_manager,
      $entity_type_manager,
      $logger,
      $entityHelper
    );
    $this->moduleHandler = $module_handler;
    $this->entityQuery = $entity_query;
    $this->database = $database;
    $this->facetManager = $facet_manager;
    $this->indexStorage = $entity_type_manager->getStorage('search_api_index');

    // Initial info about facets.
    $this->facet_info = [
      'category' => [
        'type' => 'entity',
        'facet_name' => 'product_category',
        'facet_key' => 'field_product_category',
        'entity_type' => 'taxonomy_term',
        'bundle' => 'product_category',
        'entity_class' => 'Term',
        'bundle_key' => 'vid',
      ],
      'brand' => [
        'type' => 'entity',
        'facet_name' => 'cbd_brand',
        'facet_key' => 'field_cbd_brand',
        'entity_type' => 'taxonomy_term',
        'bundle' => 'diamond_commerce_brand',
        'entity_class' => 'Term',
        'bundle_key' => 'vid',
      ],
      'type' => [
        'type' => 'entity',
        'facet_name' => 'product_type',
        'facet_key' => 'field_product_type',
        'entity_type' => 'taxonomy_term',
        'bundle' => 'product_type',
        'entity_class' => 'Term',
        'bundle_key' => 'vid',
      ],
      'strength' => [
        'type' => 'entity',
        'facet_name' => 'variations_product_variation_strength',
        'facet_key' => 'attribute_strength',
        'entity_type' => 'commerce_product_attribute_value',
        'bundle' => 'strength',
        'entity_class' => 'ProductAttributeValue',
        'bundle_key' => 'attribute',
      ],
      'volume' => [
        'type' => 'field',
        'facet_name' => 'product_volume_string',
        'facet_key' => 'field_product_volume_string',
        'entity_type' => 'commerce_product',
        'field_name' => 'field_product_volume',
      ],
      'weight' => [
        'type' => 'field',
        'facet_name' => 'product_weight_string',
        'facet_key' => 'field_product_weight_string',
        'entity_type' => 'commerce_product',
        'field_name' => 'field_product_weight',
      ],
    ];
  }

  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('simple_sitemap.generator'),
      $container->get('simple_sitemap.sitemap_generator'),
      $container->get('language_manager'),
      $container->get('entity_type.manager'),
      $container->get('simple_sitemap.logger'),
      $container->get('simple_sitemap.entity_helper'),
      $container->get('module_handler'),
      $container->get('entity.query'),
      $container->get('database'),
      $container->get('facets.manager')
    );
  }

  /**
   * Helps to get all combinations of 2 elements in array.
   */
  protected function uniqueCombination($in, $minLength = 2, $max = 2) {
    $count = count($in);
    $members = pow(2, $count);
    $return = array();
    for ($i = 0; $i < $members; $i ++) {
      $b = sprintf("%0" . $count . "b", $i);
      $out = array();
      for($j = 0; $j < $count; $j ++) {
        $b{$j} == '1' and $out[] = $in[$j];
      }

      count($out) >= $minLength && count($out) <= $max and $return[] = $out;
    }
    return $return;
  }

  /**
   * Provides facet specific info.
   */
  protected function getFacetsItems($name) {
    if (!isset($this->facet_info[$name]['items'])) {
      if (!isset($this->facet_info[$name])) {
        $this->facet_info[$name] = [];
      }
      else {
        if ($this->facet_info[$name]['type'] == 'entity') {
          $query = $this->entityQuery->get($this->facet_info[$name]['entity_type']);
          $this->facet_info[$name]['items'] = $query->condition($this->facet_info[$name]['bundle_key'], $this->facet_info[$name]['bundle'])->execute();
          if ($this->facet_info[$name]['entity_type'] == 'taxonomy_term' && $this->facet_info[$name]['items']) {
            // Getting domains ids for selected terms.
            $query = $this->database->select('taxonomy_term__field_domain', 'f');
            $query->addField('f', 'field_domain_target_id');
            $query->addField('f', 'entity_id');
            $query->condition('f.entity_id', $this->facet_info[$name]['items'], 'IN');
            $result = $query->execute();
            foreach ($result as $record) {
              $this->facet_info[$name]['items_domains'][$record->entity_id][] = $record->field_domain_target_id;
            }
          }
        }
        else {
          $number_key = "{$this->facet_info[$name]['field_name']}_number";
          $unit_key = "{$this->facet_info[$name]['field_name']}_unit";
          $query = $this->database->select("{$this->facet_info[$name]['entity_type']}__{$this->facet_info[$name]['field_name']}", 'f');
          $query->addField('f', $number_key);
          $query->addField('f', $unit_key);
          $query->orderBy("f.{$number_key}", 'ASC');
          $query->distinct();
          $result = $query->execute();
          foreach ($result as $record) {
            $value = floor($record->{$number_key});
            if ($value > 0) {
              $this->facet_info[$name]['items'][$value] = $value . $record->{$unit_key};
            }
          }
        }
      }
    }
    return $this->facet_info[$name];
  }

  /**
   * Helper function to if faceted search result is not empty.
   */
  protected function getSearchResults($facet1, $facet2, $f1item, $f2item) {
    $index = $this->indexStorage->load('products_index');
    $query = $index->query();
    $query->addCondition('field_domain', $this->context['domain']->id())
      ->addCondition('field_visibility_option', ['everywhere', 'retail'], 'IN');

    // Applying facets filters.
    $query->addCondition($facet1['facet_key'], $f1item);
    $query->addCondition($facet2['facet_key'], $f2item);

    $query->range(0, 1);
    return $query->execute()->getResultCount();
  }

  /**
   * @inheritdoc
   */
  public function getDataSets() {
    $data_sets = $this->uniqueCombination(['category', 'brand', 'type', 'strength', 'volume', 'weight']);

    $data = [];
    foreach ($data_sets as $data_set) {
      if (!isset($data_set[1])) {
        return [];
      }

      $facet1 = $this->getFacetsItems($data_set[0]);
      $facet2 = $this->getFacetsItems($data_set[1]);
      if (!empty($facet1['items']) && !empty($facet2['items'])) {
        foreach ($facet1['items'] as $f1item) {
          foreach ($facet2['items'] as $f2item) {
            $data[] = [$facet1, $facet2, $f1item, $f2item];

          }
        }
      }
    }
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  protected function addBatchResult($result) {
    $this->context['results']['generate'][$this->context['domain']->id()][] = $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function getProcessedElements() {
    $domain_id = $this->context['domain']->id();
    if (isset($this->context['results']['processed_paths'][$domain_id])
      && !empty($this->context['results']['processed_paths'])) {
      return $this->context['results']['processed_paths'][$domain_id];
    }
    else {
      return [];
    }
  }

  /**
   * @inheritdoc
   */
  protected function processDataSet($data) {
    $facet1 = $data[0];
    $facet2 = $data[1];
    $f1item = $data[2];
    $f2item = $data[3];

    if (PHP_SAPI === 'cli') {
      if (empty($this->context['results']['processed_facets'])) {
        $this->context['results']['processed_facets'] = 1;
      }
      else {
        $this->context['results']['processed_facets']++;
      }

      drush_print("Processed items: {$this->context['results']['processed_facets']}");
    }

    // No sense to implement SOLR query if at least one facet is from another domain.
    $domain_id = $this->context['domain']->id();
    if (isset($facet1['items_domains']) && !in_array($domain_id, $facet1['items_domains'][$f1item]) ||
      isset($facet2['items_domains']) && !in_array($domain_id, $facet2['items_domains'][$f2item])) {
      return [];
    }

    if ($this->getSearchResults($facet1, $facet2, $f1item, $f2item)) {
      $all_facets = $this->facetManager->getEnabledFacets();
      $processors = $all_facets['product_category']->getProcessorsByStage(ProcessorInterface::STAGE_BUILD);
      $processor = $processors['url_processor_handler']->getProcessor();

      $facet_filters = [
        $facet1['facet_name'] => [$f1item],
        $facet2['facet_name'] => [$f2item],
      ];

      if ($processor instanceof PublicFacetUrlInterface) {
        $pretty = $processor->getPrettyFacetUrl('collections/all', $facet_filters);
        $pretty->setAbsolute(TRUE);
        $route = $pretty->getRouteName();
        $defaults = $route->getDefaults();
        $route_name = "{$defaults['base_route_name']}_{$defaults['page_manager_page_variant']}";
        $url_object = Url::fromRoute($route_name, $pretty->getRouteParameters());
        $url_object->setOption('base_url', $this->context['domain']->getRawPath());
        $url_object->setAbsolute(TRUE);
        return [
          'url' => $url_object,
          'lastmod' => date_iso8601(time()),
          'priority' => 0.5,
          'changefreq' => NULL,
          'images' => [],
        ];
      }
    }
    return [];
  }

  /**
   * @inheritdoc
   */
  protected function getBatchIterationElements(array $data_set) {
    if ($this->needsInitialization()) {
      $this->initializeBatch(1);
    }
    return [$data_set];
  }

  /**
   * @inheritdoc
   */
  protected function getAlternateUrlsForAllLanguages($url_object) {
    $alternate_urls = [];
    foreach ($this->languages as $language) {
      if (!isset($this->batchSettings['excluded_languages'][$language->getId()]) || $language->isDefault()) {
        $url_object->setOption('language', $language);
        $alternate_urls[$language->getId()] = $this->replaceBaseUrlWithCustom($url_object->toString());
      }
    }
    return $alternate_urls;
  }
}
