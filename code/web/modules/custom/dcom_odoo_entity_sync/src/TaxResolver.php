<?php

namespace Drupal\dcom_odoo_entity_sync;

use Drupal\odoo_api\OdooApi\ClientInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\odoo_api\OdooApi\Util\ResponseCacheTrait;

/**
 * The tax resolver service.
 *
 * @package Drupal\dcom_odoo_entity_sync
 */
class TaxResolver implements TaxResolverInterface {

  const ODOO_TAX_MODEL = 'account.tax';

  use ResponseCacheTrait;

  /**
   * Drupal\odoo_api\OdooApi\ClientInterface definition.
   *
   * @var \Drupal\odoo_api\OdooApi\ClientInterface
   */
  protected $odooApiApiClient;

  /**
   * TaxResolver constructor.
   *
   * @param \Drupal\odoo_api\OdooApi\ClientInterface $odoo_api_api_client
   *   The Odoo API client.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_default
   *   The cache default service.
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cache_tags_invalidator
   *   The cache tags invalidator service.
   */
  public function __construct(ClientInterface $odoo_api_api_client, CacheBackendInterface $cache_default, CacheTagsInvalidatorInterface $cache_tags_invalidator) {
    $this->odooApiApiClient = $odoo_api_api_client;
    $this->setCacheOptions($cache_default, $cache_tags_invalidator, 'dcom_odoo_entity_sync.tax_resolver');
  }

  /**
   * {@inheritdoc}
   */
  public function findOdooTaxIdsByPercentage($tax_percentage) {
    return $this->cacheResponse('tax_ids_percentage_' . $tax_percentage, function () use ($tax_percentage) {
      $filters = [
        // Diamond CBD company ID - 1.
        ['company_id', '=', DcomEntitySyncInterface::DIAMONDCBD_ODOO_COMPANY_ID],
        ['type_tax_use', '=', 'sale'],
        ['amount', '=', $tax_percentage],
        ['amount_type', '=', 'percent'],
      ];

      $results = $this->odooApiApiClient->searchRead(static::ODOO_TAX_MODEL, $filters, ['id']);
      return array_map(function ($item) {
        return $item['id'];
      }, $results);
    });
  }

  /**
   * {@inheritdoc}
   */
  public function createOdooTax($percentage) {
    $percentage_string = number_format($percentage, 2) . '%';
    $fields = [
      'name' => 'Tax ' . $percentage_string,
      'description' => $percentage_string,
      // Tax Computation - Percentage of Price.
      'amount_type' => 'percent',
      'amount' => $percentage,
      'company_id' => DcomEntitySyncInterface::DIAMONDCBD_ODOO_COMPANY_ID,
      'type_tax_use' => 'sale',
    ];
    $odoo_id = $this->odooApiApiClient->create(static::ODOO_TAX_MODEL, $fields);
    $this->invalidateCache();
    return $odoo_id;
  }

}
