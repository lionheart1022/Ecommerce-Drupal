<?php

namespace Drupal\shopify_migrate\Plugin\migrate\source;

/**
 * Provides a Shopify Product Image migrate source.
 *
 * Usage example:
 *
 * @code
 * source:
 *   plugin: shopify_product_image
 *   shopify:
 *     shop_domain: mydomain.myshopify.com
 *     api_key: API_KEY
 *     password: PASSWORD
 *     shared_secret: SHARED_SECRET
 * @endcode
 *
 * @MigrateSource(
 *  id = "shopify_product_image"
 * )
 */
class ProductImage extends Base {

  /**
   * Images aren't countable since they are properties of products.
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
      'id' => $this->t('Image ID'),
      'product_id' => $this->t('Product ID'),
      'created_at' => $this->t('Creation date'),
      'updated_at' => $this->t('Update date'),
      'position' => $this->t('Product variant position'),
      'width' => $this->t('Width'),
      'height' => $this->t('Height'),
      'src' => $this->t('Source'),
      'variant_ids' => $this->t('Product variants'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function initializeIterator() {
    foreach (parent::initializeIterator() as $product) {
      if (empty($product['images'])) {
        continue;
      }
      foreach ($product['images'] as $image) {
        // Extract variants from products.
        yield (array) $image;
      }
    }
  }

}
