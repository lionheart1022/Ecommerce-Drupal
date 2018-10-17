<?php

namespace Drupal\dcom_shopify_migrate;

/**
 * Interface CustomerOrdersResolverInterface.
 *
 * @package Drupal\dcom_shopify_migrate
 */
interface CustomerOrdersResolverInterface {

  /**
   * Get customer orders by the customer ID.
   *
   * @param int $customer_id
   *   The customer ID.
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
  public function getCustomerOrders($customer_id, $shop_domain, $api_key, $password, $shared_secret);

}
