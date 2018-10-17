<?php

namespace Drupal\cpl_search_popup\Plugin\Field\FieldFormatter;

use CommerceGuys\Intl\Formatter\CurrencyFormatterInterface;
use Drupal\commerce\Context;
use Drupal\commerce_order\AdjustmentTypeManager;
use Drupal\commerce_order\Plugin\Field\FieldFormatter\PriceCalculatedFormatter;
use Drupal\commerce_order\PriceCalculatorInterface;
use Drupal\commerce_store\CurrentStoreInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implement of the 'product_variation_calculated_price_range' formatter.
 *
 * @FieldFormatter(
 *   id = "product_variation_calculated_price_range",
 *   label = @Translation("Product Variation Calculated Price Range"),
 *   field_types = {
 *     "entity_reference",
 *   },
 * )
 */
class ProductVariationCalculatedPriceRangeFormatter extends PriceCalculatedFormatter {

  /**
   * The currency storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $currencyStorage;

  /**
   * Constructs a new ProductVariationCalculatedPriceRangeFormatter object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings settings.
   * @param \CommerceGuys\Intl\Formatter\CurrencyFormatterInterface $currency_formatter
   *   The currency formatter.
   * @param \Drupal\commerce_order\AdjustmentTypeManager $adjustment_type_manager
   *   The adjustment type manager.
   * @param \Drupal\commerce_store\CurrentStoreInterface $current_store
   *   The current store.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\commerce_order\PriceCalculatorInterface $price_calculator
   *   The price calculator.
   * @param \Drupal\Core\Entity\EntityStorageInterface $currency_storage
   *   Currency storage service.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, CurrencyFormatterInterface $currency_formatter, AdjustmentTypeManager $adjustment_type_manager, CurrentStoreInterface $current_store, AccountInterface $current_user, PriceCalculatorInterface $price_calculator, EntityStorageInterface $currency_storage) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings, $currency_formatter, $adjustment_type_manager, $current_store, $current_user, $price_calculator);

    $this->currencyStorage = $currency_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('commerce_price.currency_formatter'),
      $container->get('plugin.manager.commerce_adjustment_type'),
      $container->get('commerce_store.current_store'),
      $container->get('current_user'),
      $container->get('commerce_order.price_calculator'),
      $container->get('entity_type.manager')->getStorage('commerce_currency')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    if (!$items->isEmpty()) {
      $context = new Context($this->currentUser, $this->currentStore->getStore());
      $adjustment_types = array_filter($this->getSetting('adjustment_types'));
      /** @var \Drupal\commerce_product\Entity\Product $product */
      $product = $items->getEntity();
      $product_variations = $product->getVariations();

      $cache = new CacheableMetadata();
      $cache->addCacheableDependency($product);
      $prices = [];
      foreach ($product_variations as $product_variation) {
        $cache->addCacheableDependency($product_variation);
        $result = $this->priceCalculator->calculate($product_variation, 1, $context, $adjustment_types);
        $prices[] = $result->getCalculatedPrice();
      }
      /** @var \Drupal\commerce_price\Price[] $prices */
      $min = $max = reset($prices);
      foreach ($prices as $price) {
        if ($price->greaterThan($max)) {
          $max = $price;
        }
        if ($price->lessThan($min)) {
          $min = $price;
        }
      }

      /** @var \Drupal\commerce_price\Entity\CurrencyInterface $currency */
      $currency = $this->currencyStorage->load($min->getCurrencyCode());
      $min_price_string = $currency->getSymbol() . round($min->getNumber(), 2);

      if ($min->lessThan($max)) {
        $max_price_string = $currency->getSymbol() . round($max->getNumber(), 2);
      }

      $elements[0] = [
        '#plain_text' => !empty($max_price_string) ? $min_price_string . ' - ' . $max_price_string : $min_price_string,
      ];
      $cache->applyTo($elements[0]);
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    $entity_type = $field_definition->getTargetEntityTypeId();
    $field_name = $field_definition->getName();

    return $entity_type == 'commerce_product' && $field_name == 'variations';
  }

}
