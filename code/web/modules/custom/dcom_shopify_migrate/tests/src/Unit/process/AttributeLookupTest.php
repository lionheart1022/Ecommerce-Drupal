<?php

namespace Drupal\Tests\dcom_shopify_migrate\Unit\process;

use Drupal\dcom_shopify_migrate\Plugin\migrate\process\AttributeLookup;
use Drupal\migrate\MigrateException;
use Drupal\Tests\migrate\Unit\process\MigrateProcessTestCase;

/**
 * Tests the concat process plugin.
 *
 * @group migrate
 */
class AttributeLookupTest extends MigrateProcessTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    $this->plugin = new TestAttributeLookup();
    parent::setUp();
  }

  /**
   * Test tags mapping.
   *
   * @dataProvider inputProvider
   */
  public function testTagsMapping($attribute, $values, $expected) {
    $this->plugin->setAttribute($attribute);
    $value = $this->plugin->transform($values, $this->migrateExecutable, $this->row, 'destinationproperty');
    $this->assertSame($value, $expected);
  }

  /**
   * Test process fails properly with wrong tag type.
   */
  public function testUnknownAttribute() {
    $this->setExpectedException(MigrateException::class);
    $this->plugin->setAttribute('wrong');
    $this->plugin->transform(['foo', ['tag1', 'tag2']], $this->migrateExecutable, $this->row, 'destinationproperty');
  }

  /**
   * Data provider for testTagsMapping.
   */
  public function inputProvider() {
    return [
      ['strength_value', ['100mg', []], '100mg'],
      ['strength_value', ['default title', ['100mg']], '100mg'],
      ['strength_value', ['default title', ['100mg', '200mg']], FALSE],
      ['strength_value', ['200mg', ['100mg', '200mg']], '200mg'],
      ['strength_value', ['200mg', ['100mg', '200mg', '200ml']], '200mg'],
      ['volume_value', ['200ml', ['100mg', '200mg', '200ml']], '200ml'],
      ['volume_value', ['200ml', ['100mg', '200mg']], '200ml'],
    ];
  }

}

/**
 * Testable class.
 */
class TestAttributeLookup extends AttributeLookup {

  /**
   * {@inheritdoc}
   */
  public function __construct() {}

  /**
   * Set attribute.
   *
   * @param string $attribute
   *   The new attribute.
   */
  public function setAttribute($attribute) {
    $this->configuration['attribute'] = $attribute;
  }

}
