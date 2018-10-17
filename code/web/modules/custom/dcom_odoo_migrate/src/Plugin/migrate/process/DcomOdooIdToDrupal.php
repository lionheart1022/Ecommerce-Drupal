<?php

namespace Drupal\dcom_odoo_migrate\Plugin\migrate\process;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Transforms Odoo ID to Drupal ID.
 *
 * @code
 * process:
 *   uid:
 *     plugin: dcom_odoo_id_to_drupal
 *     migration: 'migration_id'
 *     source: partner_id
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "dcom_odoo_id_to_drupal"
 * )
 */
class DcomOdooIdToDrupal extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The migration plugin manager.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManagerInterface
   */
  protected $migrationPluginManager;

  /**
   * Constructs a DcomOdooIdToDrupal object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\migrate\Plugin\MigrationPluginManagerInterface $migration_plugin_manager
   *   The migration plugin manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationPluginManagerInterface $migration_plugin_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->migrationPluginManager = $migration_plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.migration')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (empty($this->configuration['migration'])) {
      throw new MigrateException('Odoo ID to Drupal ID plugin is missing the migration configuration.');
    }

    $migrations = $this->migrationPluginManager->createInstances([$this->configuration['migration']]);
    $migration = reset($migrations);
    $destination_ids = $migration->getIdMap()->lookupDestinationIds([$value]);

    if ($destination_ids) {
      $destination_id = reset($destination_ids);
      $destination_id = is_array($destination_id) ? reset($destination_id) : NULL;
    }

    return empty($destination_id) ? NULL : $destination_id;
  }

}
