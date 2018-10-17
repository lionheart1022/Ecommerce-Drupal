<?php

namespace Drupal\shopify_migrate\Plugin\migrate\source;

/**
 * Provides a Shopify Product Tag migrate source.
 *
 * Usage example:
 *
 * @code
 * source:
 *   plugin: shopify_product_tag
 *   shopify:
 *     shop_domain: mydomain.myshopify.com
 *     api_key: API_KEY
 *     password: PASSWORD
 *     shared_secret: SHARED_SECRET
 * @endcode
 *
 * @MigrateSource(
 *  id = "shopify_product_tag"
 * )
 */
class ProductTag extends Base {

  /**
   * Tags aren't countable since they aren't unique.
   *
   * {@inheritdoc}
   */
  protected $skipCount = TRUE;

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'name' => [
        'type' => 'string_long',
      ],
    ];
  }

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
      'name' => $this->t('Tag name'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function initializeIterator() {
    foreach (parent::initializeIterator() as $product) {
      if (isset($product['tags'])) {
        $tags = array_filter(array_map('trim', explode(',', $product['tags'])));
        foreach ($tags as $tag) {
          yield [
            'name' => $tag,
          ];
        }
      }
    }
  }

}
