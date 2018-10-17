<?php

namespace Drupal\dcom_shopify_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateException;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use LogicException;

/**
 * DiamondCBD product tags mapping plugin.
 *
 * @code
 * process:
 *   some_taxonomy_field:
 *     plugin: shopify_tags_filter
 *     source: tags
 *     tag_type: strength_value
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "shopify_tags_filter",
 *   handle_multiples = TRUE
 * )
 */
class TagsFilter extends ProcessPluginBase {

  /**
   * Tag type mapping.
   *
   * @var array
   *   Callback function names keyed by tag type.
   */
  protected static $tagTypes = [
    'strength_value' => 'processMgValue',
    'pack_value' => 'processPackValue',
    'domain_value' => 'processDomainValue',
    'brand_value' => 'processBrandValue',
    'category_value' => 'processCategoryValue',
    'type_value' => 'processTypeValue',
    'characteristics_value' => 'processCharacteristicValue',
    'volume_value' => 'processMlValue',
    'volume_value_number' => 'processVolumeValueNumber',
    'volume_value_unit' => 'processVolumeValueUnit',
    'weight_value_number' => 'processWeightValueNumber',
    'weight_value_unit' => 'processWeightValueUnit',
  ];

  /**
   * Static values map.
   *
   * @var array
   *   Array of arrays of static maps, keyed by tag type.
   */
  protected static $staticMap = [
    'brand_value' => [
      'blue-cbd-flavored' => 'Blue CBD Flavored Crystals Isolate',
      'blue-cbd-crystals-isolate' => 'Blue CBD Unflavored Crystals Isolate',
      'biotech-oil' => 'Biotech Oil',
      'cbd-crystals-dabs' => 'CBD Crystal Dabs',
      'cbd-extreme-drops' => 'CBD Extreme Drops',
      'cbd-cake-pops' => 'CBD Cake Pops',
      'double-shots' => 'CBD Double Shots',
      'cbd-fatty' => 'CBD Fatty',
      'cbd-for-pets' => 'CBD For Pets',
      'cbd-honey-sticks' => 'CBD Honey Sticks',
      'cbd-re-leaf' => 'CBD Re-Leaf',
      'cbd-shots' => 'CBD Shots',
      'chill-gummies' => 'Chill Gummies',
      'chill-plus-gummies' => 'Chill Plus Gummies',
      'chongs-choice-cbd' => 'Chong\'s Choice CBD',
      'diamond-cbd-gummies' => 'Diamond CBD Gummies',
      'flavored-diamond-cbd-hemp-oil' => 'Diamond CBD Flavored Hemp Oil',
      'diamond-cbd-flavored-terpenes-oils' => 'Diamond CBD Flavored Terpenes Oils',
      'natural-unflavored-cbd-oil' => 'Diamond CBD Unflavored Hemp Oil',
      'diamond-cbd-vape-additive' => 'Diamond CBD Vape Additive',
      'easy-grinder' => 'Easy Grinder',
      'lean-shots' => 'Lean Shots',
      'liquid-gold' => 'Liquid Gold',
      'meds-biotech' => 'Meds Biotech',
      'pain-master' => 'Pain Master',
      'relax-extreme-cbd' => 'Relax Extreme CBD',
      'relax-gummies' => 'Relax Gummies',
      'relax-vape-liquid' => 'Relax Vape Liquid',
      'medipets' => 'Medipets',
    ],
    'category_value' => [
      'edible' => 'Edibles',
      'drinks' => 'Drinks',
      'oil' => 'Oils',
      'vape' => 'Vapes',
      'smokables' => 'Smokables',
      'creams' => 'Creams',
      'pets' => 'Pets',
      'grinders' => 'Grinders',
      'new-arrivals' => 'New Arrivals',
    ],
    'type_value' => [
      'cbd-isolates' => 'Isolates',
      'gummies' => 'Gummies',
      'chocolates' => 'Chocolates',
      'cbd-terpenes' => 'CBD Terpenes',
      'capsules' => 'Capsules',
      'full-spectrum' => 'Full Spectrum',
      'honey' => 'Honey',
      'oral-drops' => 'Oral Drops',
      'vape-additive' => 'Vape Additive',
      'vaping-pen' => 'Vaping Pens & Tanks',
      'vape-refills' => 'Vape Refills/Liquids',
    ],
    'characteristics_value' => [
      'best-sellers' => 'best_sellers',
      'new-arrivals' => 'new_arrival',
    ],
  ];

  /**
   * Static map getter.
   *
   * @return array
   *   Static map.
   */
  public static function getStaticMap() {
    return self::$staticMap;
  }

