<?php

namespace Drupal\dcom_shopify_migrate;

/**
 * Interface SkuResolverInterface.
 */
interface SkuResolverInterface {

  /**
   * Get product SKU by Shopify product variant ID.
   *
   * @param int $shopify_variant_id
   *   Shopify product variant ID.
   * @param string $shop_domain
   *   Shop domain.
   * @param string $api_key
   *   Shopify API key.
   * @param string $password
   *   Shopify password.
   * @param string $shared_secret
   *   Shopify shared secret.
   *
   * @return string|null
   *   Product variant SKU or NULL.
   */
  public function getSku($shopify_variant_id, $shop_domain, $api_key, $password, $shared_secret);

}
