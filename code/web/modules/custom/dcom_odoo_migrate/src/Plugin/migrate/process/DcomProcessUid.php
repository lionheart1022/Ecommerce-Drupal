<?php

namespace Drupal\dcom_odoo_migrate\Plugin\migrate\process;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Attaches Odoo user id to drupal user id if the latest one has no mapping.
 *
 * For some reason we have entities on Odoo and Drupal with the same email
 * but without mapping. Map it with this process plugin.
 *
 * @code
 * process:
 *   uid:
 *     -
 *       plugin: entity_lookup
 *       value_key: mail
 *       entity_type: user
 *       ignore_case: true
 *       source:
 *         - email
 *     -
 *       plugin: dcom_process_uid
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "dcom_process_uid",
 *   handle_multiples = TRUE
 * )
 */
class DcomProcessUid extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The current migration.
   *
   * @var \Drupal\migrate\Plugin\MigrationInterface
   */
  protected $migration;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->migration = $migration;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $id_map = $row->getIdMap();

    // If the entity already mapped - skip it.
    // TODO Do it before entity_lookup.
    if (!empty($id_map['destid1'])) {
      return $id_map['destid1'];
    }

    if ($value) {
      foreach ($value as $destination_id) {
        // TODO Do not hard code "uid".
        $source_id = $this->migration->getIdMap()->lookupSourceID([
          'uid' => $destination_id,
        ]);

        // If there is an entity with the same email, which is not mapped to any
        // other Odoo entity - use it.
        if (!$source_id) {
          return $destination_id;
        }
      }
    }

    // Create a new entity.
    return NULL;
  }

}
