<?php

namespace Drupal\dcom_shopify_migrate\Plugin\migrate\process;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\dcom_shopify_migrate\CustomerOrdersResolverInterface;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Custom process plugin for extracting company name.
 *
 * @code
 * process:
 *   name:
 *     plugin: dcom_extract_company
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "dcom_extract_company"
 * )
 */
class ExtractCompany extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Orders resolver.
   *
   * @var \Drupal\dcom_shopify_migrate\CustomerOrdersResolverInterface
   */
  protected $ordersResolver;

  /**
   * ExtractCompany constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\dcom_shopify_migrate\CustomerOrdersResolverInterface $orders_resolver
   *   Orders resolver.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CustomerOrdersResolverInterface $orders_resolver) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->ordersResolver = $orders_resolver;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('dcom_shopify_migrate.customer_orders_resolver')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $regex = '/Company Name: ([\w \.]+)/i';
    if (preg_match($regex, $value, $matches)) {
      return trim($matches[1]);
    }

    $address_company = $row->getSourceProperty('default_address/company');
    if (!empty($address_company)) {
      return $address_company;
    }

    // For now only 1 user doesn't has a company name. Hardcode it for him.
    $customer_id = $row->getSourceIdValues()['id'];
    if ($customer_id == 404178927677) {
      return 'Unknown';
    }

    $configuration = &$this->configuration;
    $orders = $this->ordersResolver->getCustomerOrders($customer_id, $configuration['shop_domain'], $configuration['api_key'], $configuration['password'], $configuration['shared_secret']);

    if ($orders) {
      // Fail if there's no company name.
      throw new MigrateException('Missing company name');
    }

    return NULL;
  }

}
