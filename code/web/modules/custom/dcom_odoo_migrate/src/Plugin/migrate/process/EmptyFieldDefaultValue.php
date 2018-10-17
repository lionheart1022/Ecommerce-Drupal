<?php

namespace Drupal\dcom_odoo_migrate\Plugin\migrate\process;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Plugin\migrate\process\DefaultValue;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns a given default value if the input is empty and destination is empty.
 *
 * @MigrateProcessPlugin(
 *   id = "empty_field_default_value"
 * )
 */
class EmptyFieldDefaultValue extends DefaultValue implements ContainerFactoryPluginInterface {

  /**
   * The entity migration object.
   *
   * @var \Drupal\migrate\Plugin\MigrationInterface
   */
  protected $migration;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a DcomOdooImageUri object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   Migration.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->migration = $migration;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $id_map = $row->getIdMap();

    if (!empty($id_map['destid1']) && $this->migration && $destination_plugin = $this->migration->getDestinationPlugin()) {
      $entity_type = $destination_plugin->getDerivativeId();
      $storage = $this->entityTypeManager->getStorage($entity_type);
      $field_name = $this->prepareFieldName($destination_property);

      // Returns original field value instead of default.
      if (($destination_entity = $storage->load($id_map['destid1']))
        && $destination_entity->hasField($field_name)
        && !$destination_entity->{$field_name}->isEmpty()
      ) {
        return $destination_entity->{$field_name}->getValue();
      }
    }

    if (!empty($this->configuration['strict'])) {
      return isset($value) ? $value : $this->configuration['default_value'];
    }
    return $value ?: $this->configuration['default_value'];
  }

  /**
   * Prepare destination field name in case field_name/0/value.
   *
   * @param string $destination_property
   *   Migration destination property.
   *
   * @return string
   *   Destination field name.
   */
  public function prepareFieldName($destination_property) {
    $separator = '/';
    $field_name = $destination_property;
    if (strpos($destination_property, $separator)) {
      list($field_name) = explode($separator, $destination_property, 2);
    }
    return $field_name;
  }

}
