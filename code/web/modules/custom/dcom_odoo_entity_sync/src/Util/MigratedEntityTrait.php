<?php

namespace Drupal\dcom_odoo_entity_sync\Util;

use Drupal\Core\Entity\EntityInterface;
use Drupal\dcom_odoo_entity_sync\Exception\MigrateLookupException;
use Drupal\dcom_odoo_migrate\MigrateIdMapTrait;

/**
 * Helper for getting IDs of migrated entities.
 */
trait MigratedEntityTrait {

  use MigrateIdMapTrait;

  /**
   * Lookup Odoo ID using Migrate.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity object.
   * @param string $odoo_model
   *   Odoo model.
   *
   * @return int
   *   Odoo ID.
   *
   * @throws \Drupal\dcom_odoo_entity_sync\Exception\MigrateLookupException
   *   ID lookup error.
   */
  protected function getMigratedEntityOdooId(EntityInterface $entity, $odoo_model) {
    $ids = $this->lookupOdooIds($entity, $odoo_model);
    if (count($ids) == 0) {
      throw new MigrateLookupException($entity->getEntityTypeId(), $odoo_model, $entity->id(), 'No Odoo ID found. The entity was not imported from Odoo.');
    }
    if (count($ids) > 1) {
      throw new MigrateLookupException($entity->getEntityTypeId(), $odoo_model, $entity->id(), 'More than one Odoo ID found.');
    }
    return (int) reset($ids);
  }

}
