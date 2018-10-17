<?php

namespace Drupal\dcom_facets\Plugin\facets\url_processor;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\cpl_commerce_shop\CollectionsUrlHelper;
use Drupal\cpl_commerce_shop\Plugin\facets\url_processor\PrettyPathsUrlProcessor;
use Drupal\dcom_facets\PublicFacetUrlInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\facets_pretty_paths\Coder\CoderPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Pretty paths URL processor.
 *
 * @FacetsUrlProcessor(
 *   id = "dcom_facets_pretty_paths",
 *   label = @Translation("Diamond Commerce - Pretty paths"),
 *   description = @Translation("Pretty paths uses slashes as separator, e.g. /edibles/brand/drupal/color/blue"),
 * )
 */
class DiamondPrettyPathsUrlProcessor extends PrettyPathsUrlProcessor implements PublicFacetUrlInterface {

  /**
   * Active domain ID.
   *
   * @var string
   */
  protected $activeDomainId;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration,
                              $plugin_id,
                              $plugin_definition,
                              Request $request,
                              EntityTypeManagerInterface $entity_type_manager,
                              RouteMatchInterface $route_match,
                              CoderPluginManager $coder_plugin_manager,
                              RouteProviderInterface $router,
                              CollectionsUrlHelper $url_parser,
                              DomainNegotiatorInterface $domain_negotiator) {
    $this->activeDomainId = $domain_negotiator->getActiveId();
    parent::__construct($configuration, $plugin_id, $plugin_definition, $request, $entity_type_manager, $route_match, $coder_plugin_manager, $router, $url_parser);
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
      $container->get('cpl_commerce_shop.collections_url_helper'),
      $container->get('domain.negotiator')
    );
  }

  /**
   * Hardcoded facets order.
   */
  protected function getFacetsOrder() {
    return [
      'product_category',
      'product_type',
      'cbd_brand',
      'variations_product_variation_strength',
      'product_volume_string',
      'product_weight_string',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getPrettyFacetUrl($base_path, array $filters_current_result) {
    return $this->buildPrettyFacetUrl($base_path, $filters_current_result);
  }

  /**
   * Extra facet access check.
   *
   * Do not allow facet access if at least one of active terms does not
   * belong to the active domain.
   *
   * {@inheritdoc}
   */
  protected function facetAccess(array $parts, array $map) {
    $tids = [];
    $facet_source = $this->configuration['facet']->getFacetSource();
    foreach ($map as $field_name => $values) {
      if ($facet_source->getDataDefinition($field_name)->getSetting('target_type') == 'taxonomy_term') {
        foreach ($parts as $part) {
          if (isset($values[$part])) {
            $tids[] = $values[$part];
          }
        }
      }
    }

    /** @var \Drupal\Core\Entity\ContentEntityInterface[] $terms */
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadMultiple($tids);
    foreach ($terms as $term) {
      if ($term->hasField('field_domain')
      && !in_array($this->activeDomainId, explode(', ', $term->get('field_domain')->getString()))) {
        return FALSE;
      }
    }

    return TRUE;
  }

}
