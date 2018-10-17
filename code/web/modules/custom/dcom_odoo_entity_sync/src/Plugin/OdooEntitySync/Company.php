<?php

namespace Drupal\dcom_odoo_entity_sync\Plugin\OdooEntitySync;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
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
 * User companies sync.
 *
 * @OdooEntitySync(
 *   id = "dcom_odoo_entity_sync_company",
 *   entityType = "user",
 *   odooModel = "res.partner",
 *   exportType = "company"
 * )
 */
class Company extends EntitySyncBase {

  use StringTranslationTrait;
  use UserSyncTrait;

  /**
   * Odoo address data resolver service.
   *
   * @var \Drupal\odoo_api\OdooApi\Data\AddressResolverInterface
   */
  private $addressResolver;

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
    /** @var \Drupal\user\UserInterface $user */
    $user = $entity;
    return $this->userIsWholesale($user);
  }

  /**
   * {@inheritdoc}
   */
  protected function getOdooFields(EntityInterface $entity) {
    /** @var \Drupal\user\UserInterface $user */
    $user = $entity;
    $fields = [
      'is_company' => TRUE,
      'company_type' => 'company',
      // @TODO: Do not override comments on user update.
      'comment' => (string) $this->t('Imported from Drupal.'),
      'name' => $user->field_company_name->value,
      'vat' => $user->field_ein_tax_number->value,
      // @TODO: Store EIN file.
      'city' => $user->field_address->locality,
      'street' => $user->field_address->address_line1,
      'street2' => $user->field_address->address_line2,
      'zip' => $user->field_address->postal_code,
      'phone' => $user->field_phone->value,
      // Pricelist - Wholesale (Odoo id 2).
      'property_product_pricelist' => 2,
    ];

    if (!empty($user->field_address->country_code)
      && !empty($user->field_address->administrative_area)) {
      try {
        $fields['country_id'] = $this->addressResolver->findCountryIdByCode($user->field_address->country_code);
        $fields['state_id'] = $this->addressResolver->findStateIdByCode($fields['country_id'], $user->field_address->administrative_area);
      }
      catch (DataException $e) {
        // Skip wrong state error for non-US customers.
        if ($user->field_address->country_code == 'US') {
          throw new GenericException($this->getEntityType(), $this->getOdooModel(), $this->getExportType(), $entity->id(), 'Error resolving address.', $e);
        }
      }
    }

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  protected function shouldDelete(EntityInterface $entity) {
    return FALSE;
  }

}
