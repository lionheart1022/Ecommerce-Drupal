<?php

namespace Drupal\Tests\dcom_shopify_migrate\Unit\process;

use Drupal\dcom_shopify_migrate\Plugin\migrate\process\TagsFilter;
use Drupal\migrate\MigrateException;
use Drupal\Tests\migrate\Unit\process\MigrateProcessTestCase;

/**
 * Tests the concat process plugin.
 *
 * @group migrate
 */
class TagsFilterTest extends MigrateProcessTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    $this->plugin = new TestTagsFilter();
    parent::setUp();
  }

  /**
   * Test tags mapping.
   *
   * @dataProvider tagsMappingProvider
   */
  public function testTagsMapping($tag_type, $tags, $expected) {
    $this->plugin->setTagType($tag_type);
    $value = $this->plugin->transform($tags, $this->migrateExecutable, $this->row, 'destinationproperty');
    $this->assertSame($value, $expected);
  }

  /**
   * Test process fails properly with wrong tag type.
   */
  public function testUnknownTagType() {
    $this->setExpectedException(MigrateException::class);
    $this->plugin->setTagType('wrong');
    $this->plugin->transform(['foo'], $this->migrateExecutable, $this->row, 'destinationproperty');
  }

  /**
   * Data provider for testTagsMapping.
   */
  public function tagsMappingProvider() {
    // Same tags input for all tests.
    $non_macthing_tags = [
      'over-75',
      'edible',
      'drinks',
      'cbd-shots',
    ];

    // @codingStandardsIgnoreStart
    foreach ($non_macthing_tags as $tag) {
      yield ['strength_value', $tag, []];
      yield ['volume_value', $tag, []];
      yield ['pack_value', $tag, []];
    }

    yield ['strength_value', '20mg', ['20mg']];
    // Handle multiple values.
    yield ['strength_value', ['20mg'], ['20mg']];
    yield ['volume_value', '60ml', ['60ml']];
    yield ['pack_value', '12-pack', ['12-pack']];

    yield ['pack_value', '20mg', []];
    yield ['strength_value', '60ml', []];
    yield ['volume_value', '12-pack', []];

    yield ['domain_value', 'meds-biotech', ['mbio_domain', 'diamondcbd_domain']];
    yield ['domain_value', ['meds-biotech', 'medipets', 'cbd-for-pets'], ['mbio_domain', 'medipets_domain', 'diamondcbd_domain']];
    // DiamondCBD is always appended.
    yield ['domain_value', [], ['diamondcbd_domain']];

    // Brands.
    foreach (TagsFilter::getStaticMap()['brand_value'] as $tag => $tag_value) {
      // Adding some random tags here.
      $tags = [$tag, '20mg', '12-pack', 'edible', 'drinks'];
      yield['brand_value', $tags, [$tag_value]];
    }
    yield['brand_value', ['20mg', '12-pack', 'edible', 'drinks'], []];
    yield['brand_value', ['cbd-for-pets', '20mg', '12-pack', 'edible', 'drinks'], ['CBD For Pets']];
    yield['brand_value', ['medipets', '20mg', '12-pack', 'edible', 'drinks'], ['Medipets']];
    yield['brand_value', ['cbd-for-pets', 'medipets', '20mg', '12-pack', 'edible', 'drinks'], ['Medipets']];

    // Categories.
    foreach (TagsFilter::getStaticMap()['category_value'] as $tag => $tag_value) {
      // Adding some random tags here.
      $tags = [$tag, 'medipets', '20mg', '12-pack','meds-biotech'];
      yield['category_value', $tags, [$tag_value]];
    }
    yield['category_value', ['medipets', '20mg', '12-pack','meds-biotech'], []];

    // Types.
    foreach (TagsFilter::getStaticMap()['type_value'] as $tag => $tag_value) {
      // Adding some random tags here.
      $tags = [$tag, 'medipets', '20mg', '12-pack','meds-biotech', 'edible', 'drinks'];
      yield['type_value', $tags, [$tag_value]];
    }
    yield['type_value', ['medipets', '20mg', '12-pack','meds-biotech', 'edible', 'drinks'], []];

    // Characteristics.
    foreach (TagsFilter::getStaticMap()['characteristics_value'] as $tag => $tag_value) {
      // Adding some random tags here.
      $tags = [$tag, 'medipets', '20mg', '12-pack','meds-biotech', 'edible', 'drinks'];
      yield['characteristics_value', $tags, [$tag_value]];
    }
    yield['characteristics_value', ['medipets', '20mg', '12-pack','meds-biotech', 'edible', 'drinks'], []];


    // Product volume.
    yield ['volume_value_number', ['20ml'], ['20']];
    yield ['volume_value_number', ['20l'], ['20']];
    yield ['volume_value_number', ['20cl'], ['20']];
    yield ['volume_value_unit', '60ml', ['ml']];
    yield ['volume_value_unit', '60l', ['l']];
    yield ['volume_value_unit', '60cl', ['cl']];
    yield ['volume_value_number', ['20mm'], []];
    yield ['volume_value_unit', '60mg', []];
    yield ['volume_value_unit', ['medipets', '20ml', '12-pack', 'edible', 'drinks'], ['ml']];
    yield ['volume_value_number', ['medipets', '20mg', '12-pack', 'edible', 'drinks'], []];

    // Product weight.
    yield ['weight_value_number', ['20g'], ['20']];
    yield ['weight_value_unit', ['20g'], ['g']];
    yield ['weight_value_number', ['20oz'], ['20']];
    yield ['weight_value_unit', ['20oz'], ['oz']];
    yield ['weight_value_unit', ['medipets', '20ml', '12-pack', 'edible', 'drinks'], []];
    yield ['weight_value_number', ['medipets', '20mg', '12-pack', 'edible', 'drinks'], []];
    yield ['weight_value_number', ['medipets', '20g', '12-pack', 'edible', 'drinks'], ['20']];
    yield ['weight_value_unit', ['medipets', '20g', '12-pack', 'edible', 'drinks'], ['g']];

    // @codingStandardsIgnoreEnd
  }

}

/**
 * Testable class.
 */
class TestTagsFilter extends TagsFilter {

  /**
   * {@inheritdoc}
   */
  public function __construct() {}

  /**
   * Set the tag type.
   *
   * @param string $tag_type
   *   The new tag type.
   */
  public function setTagType($tag_type) {
    $this->configuration['tag_type'] = $tag_type;
  }

}
