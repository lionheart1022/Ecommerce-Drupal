<?php

namespace Drupal\dcom_odoo_migrate;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Entity\EntityInterface;
use Drupal\odoo_api_migrate\Plugin\migrate\source\OdooApi;
use Drupal\migrate\Row;

/**
 * Migration lookup trait.
 *
 * Used to lookup Odoo ID of imported entities.
 */
trait MigrateIdMapTrait {

  /**
   * Odoo migrations.
   *
   * @var \Drupal\migrate\Plugin\Migration[]
   */
  private $allOdooMigrations;

  /**
   * Drupal\migrate\Plugin\MigrationPluginManagerInterface definition.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManagerInterface
   */
  private $pluginManagerMigration;

  /**
   * Lookup Odoo object IDs in Migrate data.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity object.
   * @param string $odoo_model
   *   Odoo object model name.
   *
   * @return array
   *   Array of Odoo IDs.
   */
  protected function lookupOdooIds(EntityInterface $entity, $odoo_model) {
    $ids = [];
    foreach ($this->getOdooMigrations($entity->getEntityTypeId(), $odoo_model) as $migration) {
      $source_id = $migration->getIdMap()->lookupSourceID([$entity->getEntityType()->getKey('id') => $entity->id()]);
      if (!empty($source_id['id'])) {
        $ids[] = $source_id['id'];
      }
    }
    return $ids;
  }

  /**
   * Save Migrate ID map for exported entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Exported entity object.
   * @param string $odoo_model
   *   Odoo object model name.
   * @param int $odoo_id
   *   Odoo object ID.
   */
  protected function saveIdMap(EntityInterface $entity, $odoo_model, $odoo_id) {
    foreach ($this->getOdooMigrations($entity->getEntityTypeId(), $odoo_model) as $migration) {
      $stub_row = new Row(['id' => $odoo_id], $migration->getSourcePlugin()->getIds(), TRUE);
      $migration->getIdMap()->saveIdMapping($stub_row, [$entity->id()]);
    }
  }

  /**
   * Fetch Odoo migrations for given entity type.
   *
   * @return \Drupal\migrate\Plugin\Migration[]
   *   Migration plugins.
   *
   * @TODO: Add static cache.
   */
  private function getOdooMigrations($entity_type, $odoo_model) {
    $migrations = [];
    foreach ($this->getAllOdooMigrations() as $migration) {
      $source = $migration->getSourceConfiguration();

      // @TODO: Find better way to check entity type. Probably we should
      // @TODO: get destination plugin object and then compare; I didn't bother.
      $destination = $migration->getDestinationConfiguration();
      if (!empty($source['model'])
        && $source['model'] == $odoo_model
        && $destination['plugin'] == 'entity:' . $entity_type) {
        $migrations[] = $migration;
      }
    }
    return $migrations;
  }

  /**
   * Fetches the Odoo migration for the given id.
   *
   * @param string $migration_id
   *   The ID of the migration.
   *
   * @return \Drupal\migrate\Plugin\Migration|bool
   *   The migration plugin or FALSE if not exist.
   */
  private function getOdooMigration($migration_id) {
    $migrations = $this->getAllOdooMigrations();
    if (isset($migrations[$migration_id])) {
      return $migrations[$migration_id];
    }

    return FALSE;
  }

  /**
   * Get all Odoo migrations.
   *
   * @return \Drupal\migrate\Plugin\Migration[]
   *   List of all Odoo migrations.
   */
  private function getAllOdooMigrations() {
    if (!isset($this->allOdooMigrations)) {
      $this->allOdooMigrations = [];
      $definitions = $this->getMigrationPluginManager()->getDefinitions();
      try {
        foreach ($this->getMigrationPluginManager()->createInstances(array_keys($definitions)) as $migration) {
          if ($migration->getSourcePlugin() instanceof OdooApi) {
            $this->allOdooMigrations[$migration->id()] = $migration;
          }
        }
      }
      catch (PluginException $e) {
        // @TODO: Watchdog?..
      }
    }

    return $this->allOdooMigrations;
  }

  /**
   * Get migration plugin manager service.
   *
   * @return \Drupal\migrate\Plugin\MigrationPluginManagerInterface
   *   Migration plugin manager service.
   */
  private function getMigrationPluginManager() {
    if (!isset($this->pluginManagerMigration)) {
      $this->pluginManagerMigration = \Drupal::service('plugin.manager.migration');
    }

    return $this->pluginManagerMigration;
  }

}