  /**
   * {@inheritdoc}
   */
  public function multiple() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (!is_array($value)) {
      $value = [$value];
    }

    if (!isset($this->configuration['tag_type'])) {
      throw new MigrateException('Missing tag_type configuration.');
    }

    $tag_type = $this->configuration['tag_type'];
    return self::filterValues($tag_type, $value);
  }

  /**
   * Filter tags by type.
   *
   * @param string $tag_type
   *   Tag type. One of TagsFilter::$tagTypes.
   * @param mixed $values
   *   Tag or multiple tags.
   *
   * @return array
   *   Human-readable tags matching given type.
   *
   * @throws \Drupal\migrate\MigrateException
   *   Unknown tag type.
   * @throws \LogicException
   *   Missing implementation.
   */
  public static function filterValues($tag_type, $values) {
    if (!is_array($values)) {
      $values = [$values];
    }

    if (!isset(static::$tagTypes[$tag_type])) {
      throw new MigrateException('Unknown tag_type "' . $tag_type . '".');
    }

    $callback = [
      '\Drupal\dcom_shopify_migrate\Plugin\migrate\process\TagsFilter',
      static::$tagTypes[$tag_type],
    ];
    if (!is_callable($callback)) {
      throw new LogicException('Missing transform implementation for tag type "' . $tag_type . '".');
    }

    return call_user_func($callback, $values);
  }

  /**
   * Process callback for 'XXmg' tags.
   */
  protected static function processMgValue(array $tags) {
    return array_values(preg_filter('/^(\d+mg)$/', '$1', $tags));
  }

  /**
   * Process callback for 'XXml' tags.
   */
  protected static function processMlValue(array $tags) {
    return array_values(preg_filter('/^(\d+ml)$/', '$1', $tags));
  }

  /**
   * Process callback for 'XXml' tags; returns only number.
   */
  protected static function processVolumeValueNumber(array $tags) {
    return array_values(preg_filter('/^(\d+)(?:ml|l|cl)$/', '$1', $tags));
  }

  /**
   * Process callback for 'XXml' tags; returns only number.
   */
  protected static function processVolumeValueUnit(array $tags) {
    return array_values(preg_filter('/^\d+(ml|l|cl)$/', '$1', $tags));
  }

  /**
   * Process callback for 'XXg' tags; returns only number.
   */
  protected static function processWeightValueNumber(array $tags) {
    return array_values(preg_filter('/^(\d+)(?:g|kg|oz|lb)$/', '$1', $tags));
  }

  /**
   * Process callback for 'XXg' tags; returns only number.
   */
  protected static function processWeightValueUnit(array $tags) {
    return array_values(preg_filter('/^\d+(g|kg|oz|lb)$/', '$1', $tags));
  }

  /**
   * Process callback for 'X-pack' tags.
   */
  protected static function processPackValue(array $tags) {
    return array_values(preg_filter('/^(\d+-pack)$/', '$1', $tags));
  }

  /**
   * Process callback for domain tags.
   */
  protected static function processDomainValue(array $tags) {
    $mapping = [
      'meds-biotech' => 'mbio_domain',
      'medipets' => 'medipets_domain',
    ];

    return array_merge(array_values(array_intersect_key($mapping, array_combine($tags, $tags))), ['diamondcbd_domain']);
  }

  /**
   * Process callback for brand tags.
   */
  protected static function processBrandValue(array $tags) {
    $brands = self::processStaticMapValue('brand_value', $tags);
    if (count($brands) == 2
      && in_array('Medipets', $brands)
      && in_array('CBD For Pets', $brands)) {
      // Special case. Only Medipets should be selected if both Medipets and
      // CBD For Pets are tagged.
      return ['Medipets'];
    }
    return $brands;
  }

  /**
   * Process callback for category tags.
   */
  protected static function processCategoryValue(array $tags) {
    return self::processStaticMapValue('category_value', $tags);
  }

  /**
   * Process callback for type tags.
   */
  protected static function processTypeValue(array $tags) {
    return self::processStaticMapValue('type_value', $tags);
  }

  /**
   * Process callback for characteristics tags.
   */
  protected static function processCharacteristicValue(array $tags) {
    return self::processStaticMapValue('characteristics_value', $tags);
  }

  /**
   * Helper function for processing static tags mapping.
   *
   * @param string $tag_type
   *   Tag type. One of TagsFilter::$tagTypes.
   * @param array $tags
   *   Tags array.
   *
   * @return array
   *   Process tag from static map.
   */
  protected static function processStaticMapValue($tag_type, array $tags) {
    $mapping = static::getStaticMap()[$tag_type];
    return array_values(array_intersect_key($mapping, array_combine($tags, $tags)));
  }

}
