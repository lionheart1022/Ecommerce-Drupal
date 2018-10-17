<?php

namespace Drupal\shopify_migrate\Plugin\migrate\source;

/**
 * Provides a Shopify Product Variant migrate source.
 *
 * Usage example:
 *
 * @code
 * source:
 *   plugin: shopify_product_variant
 *   shopify:
 *     shop_domain: mydomain.myshopify.com
 *     api_key: API_KEY
 *     password: PASSWORD
 *     shared_secret: SHARED_SECRET
 * @endcode
 *
 * @MigrateSource(
 *  id = "shopify_product_variant"
 * )
 */
class ProductVariant extends Base {

  /**
   * Variants aren't countable since they are properties of products.
   *
   * {@inheritdoc}
   */
  protected $skipCount = TRUE;

  /**
   * {@inheritdoc}
   */
  protected function getShopifyResource() {
    return 'products';
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'id' => $this->t('Product Variant ID'),
      'title' => $this->t('Title'),
      'created_at' => $this->t('Creation date'),
      'updated_at' => $this->t('Update date'),
      'barcode' => $this->t('Barcode'),
      'grams' => $this->t('Weight in grams'),
      'weight' => $this->t('Weight the unit system specified with weight_unit'),
      'weight_unit' => $this->t('The unit of measurement that applies to weight'),
      // @TODO: Implement me.
      'options' => $this->t('Options'),
      'position' => $this->t('Product variant position'),
      'price' => $this->t('Price'),
      'product_id' => $this->t('Product ID'),
      'sku' => $this->t('SKU'),
      'tags' => $this->t('Comma-separated product tags'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function initializeIterator() {
    foreach (parent::initializeIterator() as $product) {
      if (empty($product['variants'])) {
        continue;
      }
      foreach ($product['variants'] as $variant) {
        // Extract variants from products.
        $variant_array = (array) $variant;
        if (isset($product['tags'])) {
          $variant_array['tags'] = $product['tags'];
        }
        if (isset($product['options'])) {
          $variant_array['options'] = $product['options'];
        }
        yield $variant_array;
      }
    }
  }

}
