<?php

namespace Drupal\dcom_cart\Plugin\Field\FieldWidget;

use Drupal\commerce_product\Plugin\Field\FieldWidget\ProductVariationAttributesWidget;
use Drupal\commerce_product\ProductAttributeFieldManagerInterface;
use Drupal\commerce_product\ProductVariationAttributeMapperInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\dcom_back_in_stock\BackInStockClassService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Plugin implementation of the 'commerce_product_variation_attributes' widget.
 *
 * @FieldWidget(
 *   id = "dcom_commerce_product_variation_attributes",
 *   label = @Translation("DCOM Product variation attributes"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class DcomProductVariationAttributesWidget extends ProductVariationAttributesWidget implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new ProductVariationAttributesWidget object.
   *
   * @param string $plugin_id
   *   The plugin_id for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\commerce_product\ProductAttributeFieldManagerInterface $attribute_field_manager
   *   The product attribute field manager.
   * @param \Drupal\commerce_product\ProductVariationAttributeMapperInterface $variation_attribute_mapper
   *   The product variation attribute mapper.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\dcom_back_in_stock\BackInStockClassService $back_in_stock
   *   Back in stock service.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, EntityTypeManagerInterface $entity_type_manager, EntityRepositoryInterface $entity_repository, ProductAttributeFieldManagerInterface $attribute_field_manager, ProductVariationAttributeMapperInterface $variation_attribute_mapper, RequestStack $request_stack, BackInStockClassService $back_in_stock) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings, $entity_type_manager, $entity_repository, $attribute_field_manager, $variation_attribute_mapper);

    $this->requestStack = $request_stack;
    $this->backInStock = $back_in_stock;
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
      $configuration['third_party_settings'],
      $container->get('entity_type.manager'),
      $container->get('entity.repository'),
      $container->get('commerce_product.attribute_field_manager'),
      $container->get('commerce_product.variation_attribute_mapper'),
      $container->get('request_stack'),
      $container->get('dcom_back_in_stock.back_in_stock_class')
    );
  }

  /**
   * Gets the default variation for the widget.
   *
   * @param \Drupal\commerce_product\Entity\ProductInterface $product
   *   The product.
   * @param array $variations
   *   An array of available variations.
   *
   * @return \Drupal\commerce_product\Entity\ProductVariationInterface
   *   The default variation.
   */
  protected function getDefaultVariation(ProductInterface $product, array $variations) {
    $current_request = $this->requestStack->getCurrentRequest();
    if ($variation_id = $current_request->query->get('v')) {
      if (in_array($variation_id, array_keys($variations))) {
        if ($variations[$variation_id]->isActive() && $variations[$variation_id]->access('view')) {
          $selected_variation = $variations[$variation_id];
        }
      }
    }

    if (empty($selected_variation)) {
      // Select first variation by default.
      $selected_variation = reset($variations);

      // Then, check availability of variations.
      foreach ($variations as $variation) {
        if ($variation->isActive() && $variation->access('view')) {
          $stock_policy = $this->backInStock->getStockPolicy($variation);
          if ($stock_policy == BackInStockClassService::AVAIL_FORCE_AVAIL
            || ($stock_policy == BackInStockClassService::AVAIL_ODOO && !empty($variation->field_product_availability->value))) {
            $selected_variation = $variation;
            break;
          }
        }
      }
    }

    $selected_variation = $this->entityRepository->getTranslationFromContext($selected_variation, $product->language()->getId());

    // The returned variation must also be enabled.
    if (!in_array($selected_variation, $variations)) {
      $selected_variation = reset($variations);
    }
    return $selected_variation;
  }

}
