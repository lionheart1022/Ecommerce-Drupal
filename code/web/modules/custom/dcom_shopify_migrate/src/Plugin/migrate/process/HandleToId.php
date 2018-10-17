<?php

namespace Drupal\dcom_shopify_migrate\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Shopify\PrivateApp;

/**
 * Migrate Shopify product ids.
 *
 * @MigrateProcessPlugin(
 *   id = "dcom_shopify_handle_to_id"
 * )
 */
class HandleToId extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    foreach ($this->productsMap() as $product_handle => $id) {
      if ($value == $product_handle) {
        return $id;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function productsMap() {
    if (!isset($this->productsMap)) {
      $this->productsMap = $this->fetchProductsMap();
    }

    return $this->productsMap;
  }

  /**
   * Initializes Shopify API client.
   *
   * @return \Shopify\PrivateApp
   *   Shopify API client.
   */
  protected function getApiClient() {
    if (!isset($this->apiClient)) {
      $this->apiClient = new PrivateApp('diamondcbd.myshopify.com', '221f9ce80f304929059c9588617f88b6', '1529dce859e0c59d2a061452ecdab421', '72be699ffe843f37a3aed4cf9e456d4b');
    }

    return $this->apiClient;
  }

  /**
   * {@inheritdoc}
   */
  protected function fetchProductsMap() {
    $map = [];
    foreach ($this->getApiClient()->getResourcePager('products') as $object) {
      $map[$object->handle] = $object->id;
    }
    return $map;
  }

}
