<?php

namespace Drupal\dcom_odoo_entity_sync\Plugin\OdooEntitySync;

use Drupal\Core\Entity\EntityInterface;
use Drupal\odoo_api_entity_sync\Plugin\EntitySyncBase;

/**
 * User roles sync.
 *
 * @OdooEntitySync(
 *   id = "dcom_odoo_entity_sync_user_role",
 *   entityType = "user_role",
 *   odooModel = "x_drupal_user.role",
 * )
 */
class UserRole extends EntitySyncBase {

  /**
   * {@inheritdoc}
   */
  protected function getOdooFields(EntityInterface $entity) {
    /** @var \Drupal\user\RoleInterface $role */
    $role = $entity;
    return ['x_name' => $role->label()];
  }

  /**
   * {@inheritdoc}
   */
  protected function shouldSync(EntityInterface $entity) {
    return !in_array($entity->id(), ['anonymous', 'authenticated']);
  }

  /**
   * {@inheritdoc}
   */
  protected function shouldDelete(EntityInterface $entity) {
    return FALSE;
  }

}
