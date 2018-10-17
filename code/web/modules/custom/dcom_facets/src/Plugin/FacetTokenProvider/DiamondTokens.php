<?php

namespace Drupal\dcom_facets\Plugin\FacetTokenProvider;

use Drupal\Core\Utility\Token;
use Drupal\cpl_commerce_facet_token\Plugin\FacetTokenProviderBase;
use Drupal\cpl_commerce_shop\CollectionsUrlHelper;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\facets\FacetManager\DefaultFacetManager;
use Drupal\facets\FacetSource\FacetSourcePluginManager;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * DiamondCBD Shop tokens provider.
 *
 * @FacetTokenProvider(
 *   id = "dcom_facet_tokens",
 *   label = @Translation("DiamondCBD Commerce tokens"),
 * )
 */
class DiamondTokens extends FacetTokenProviderBase {

  /**
   * Active domain id.
   *
   * @var string
   */
  protected $activeDomainId;

  /**
   * Token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    FacetSourcePluginManager $facet_source_plugin_manager,
    DefaultFacetManager $facets_manager,
    CollectionsUrlHelper $collections_helper,
    DomainNegotiatorInterface $negotiator,
    Token $token) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $facet_source_plugin_manager, $facets_manager, $collections_helper);
    $this->activeDomainId = $negotiator->getActiveId();
    $this->token = $token;
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
      $container->get('cpl_commerce_shop.collections_url_helper'),
      $container->get('domain.negotiator'),
      $container->get('token')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getImageToken($original) {
    $image_token = '';

    if ($this->getAllActiveFacetResultsCount() < 2
      && $collection = $this->getCollectionTerm()) {
      if ($collection->hasField('field_domain_fields')) {
        foreach ($collection->field_domain_fields->referencedEntities() as $fc) {
          if ($this->activeDomainId == $fc->get('field_domain')->getString() && $fc->get('field_image')->getValue()) {
            $collection = $fc;
          }
        }
      }
      $image_token = $this->token->replace(str_replace('cpl_commerce_facet:active_image', 'term:field_image', $original), ['taxonomy_term' => $collection]);
    }

    return (string) $image_token;
  }

  /**
   * {@inheritdoc}
   *
   * The pattern is following:
   * {strength} {product type} - {category} {from Brand} ({volume} or {weight})
   */
  public function getTitleToken($use_meta_title) {
    if ($collection_title = $this->getActiveCollectionTitle($use_meta_title)) {
      return $collection_title;
    }

    $string_parts = [];

    // Left part: {strength} {product type}.
    $left_parts = [];
    if ($strength = $this->getActiveFacetValuesString('variations_product_variation_strength')) {
      $left_parts[] = $strength;
    }
    if ($product_type = $this->getActiveFacetValuesString('product_type')) {
      $left_parts[] = $product_type;
    }
    if (!empty($left_parts)) {
      $string_parts[] = implode(' ', $left_parts);
    }

    // Right part: {category} {from Brand} ({volume} or {weight}).
    $right_parts = [];
    if ($category = $this->getActiveFacetValuesString('product_category')) {
      $right_parts[] = $category;
    }
    if ($brand = $this->getActiveFacetValuesString('cbd_brand')) {
      $right_parts[] = $this->t('from @brand', ['@brand' => $brand]);
    }

    $volume_or_weight_label = $this->getVolumeOrWeight();

    if ($volume_or_weight_label) {
      $right_parts[] = $this->t('(@value)', ['@value' => $volume_or_weight_label]);
    }

    if (!empty($right_parts)) {
      $string_parts[] = implode(' ', $right_parts);
    }

    if (empty($string_parts)) {
      return $this->t('All');
    }

    // If user selects only size or strength (one or multiple), title is:
    // "{strength} CBD products".
    if (count($string_parts) == 1
      && $volume_or_weight_label) {
      $template = '@label CBD products';
      if ($this->activeDomainId == 'diamondhemp_domain') {
        $template = '@label hemp products';
      }
      return (string) $this->t($template, ['@label' => $volume_or_weight_label]);
    }

    // Add ' - ' as a glue between left and right parts.
    return implode(' - ', $string_parts);
  }

