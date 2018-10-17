<?php

namespace Drupal\shopify_migrate\Plugin\migrate\source;

use InvalidArgumentException;

/**
 * Provides a Shopify Product migrate source.
 *
 * Usage example:
 *
 * @code
 * source:
 *   plugin: shopify_product
 *   shopify:
 *     shop_domain: mydomain.myshopify.com
 *     api_key: API_KEY
 *     password: PASSWORD
 *     shared_secret: SHARED_SECRET
 *     fetch_metafields: TRUE
 * @endcode
 *
 * @MigrateSource(
 *  id = "shopify_product"
 * )
 */
class Product extends Base {

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
      'id' => $this->t('Product ID'),
      'body_html' => $this->t('Product description'),
      'created_at' => $this->t('Creation date'),
      // @TODO: Add more fields.
      'tags' => $this->t('Comma-separated product tags'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function initializeIterator() {
    // Add products variants IDs field.
    foreach (parent::initializeIterator() as $product) {
      $variants = [];
      $images = [];
      if (!empty($product['variants'])) {
        foreach ($product['variants'] as $variant) {
          $variants[] = $variant->id;
        }
      }
      if (!empty($product['images'])) {
        foreach ($product['images'] as $image) {
          $images[] = $image->id;
        }
      }
      $product['variants'] = $variants;
      $product['images'] = $images;

      // Add metafields.
      if (!empty($this->configuration['shopify']['fetch_metafields'])) {
        $product['metafields'] = $this->getProductMetaFields($product['id']);
      }

      yield $product;
    }
  }

  /**
   * Get product metafields.
   *
   * @param int $product_id
   *   Product ID.
   *
   * @return array
   *   Array of metafields.
   *
   * @throws \Drupal\migrate\MigrateException
   */
  protected function getProductMetaFields($product_id) {
    if (!is_numeric($product_id)) {
      throw new InvalidArgumentException('Non-numeric product ID.');
    }
    $metafields = [];
    foreach ($this->getApiClient()->getResourcePager('products/' . $product_id . '/metafields') as $item) {
      $item = (array) $item;
      $metafields[$item['key']][] = $item;
    }
    return $metafields;
  }

}
