<?php

namespace Drupal\dcom_odoo_inventory;

use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\dcom_odoo_entity_sync\Util\MigratedEntityTrait;
use Drupal\dcom_odoo_inventory\Exception\MissingOdooDataException;
use Drupal\odoo_api\OdooApi\ClientInterface;
use UnexpectedValueException;

/**
 * Class InventorySync.
 */
class InventorySync implements InventorySyncInterface {

  use MigratedEntityTrait;

  /**
   * Odoo API client service.
   *
   * @var \Drupal\odoo_api\OdooApi\ClientInterface
   */
  protected $odoo;

  /**
   * Product variations storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $variationStorage;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Variations SQL tables mapping.
   *
   * @var \Drupal\Core\Entity\Sql\TableMappingInterface
   */
  protected $tableMapping;

  /**
   * Odoo query cache.
   *
   * @var array
   */
  protected $queryCache;

  /**
   * Constructs a new InventorySync object.
   *
   * @param \Drupal\odoo_api\OdooApi\ClientInterface $odoo_api_api_client
   *   Odoo API client.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\Core\Database\Connection $database
   *   Database connection.
   */
  public function __construct(ClientInterface $odoo_api_api_client, EntityTypeManagerInterface $entity_type_manager, Connection $database) {
    $this->odoo = $odoo_api_api_client;
    $this->variationStorage = $entity_type_manager->getStorage('commerce_product_variation');
    $this->database = $database;

    if (!($this->variationStorage instanceof SqlContentEntityStorage)) {
      throw new UnexpectedValueException('Not SQL storage.');
    }

    $this->tableMapping = $this->variationStorage->getTableMapping();
  }

  /**
   * {@inheritdoc}
   */
  public function queryProductVariantInventory(ProductVariationInterface $entity) {
    $result = $this->odoo->read('product.product', [$this->getVariationOdooId($entity)], ['virtual_available']);
    if (!isset($result[0]['virtual_available'])) {
      throw new MissingOdooDataException('Product variation ' . $entity->id() . ' is synced, Odoo database entry is missing.');
    }

    return $result[0]['virtual_available'];
  }

  /**
   * Get list of product variants which are no longer available.
   *
   * @return \Drupal\commerce_product\Entity\ProductVariationInterface[]
   *   List of product variants available at Drupal but not available at Odoo.
   */
  public function queryVariantsNotAvailableAnymore() {
    $ids = $this->getVariationsIds(TRUE);
    $variations_not_available = [];

    // Odoo handles 2000 items per query really well.
    foreach (array_chunk($ids, 2000, TRUE) as $chunk) {
      $variations_not_available = array_merge($variations_not_available, $this->queryOdooVariantsByStockStatus($chunk, FALSE));
    }

    // Product variants which may be produced are available.
    $variations_not_available = array_diff($variations_not_available, $this->queryOdooVariantsMayBeProduced($variations_not_available));

    return $variations_not_available ?
      $this
        ->variationStorage
        ->loadMultiple(array_keys(array_intersect($ids, $variations_not_available))) :
      [];
  }

  /**
   * Get list of product variants which became available.
   *
   * @return \Drupal\commerce_product\Entity\ProductVariationInterface[]
   *   List of product variants not available at Drupal but available at Odoo.
   */
  public function queryVariantsNowAvailable() {
    // IDs are Drupal ID => Odoo ID.
    $ids = $this->getVariationsIds(FALSE);
    $variations_now_available = [];

    // Odoo handles 2000 items per query really well.
    foreach (array_chunk($ids, 2000, TRUE) as $ids_chunk) {
      // Query products where virtual_available > 0.
      // These are products which are currently available in stock.
      $variations_now_available = array_merge($variations_now_available, $this->queryOdooVariantsByStockStatus($ids_chunk, TRUE));

      // Append products which may be produced.
      $variations_now_available = array_merge($variations_now_available, $this->queryOdooVariantsMayBeProduced(array_diff($ids_chunk, $variations_now_available)));
    }

    return $variations_now_available ?
      $this
        ->variationStorage
        ->loadMultiple(array_keys(array_intersect($ids, $variations_now_available))) :
      [];
  }

  /**
   * Get product variation sync ID.
   *
   * @param \Drupal\commerce_product\Entity\ProductVariationInterface $entity
   *   Product variation entity.
   *
   * @return int
   *   Odoo sync ID.
   *
   * @throws \Drupal\dcom_odoo_entity_sync\Exception\MigrateLookupException
   *   Odoo ID lookup error.
   */
  protected function getVariationOdooId(ProductVariationInterface $entity) {
    return $this->getMigratedEntityOdooId($entity, 'product.product');
  }

