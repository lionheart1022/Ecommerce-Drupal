<?php

namespace Drupal\dcom_odoo_entity_sync\Plugin\OdooEntitySync;

use Drupal\Core\Entity\EntityInterface;
use Drupal\dcom_odoo_entity_sync\Util\UserSyncTrait;
use Drupal\odoo_api\OdooApi\ClientInterface;
use Drupal\odoo_api\OdooApi\Data\AddressResolverInterface;
use Drupal\odoo_api\OdooApi\Exception\DataException;
use Drupal\odoo_api_entity_sync\Exception\GenericException;
use Drupal\odoo_api_entity_sync\MappingManagerInterface;
use Drupal\odoo_api_entity_sync\Plugin\EntitySyncBase;
use Drupal\odoo_api_entity_sync\SyncManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Profiles sync.
 *
 * @OdooEntitySync(
 *   id = "dcom_odoo_entity_sync_profile",
 *   entityType = "profile",
 *   odooModel = "res.partner",
 * )
 */
class Profile extends EntitySyncBase {

  use UserSyncTrait;

  /**
   * Address resolver service.
   *
   * @var \Drupal\odoo_api\OdooApi\Data\AddressResolverInterface
   */
  protected $addressResolver;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ClientInterface $odoo_api,
    SyncManagerInterface $sync_manager,
    MappingManagerInterface $map,
    EventDispatcherInterface $event_dispatcher,
    AddressResolverInterface $address_resolver
  ) {
    $this->addressResolver = $address_resolver;
    return parent::__construct($configuration, $plugin_id, $plugin_definition, $odoo_api, $sync_manager, $map, $event_dispatcher);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('odoo_api.api_client'),
      $container->get('odoo_api_entity_sync.sync'),
      $container->get('odoo_api_entity_sync.mapping'),
      $container->get('event_dispatcher'),
      $container->get('odoo_api.address_resolver')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function shouldSync(EntityInterface $entity) {
    /** @var \Drupal\profile\Entity\ProfileInterface $profile */
    $profile = $entity;
    $user = $profile->getOwner();
    $bundle = $entity->bundle() == 'customer';
    return $bundle
      && $user
      && !$this->userSyncExcluded($user)
      && !$user->isAnonymous()
      && $this->userHasOrders($user);
  }

  /**
   * {@inheritdoc}
   */
  protected function getOdooFields(EntityInterface $entity) {
    /** @var \Drupal\profile\Entity\ProfileInterface $profile */
    $profile = $entity;
    $user = $profile->getOwner();

    $fields = [
      // TODO $profile->isDefault()?
      'type' => 'other',
      'comment' => 'Imported from Drupal',
      'phone' => $profile->field_phone_number->value,
      'parent_id' => $this->getReferencedEntityOdooId($user->getEntityTypeId(), 'res.partner', 'default', $user->id()),
      'active' => $profile->isActive(),
    ];

    if ($profile->hasField('address') && !$profile->address->isEmpty()) {
      /** @var \Drupal\address\AddressInterface $address */
      $address = $profile->get('address')->first();

      try {
        // Odoo Country ID.
        $country_id = '';
        if ($address->getCountryCode()) {
          $country_id = $this->addressResolver->findCountryIdByCode($address->getCountryCode());
        }
        // Odoo State ID.
        $state_id = '';
        if ($country_id && $address->getAdministrativeArea()) {
          $state_id = $this->addressResolver->findStateIdByCode($country_id, $address->getAdministrativeArea());
        }
      }
      catch (DataException $e) {
        throw new GenericException($this->getEntityType(), $this->getOdooModel(), $this->getExportType(), $entity->id(), 'Error resolving address.', $e);
      }

      $name = $address->getGivenName() . ' ' . $address->getFamilyName();
      $user_first_name = $user->field_first_name->value;
      $user_last_name = $user->field_last_name->value;

      if ($user_first_name && $user_last_name) {
        if ($user_first_name == $address->getGivenName() && $user_last_name == $address->getFamilyName()) {
          $name = '';
        }
      }

      $fields += [
        'name' => $name,
        'x_drupal_profile_organization' => $address->getOrganization(),
        'street' => $address->getAddressLine1(),
        'street2' => $address->getAddressLine2(),
        'city' => $address->getLocality(),
        'state_id' => $state_id,
        'zip' => $address->getPostalCode(),
        'country_id' => $country_id,
      ];
    }

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  protected function shouldDelete(EntityInterface $entity) {
    /** @var \Drupal\profile\Entity\ProfileInterface $entity */
    // Because of a mess with duplicated users the following entities has
    // orders on Odoo, not on Drupal. We need to find a better solution to
    // handle it. I didn't bother.
    // TODO Remove this once all references are removed on Odoo.
    $not_delete = in_array($entity->id(), [742, 752, 7414]);
    if (!$not_delete && ($user = $entity->getOwner()) && $this->entityExported($entity)) {
      return $user->isAnonymous() || !$this->userHasOrders($user);
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function recreateDeleted(EntityInterface $entity) {
    // We always want to re-create deleted profiles.
    return TRUE;
  }

}