  /**
   * {@inheritdoc}
   *
   * The pattern is following:
   *
   * The highest quality {strength} {product type} products made in the USA.
   * Enjoy the most trusted {category} {from Brand} ({volume} or {weight}) made
   * from all natural hemp CBD.
   */
  public function getDescriptionToken($use_meta_description) {
    if ($collection_description = $this->getActiveCollectionDescription($use_meta_description)) {
      return $collection_description;
    }

    $from_brand = '';
    $volume_or_weight = '';

    if ($brand = $this->getActiveFacetValuesString('cbd_brand')) {
      $from_brand = $this->t('from @brand', ['@brand' => $brand]);
    }

    if ($volume_or_weight_label = $this->getVolumeOrWeight()) {
      $volume_or_weight = $this->t('(@value)', ['@value' => $volume_or_weight_label]);
    }

    $strength = $this->getActiveFacetValuesString('variations_product_variation_strength');
    $product_type = $this->getActiveFacetValuesString('product_type');
    $category = $this->getActiveFacetValuesString('product_category');

    if ($volume_or_weight
      && !$strength
      && !$product_type
      && !$category
      && !$from_brand) {
      $template = 'The highest quality @volume_or_weight CBD products made in the USA.  Enjoy trusted CBD Oils, CBD vapes, CBD edibles, and more.';
      if ($this->activeDomainId == 'diamondhemp_domain') {
        $template = 'The highest quality @volume_or_weight hemp products made in the USA.  Enjoy trusted hemp creams, hemp cosmetics, and more';
      }
      return $this->t($template, ['@volume_or_weight' => $volume_or_weight]);
    }

    $vars = [
      '@strength' => $strength ?: '',
      '@type' => $product_type ?: $this->t('CBD'),
      '@category' => $category ?: $this->t('products'),
      '@from_brand' => $from_brand,
      '@volume_or_weight' => $volume_or_weight,
    ];

    $template = 'The highest quality @strength @type products made in the USA. Enjoy the most trusted @category @from_brand @volume_or_weight made from all natural hemp CBD.';
    if ($this->activeDomainId == 'diamondhemp_domain') {
      $template = 'The highest quality @strength @type products made in the USA. Enjoy the most trusted @category @from_brand @volume_or_weight made from all natural hemp.';
      $vars['@type'] = $this->t('hemp');
    }

    return html_entity_decode(str_replace('  ', ' ', $this->t($template, $vars)));
  }

  /**
   * {@inheritdoc}
   */
  protected function getActiveCollectionTitle($use_meta_title) {
    if ($this->getAllActiveFacetResultsCount() < 2
      && $collection = $this->collectionsHelper->getCollectionTermFromUrl()) {
      $title = $collection->label();
      if ($use_meta_title) {
        if ($domain_title = $this->getActiveCollectionDomainFieldValue($collection, 'field_meta_title')) {
          $title = $domain_title;
        }
        elseif (!empty($collection->field_meta_title->value)) {
          $title = $collection->field_meta_title->value;
        }
      }
      return html_entity_decode(strip_tags($title));
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function getActiveCollectionDescription($use_meta_description) {
    if ($this->getAllActiveFacetResultsCount() < 2
      && $collection = $this->getCollectionTerm()) {

      $description = $collection->getDescription();

      if ($use_meta_description) {
        if ($domain_description = $this->getActiveCollectionDomainFieldValue($collection, 'field_meta_description')) {
          $description = $domain_description;
        }
        elseif (!empty($collection->field_meta_description->value)) {
          $description = $collection->field_meta_description->value;
        }
      }
      elseif ($domain_description = $this->getActiveCollectionDomainFieldValue($collection, 'field_description')) {
        $description = $domain_description;
      }
      return html_entity_decode(strip_tags($description));
    }

    return NULL;
  }

  /**
   * Get formatted label string for volume/weight selection.
   *
   * @return string|null
   *   Formatter string or NULL.
   */
  protected function getVolumeOrWeight() {
    $volume_or_weight_label = NULL;
    $volume_or_weight = [];
    if ($volume = $this->getActiveFacetLabels('product_volume_string')) {
      $volume_or_weight = array_merge($volume_or_weight, $volume);
    }
    if ($weight = $this->getActiveFacetLabels('product_weight_string')) {
      $volume_or_weight = array_merge($volume_or_weight, $weight);
    }
    if (!empty($volume_or_weight)) {
      $volume_or_weight_label = $this->formatMultipleLabels($volume_or_weight);
    }

    return $volume_or_weight_label;
  }

  /**
   * Get active collection title.
   *
   * @param \Drupal\taxonomy\TermInterface $collection
   *   Collection term object.
   * @param string $field_name
   *   Field name.
   *
   * @return string
   *   Collection field value specific for domain.
   */
  protected function getActiveCollectionDomainFieldValue(TermInterface $collection, $field_name) {
    if ($collection->hasField('field_domain_fields')) {
      foreach ($collection->field_domain_fields->referencedEntities() as $fc) {
        if ($this->activeDomainId == $fc->get('field_domain')->getString() && !empty($fc->{$field_name}->value)) {
          return $fc->{$field_name}->value;
        }
      }
    }
    return '';
  }

}