  /**
   * Query Odoo IDs of product variations.
   *
   * @param bool $avail
   *   TRUE to query available variations, FALSE otherwise.
   *
   * @return array
   *   Array of entity ID => Odoo ID.
   */
  protected function getVariationsIds($avail) {
    $product_variants_migration = $this->getOdooMigration('odoo_product_variants');
    /** @var \Drupal\migrate\Plugin\migrate\id_map\Sql $id_map */
    $id_map = $product_variants_migration->getIdMap();
    $sync_id_table = $id_map->mapTableName();
    $avail_table = $this
      ->tableMapping
      ->getFieldTableName('field_product_availability');

    $availability_column_names = $this->tableMapping->getColumnNames('field_product_availability');
    $availability_column = 'a.' . $availability_column_names['value'];

    $query = $this->database->select($sync_id_table, 's');
    $query->fields('s', ['destid1', 'sourceid1']);

    if ($avail) {
      $query->join($avail_table, 'a', 's.destid1 = a.entity_id');
      $query->condition($availability_column, 1);
      $query->condition('a.deleted', 0);
    }
    else {
      $query->leftJoin($avail_table, 'a', 's.destid1 = a.entity_id');
      $or = $query->orConditionGroup();
      $or
        ->condition($availability_column, NULL, 'IS NULL')
        ->condition($availability_column, 0);
      $query->condition($or);
    }

    return $query->execute()->fetchAllKeyed();
  }

  /**
   * Gets IDs of Odoo product variants by stock status.
   *
   * @param array $odoo_ids
   *   Array of Odoo IDs.
   * @param bool $in_stock
   *   Whether items in stock should be queried.
   *
   * @return array
   *   Array of Odoo IDs of product variants (product.product) which are
   *   currently in/out of stock.
   */
  protected function queryOdooVariantsByStockStatus(array $odoo_ids, $in_stock) {
    $filter = [];
    if ($in_stock) {
      $filter[] = ['virtual_available', '>', 0];
    }
    else {
      $filter[] = ['virtual_available', '<=', 0];
    }
    $filter[] = ['id', 'in', array_values($odoo_ids)];
    return $this->odoo->search('product.product', $filter);
  }

  /**
   * Get IDs of Odoo product variants which may be produced.
   *
   * @param array $odoo_ids
   *   Array of Odoo IDs of products.
   *
   * @return array
   *   Array of Odoo IDs of product variants (product.product) which aren't in
   *   stock but may be produced.
   */
  protected function queryOdooVariantsMayBeProduced(array $odoo_ids) {
    if (empty($odoo_ids)) {
      return [];
    }

    $boms = $this->queryBoms($odoo_ids);

    $materials_to_query = [];
    foreach ($boms as $bom) {
      foreach ($bom['bom_lines'] as $bom_line) {
        if (!empty($bom_line['product_id'][0])) {
          $materials_to_query[$bom_line['product_id'][0]] = $bom_line['product_id'][0];
        }
      }
    }
    if (empty($materials_to_query)) {
      return [];
    }

    $this->cacheableRead('product.product',
      array_map('intval', array_values($materials_to_query)),
      ['id', 'virtual_available']
    );

    $may_be_produced = [];
    foreach ($boms as $product_id => $bom) {
      if ($this->bomMaterialsAvailable($bom)) {
        $may_be_produced[] = $product_id;
      }
    }

    return $may_be_produced;
  }

  /**
   * Query bills of materials for given products (variants).
   *
   * @param array $odoo_ids
   *   Array of Odoo IDs.
   *
   * @return array
   *   Array of BoMs, keyed by Odoo product ID.
   */
  protected function queryBoms(array $odoo_ids) {
    // Query products to find Bills of Materials.
    if (!($bom_ids = $this->getOdooProductsBomIds($odoo_ids))) {
      return [];
    }

    // @TODO: Only query BoMs which are Kit.
    $bom_lines_to_query = [];
    $boms = [];
    $bom_fields = ['id', 'bom_line_ids', 'product_id', 'type'];
    foreach ($this->cacheableRead('mrp.bom', array_map('intval', $bom_ids), $bom_fields) as $bom) {
      $bom_product_id = $bom['product_id'][0];
      // Only check BoMs which are 'phantom' which is 'Kit' in UI.
      if (in_array($bom_product_id, $odoo_ids)
        && $bom['type'] == 'phantom') {
        $boms[$bom_product_id] = $bom;
        $bom_lines_to_query = array_merge($bom_lines_to_query, $bom['bom_line_ids']);
      }
    }

    if (!($bom_lines = $this->cacheableRead('mrp.bom.line',
      array_map('intval', array_values($bom_lines_to_query)),
      ['id', 'product_id']
    ))) {
      return [];
    }

    foreach ($boms as &$bom) {
      $bom['bom_lines'] = [];
      foreach ($bom['bom_line_ids'] as $bom_line_id) {
        if (isset($bom_lines[$bom_line_id])) {
          $bom['bom_lines'][$bom_line_id] = $bom_lines[$bom_line_id];
        }
      }
    }

    return $boms;
  }

