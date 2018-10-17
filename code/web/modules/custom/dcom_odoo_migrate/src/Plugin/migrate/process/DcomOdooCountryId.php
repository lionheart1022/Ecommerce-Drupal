<?php

namespace Drupal\dcom_odoo_migrate\Plugin\migrate\process;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\odoo_api\OdooApi\Data\AddressResolverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Transform country id imported from Odoo to Drupal country id (ex: US).
 *
 * @code
 * process:
 *   address/0/country_code:
 *     plugin: dcom_odoo_country_id
 *     source: country_id
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "dcom_odoo_country_id",
 *   handle_multiples = TRUE
 * )
 */
class DcomOdooCountryId extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Address resolver service.
   *
   * @var \Drupal\odoo_api\OdooApi\Data\AddressResolverInterface
   */
  protected $addressResolver;

  /**
   * Constructs a DcomOdooCountryId object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\odoo_api\OdooApi\Data\AddressResolverInterface $address_resolver
   *   The Odoo address resolver service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    AddressResolverInterface $address_resolver
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->addressResolver = $address_resolver;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('odoo_api.address_resolver')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $country_code = '';

    if (is_array($value)) {
      $country_code = $this->addressResolver->findCountryCodeById((int) reset($value));
    }

    return $country_code;
  }

}
