<?php

namespace Drupal\dcom_shopify_migrate;

use Shopify\PrivateApp;

/**
 * The CustomerOrdersResolver service.
 *
 * @package Drupal\dcom_shopify_migrate
 */
class CustomerOrdersResolver implements CustomerOrdersResolverInterface {

  /**
   * An orders of orders per customer.
   *
   * @var array
   */
  protected $ordersCache;

  /**
   * {@inheritdoc}
   */
  public function getCustomerOrders($customer_id, $shop_domain, $api_key, $password, $shared_secret) {
    if (!isset($this->ordersCache[$shop_domain][$api_key])) {
      $this->ordersCache[$shop_domain][$api_key] = $this->fetchOrdersByCustomer($this->shopifyApiClient($shop_domain, $api_key, $password, $shared_secret));
    }

    $cache = &$this->ordersCache[$shop_domain][$api_key];
    if (isset($cache[$customer_id])) {
      return $cache[$customer_id];
    }

    return NULL;
  }

  /**
   * Fetches orders and groups it by customer.
   *
   * @param \Shopify\PrivateApp $api
   *   The Shopify API client.
   *
   * @return array
   *   Map of Shopify customer ID -> an array of orders.
   */
  protected function fetchOrdersByCustomer(PrivateApp $api) {
    $data = [];

    foreach ($api->getResourcePager('orders') as $order) {
      $data[$order->customer->id][] = $order->id;
    }

    return $data;
  }

  /**
   * Gets Shopify API client.
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
