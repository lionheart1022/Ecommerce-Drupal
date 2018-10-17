<?php

namespace Drupal\dcom_odoo_entity_sync\Plugin\OdooEntitySync;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\dcom_odoo_entity_sync\Util\UserSyncTrait;
use Drupal\odoo_api\OdooApi\ClientInterface;
use Drupal\odoo_api_entity_sync\MappingManagerInterface;
use Drupal\odoo_api_entity_sync\Plugin\EntitySyncBase;
use Drupal\odoo_api_entity_sync\SyncManagerInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Users sync.
 *
 * @OdooEntitySync(
 *   id = "dcom_odoo_entity_sync_user",
 *   entityType = "user",
 *   odooModel = "res.partner",
 * )
 */
class User extends EntitySyncBase {

  use UserSyncTrait;
  use StringTranslationTrait;

  const DCOM_ODOO_USER_SYNC_EXCLUDE_FIELD = 'field_odoo_sync_exclude';

  /**
   * Commerce order entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $orderStorage;

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
    EntityTypeManagerInterface $manager
  ) {
    $this->orderStorage = $manager->getStorage('commerce_order');
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
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function shouldSync(EntityInterface $entity) {
    /** @var \Drupal\user\UserInterface $user */
    $user = $entity;
    if (preg_match('/\+duplicate_\d+$/', $entity->getEmail())) {
      // Do not export duplicate users to Odoo.
      return FALSE;
    }

    return !$this->userSyncExcluded($user)
      && ($this->userHasOrders($user) || $this->userIsWholesale($user));
  }

  /**
   * {@inheritdoc}
   */
  protected function getOdooFields(EntityInterface $entity) {
    /** @var \Drupal\user\UserInterface $user */
    $user = $entity;
    $roles_odoo_ids = [];
    if ($roles = $user->getRoles(TRUE)) {
      $roles_odoo_ids = $this->getReferencedEntitiesOdooId('user_role', 'x_drupal_user.role', 'default', $roles);
      $roles_odoo_ids = array_values($roles_odoo_ids);
    }

    $is_wholesale = $this->userIsWholesale($user);
    $fields = [
      'email' => $user->getEmail(),
      'type' => 'contact',
      'name' => $this->getFullName($user),
      'comment' => (string) $this->t('Imported from Drupal'),
      'phone' => $user->field_phone->value,
      'x_drupal_role' => [[6, 0, $roles_odoo_ids]],
      'x_customer_type' => $is_wholesale ? 'wholesale' : 'retail',
      'x_export_to_drupal' => $is_wholesale,
    ];

    if ($is_wholesale) {
      // Company reference.
      $fields['parent_id'] = $this->getReferencedEntityOdooId($user->getEntityTypeId(), 'res.partner', 'company', $user->id());
    }
    else {
      // Pricelist - Retail (Odoo id 1).
      $fields['property_product_pricelist'] = 1;
    }

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  protected function shouldDelete(EntityInterface $entity) {
    /** @var \Drupal\user\UserInterface $entity */
    // Because of a mess with duplicated users the following entities has
    // orders on Odoo, not on Drupal. We need to find a better solution to
    // handle it. I didn't bother.
    // TODO Remove this once all references are removed on Odoo.
    $not_delete = in_array($entity->id(), [6406]);
    if (!$not_delete && preg_match('/\+duplicate_\d+$/', $entity->getEmail())) {
      // Delete duplicate users from Odoo.
      return TRUE;
    }

    // Always keep wholesale users.
    if ($this->userIsWholesale($entity)) {
      return FALSE;
    }

    return FALSE;
  }

  /**
   * Returns user's full name.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user entity.
   *
   * @return string
   *   The full user name.
   */
  protected function getFullName(UserInterface $user) {
    return $user->field_first_name->value . ' ' . $user->field_last_name->value;
  }

}
