<?php

namespace Drupal\dcom_odoo_migrate\Plugin\migrate\process;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\odoo_api_entity_sync\MappingManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Transform domain id imported from Odoo to Drupal domain id.
 *
 * @code
 * process:
 *   field_domain:
 *     plugin: dcom_odoo_domain_id
 *     source: x_drupal_domain
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "dcom_odoo_domain_id"
 * )
 */
class DcomOdooDomainId extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Mapping manager.
   *
   * @var \Drupal\odoo_api_entity_sync\MappingManagerInterface
   */
  protected $idMap;

  /**
   * List of Odoo and Drupal domain ID.
   *
   * @var array
   */
  protected static $domainsMapping;

  /**
   * Constructs a DcomOdooDomainId object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\odoo_api_entity_sync\MappingManagerInterface $map
   *   The Odoo sync mapping manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    MappingManagerInterface $map
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->idMap = $map;

    if (!isset(self::$domainsMapping)) {
      $domains = $this->entityTypeManager->getStorage('domain')->loadMultiple();
      foreach ($domains as $domain) {
        $map = $this->idMap->getIdMap('domain', 'x_product.domain', 'default', $domain->id());
        $odoo_id = $map[$domain->id()];
        if (!empty($odoo_id)) {
          self::$domainsMapping[$odoo_id] = [
            'odoo_id' => $odoo_id,
            'drupal_id' => $domain->id(),
          ];
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('odoo_api_entity_sync.mapping')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    return isset(self::$domainsMapping[$value]['drupal_id']) ? self::$domainsMapping[$value]['drupal_id'] : NULL;
  }

}