  /**
   * Get IDs of BoMs of given products.
   *
   * @param array $odoo_ids
   *   Odoo IDs of products (variants).
   *
   * @return array
   *   Odoo IDs of BoMs for given products.
   */
  protected function getOdooProductsBomIds(array $odoo_ids) {
    $bom_ids = [];
    $products = $this->cacheableRead(
      'product.product',
      array_map('intval', array_values($odoo_ids)),
      ['id', 'bom_ids']
    );

    foreach ($products as $product) {
      if (empty($product['bom_ids'])) {
        // Product doesn't have BoMs; skip.
        continue;
      }
      $bom_ids = array_merge($bom_ids, $product['bom_ids']);
    }

    return $bom_ids;
  }

  /**
   * Cache wrapper around Odoo read() method.
   *
   * @param string $model_name
   *   Odoo model name.
   * @param array $ids
   *   Array of Odoo database identifiers, as returned by search().
   * @param array|null $fields
   *   Array of fields to fetch. NULL means all fields.
   *
   * @return array
   *   Odoo objects, keyed by object ID.
   *
   * @see \Drupal\odoo_api\OdooApi\ClientInterface::read
   */
  protected function cacheableRead($model_name, array $ids, $fields = NULL) {
    $fields_key = $fields ? implode(':', $fields) : 'ALL';
    $combined_ids = array_combine($ids, $ids);

    // Initialize query cache.
    if (!isset($this->queryCache[$model_name][$fields_key])) {
      $this->queryCache[$model_name][$fields_key] = [];
    }

    if ($missing_ids = array_diff_key($combined_ids, $this->queryCache[$model_name][$fields_key])) {
      // Query Odoo 2000 items at once.
      foreach (array_chunk($missing_ids, 5000) as $chunk) {
        foreach ($this->odoo->read($model_name, array_values($chunk), $fields) as $object) {
          $this->queryCache[$model_name][$fields_key][$object['id']] = $object;
        }
      }
    }

    return array_intersect_key($this->queryCache[$model_name][$fields_key], $combined_ids);
  }

  /**
   * Check if all materials in BoM are available.
   *
   * @param array $bom
   *   Bill of Materials from Odoo.
   *
   * @return bool
   *   TRUE if all materials are in stock, FALSE otherwise.
   */
  protected function bomMaterialsAvailable(array $bom) {
    $materials = $this->queryBomMaterials($bom);

    if (empty($bom['bom_lines'])) {
      // No BoM lines; skip this product, we don't know how to produce it.
      return FALSE;
    }
    foreach ($bom['bom_lines'] as $bom_line) {
      if (empty($bom_line['product_id'][0])) {
        // No material specified, skip this product.
        return FALSE;
      }
      $material_id = $bom_line['product_id'][0];
      if (empty($materials[$material_id]['virtual_available'])
        || $materials[$material_id]['virtual_available'] < 0) {
        // Material not available, skip this product.
        return FALSE;
      }
    }

    // All materials are available.
    return TRUE;
  }

  /**
   * Get list of materials for BoM.
   *
   * @param array $bom
   *   Bill of Materials from Odoo.
   *
   * @return array
   *   An array of Odoo product.product objects used in given BoM.
   *   Only 'id' and 'virtual_available' fields are queried.
   */
  protected function queryBomMaterials(array $bom) {
    $materials_to_query = [];
    foreach ($bom['bom_lines'] as $bom_line) {
      if (!empty($bom_line['product_id'][0])) {
        $materials_to_query[$bom_line['product_id'][0]] = $bom_line['product_id'][0];
      }
    }
    if (empty($materials_to_query)) {
      $materials = [];
    }
    else {
      $materials = $this->cacheableRead('product.product',
        array_map('intval', array_values($materials_to_query)),
        ['id', 'virtual_available']
      );
    }

    return $materials;
  }

}
