<?php

namespace Drupal\dcom_profile\Plugin\migrate\process;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\dcom_profile\Util\ProfileComparatorInterface;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * This plugin looks for existing profiles.
 *
 * @MigrateProcessPlugin(
 *   id = "find_profile_by_profile"
 * )
 *
 * Example usage with full configuration:
 * @code
 *   profile_id:
 *     plugin: find_profile_by_profile
 *     get_default_destination: true
 *     type: customer
 *     status: true
 *     field_phone_number: phone
 *     address:
 *       given_name: first_name
 *       family_name: last_name
 *       organization: company
 *       address_line1: address1
 *       address_line2: address2
 *       locality: city
 *       administrative_area: province_code
 *       postal_code: zip
 *       country_code: country_code
 * @endcode
 */
class FindProfileByProfile extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The profile comparator.
   *
   * @var \Drupal\dcom_profile\Util\ProfileComparatorInterface
   */
  protected $profileComparator;

  /**
   * The migration.
   *
   * @var \Drupal\migrate\Plugin\MigrationInterface
   */
  protected $migration;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $pluginId, $pluginDefinition, ProfileComparatorInterface $profile_comparator, MigrationInterface $migration) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
    $this->profileComparator = $profile_comparator;
    $this->migration = $migration;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $pluginId, $pluginDefinition, MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $pluginId,
      $pluginDefinition,
      $container->get('dcom_profile.profile_comparator'),
      $migration
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (!empty($this->configuration['get_default_destination'])) {
      $destination_ids = $this->migration->getIdMap()->lookupDestinationIds($row->getSourceIdValues());

      // If there is already a destination - use it.
      if (!empty($destination_ids[0][0])) {
        return $destination_ids[0][0];
      }
    }

    $search_by = [];

    if ($value) {
      $search_by['uid'] = $value;
    }
    if (!empty($this->configuration['field_phone_number'])) {
      if ($property_value = $row->getSourceProperty($this->configuration['field_phone_number'])) {
        $search_by['field_phone_number'] = $property_value;
      }
    }

    foreach (['status', 'type'] as $property) {
      if (!empty($this->configuration[$property])) {
        $search_by[$property] = $this->configuration[$property];
      }
    }

    $this->getAddressProperties($migrate_executable, $row, $destination_property, $search_by);

    if (empty($search_by)) {
      throw new MigrateException('At least one parameter must be providen to search profiles.');
    }

    if ($profile_ids = $this->profileComparator->findProfileByArray($search_by)) {
      return reset($profile_ids);
    }

    return NULL;
  }

  /**
   * Gets address values from the row based on the configuration.
   *
   * @param \Drupal\migrate\MigrateExecutableInterface $migrate_executable
   *   The migration in which this process is being executed.
   * @param \Drupal\migrate\Row $row
   *   The row from the source to process. Normally, just transforming the value
   *   is adequate but very rarely you might need to change two columns at the
   *   same time or something like that.
   * @param string $destination_property
   *   The destination property currently worked on. This is only used together
   *   with the $row above.
   * @param array $search_by
   *   The array to return search values to.
   */
  protected function getAddressProperties(MigrateExecutableInterface $migrate_executable, Row $row, $destination_property, array &$search_by = []) {
    // TODO Get it from array_keys(AddressComparator::getQueryConditions()).
    $address_properties = [
      'given_name',
      'family_name',
      'organization',
      'address_line1',
      'address_line2',
      'locality',
      'administrative_area',
      'postal_code',
      'country_code',
    ];

    foreach ($address_properties as $property) {
      if (!empty($this->configuration['address'][$property]) && ($property_value = $row->getSourceProperty($this->configuration['address'][$property]))) {
        $search_by['address'][$property] = $property_value;
      }
    }
  }

}
