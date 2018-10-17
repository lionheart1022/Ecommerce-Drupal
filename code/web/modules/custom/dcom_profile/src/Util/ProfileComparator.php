<?php

namespace Drupal\dcom_profile\Util;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\profile\Entity\ProfileInterface;

/**
 * The ProfileComparator service.
 *
 * @package Drupal\dcom_profile\Util
 */
class ProfileComparator extends ComparatorBase implements ProfileComparatorInterface {

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The address comparator service.
   *
   * @var \Drupal\dcom_profile\Util\AddressComparatorInterface
   */
  protected $addressComparator;

  /**
   * ProfileComparator constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\dcom_profile\Util\AddressComparatorInterface $address_comparator
   *   The address comparator service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, AddressComparatorInterface $address_comparator) {
    parent::__construct($entity_type_manager);
    $this->entityFieldManager = $entity_field_manager;
    $this->addressComparator = $address_comparator;
  }

  /**
   * {@inheritdoc}
   */
  public function equals(ProfileInterface $original_profile, ProfileInterface $profile) {
    if ($original_profile->bundle() != $profile->bundle()) {
      throw new \InvalidArgumentException('Can not profiles of a different bundle.');
    }

    $definitions = $this->entityFieldManager->getFieldDefinitions('profile', $profile->bundle());

    foreach ($definitions as $field_definition) {
      if ($this->hasFieldValueChanged($field_definition, $profile, $original_profile)) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Checks whether the field values changed compared to the original entity.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   Field definition of field to compare for changes.
   * @param \Drupal\profile\Entity\ProfileInterface $entity
   *   Entity to check for field changes.
   * @param \Drupal\profile\Entity\ProfileInterface $original
   *   Original entity to compare against.
   *
   * @return bool
   *   True if the field value changed from the original entity.
   */
  protected function hasFieldValueChanged(FieldDefinitionInterface $field_definition, ProfileInterface $entity, ProfileInterface $original) {
    $field_name = $field_definition->getName();
    $langcodes = array_keys($entity->getTranslationLanguages());
    if ($langcodes !== array_keys($original->getTranslationLanguages())) {
      // If the list of langcodes has changed - the field value is changed.
      return TRUE;
    }
    foreach ($langcodes as $langcode) {
      $items = $entity->getTranslation($langcode)->get($field_name)->filterEmptyItems();
      $original_items = $original->getTranslation($langcode)->get($field_name)->filterEmptyItems();
      if (!$items->equals($original_items)) {
        // The field items are not equal.
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function findProfileByProfile(ProfileInterface $profile, $load_entities = FALSE) {
    $profile_storage = $this->entityTypeManager->getStorage('profile');
    $query = $profile_storage->getQuery()
      ->condition('type', $profile->bundle(), '=')
      ->condition('status', $profile->isActive(), '=')
      ->condition('uid', $profile->getOwnerId(), '=');

    if (!$profile->isNew()) {
      $id_field = $profile_storage->getEntityType()->getKey('id');
      $query->condition($id_field, $profile->id(), '!=');
    }

    $phone_field_name = 'field_phone_number';
    if (!$profile->get($phone_field_name)->isEmpty()) {
      /** @var \Drupal\telephone\Plugin\Field\FieldType\TelephoneItem $phone */
      $phone = $profile->get($phone_field_name)->first();
      $query->condition($phone_field_name, $phone->get('value')->getValue(), '=');
    }

    if (!$profile->get('address')->isEmpty()) {
      /** @var \Drupal\address\AddressInterface $address */
      $address = $profile->get('address')->first();
      $conditions = $this->addressComparator->getQueryConditions($address);

      foreach ($conditions as $field_name => $value) {
        $query->condition('address.' . $field_name, $value, '=');
      }
    }

    $result = $query->execute();

    return $load_entities ? $profile_storage->loadMultiple($result) : $result;
  }

  /**
   * {@inheritdoc}
   */
  public function findProfileByArray(array $profile, $load_entities = FALSE) {
    if (empty($profile)) {
      throw new \InvalidArgumentException('At least one search parameter must be provided to search profiles.');
    }

    $profile_storage = $this->entityTypeManager->getStorage('profile');
    $query = $profile_storage->getQuery();

    foreach (['type', 'status', 'uid', 'field_phone_number'] as $property) {
      if (!empty($profile[$property])) {
        $query->condition($property, $profile[$property], '=');
      }
    }

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

    foreach ($address_properties as $field_name) {
      if (!empty($profile['address'][$field_name])) {
        $query->condition('address.' . $field_name, $profile['address'][$field_name], '=');
      }
    }

    $result = $query->execute();
    return $load_entities ? $profile_storage->loadMultiple($result) : $result;
  }

}
