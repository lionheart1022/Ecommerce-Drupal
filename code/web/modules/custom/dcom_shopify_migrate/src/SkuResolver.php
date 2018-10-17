<?php

namespace Drupal\dcom_shopify_migrate;

use Shopify\PrivateApp;

/**
 * SKU resolver service class.
 */
class SkuResolver implements SkuResolverInterface {

  protected $skuCache;

  /**
   * {@inheritdoc}
   */
  public function getSku($shopify_variant_id, $shop_domain, $api_key, $password, $shared_secret) {
    if (!isset($this->skuCache[$shop_domain][$api_key])) {
      $this->skuCache[$shop_domain][$api_key] = $this->fetchVariants($this->shopifyApiClient($shop_domain, $api_key, $password, $shared_secret));
    }

    $cache = &$this->skuCache[$shop_domain][$api_key];
    if (isset($cache[$shopify_variant_id])) {
      return $cache[$shopify_variant_id];
    }
    else {
      return NULL;
    }
  }

  /**
   * Fetch products variants ID -> SKU mapping.
   *
   * @param \Shopify\PrivateApp $api
   *   Shopify API client.
   *
   * @return array
   *   Map of Shopify product variant ID -> SKU.
   */
  protected function fetchVariants(PrivateApp $api) {
    $data = [];

    foreach ($api->getResourcePager('products') as $product) {
      if (empty($product->variants)) {
        continue;
      }
      foreach ($product->variants as $variant) {
        // Extract variants from products.
        $data[$variant->id] = $variant->sku;
      }
    }

    return $data;
  }

  /**
   * Get Shopify API client.
   *
   * @param string $shop_domain
   *   Shop domain.
   * @param string $api_key
   *   Shopify API key.
   * @param string $password
   *   Shopify password.
   * @param string $shared_secret
   *   Shopify shared secret.
   *
   * @return \Shopify\PrivateApp
   *   Shopify API client.
   */
  protected function shopifyApiClient($shop_domain, $api_key, $password, $shared_secret) {
    return new PrivateApp($shop_domain, $api_key, $password, $shared_secret);
  }

}
