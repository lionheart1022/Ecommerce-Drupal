<?php

namespace Drupal\dcom_shopify_migrate\Plugin\migrate\destination;

use Drupal\migrate\Plugin\migrate\destination\EntityContentBase;
use Drupal\migrate\Row;

/**
 * Provides destination class for all content entities lacking a specific class.
 *
 * Allows to skip modifying the entity's values.
 *
 * @code
 * process:
 *   uid:
 *     plugin: entity_lookup
 *     value_key: mail
 *     entity_type: user
 *     ignore_case: true
 *     source: email
 * destination:
 *   plugin: dcom_shopify_entity:user
 *   skip_modify_entity_values: true
 * @endcode
 *
 * @see Drupal\migrate\Plugin\migrate\destination\EntityContentBase
 */
class DcomShopifyEntityContentBase extends EntityContentBase {

  /**
   * {@inheritdoc}
   */
  protected static function getEntityTypeId($plugin_id) {
    // Remove "dcom_shopify_entity:".
    return substr($plugin_id, 20);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntity(Row $row, array $old_destination_id_values) {
    if (!isset($this->configuration['skip_modify_entity_values'])) {
      return parent::getEntity($row, $old_destination_id_values);
    }

    $entity_id = reset($old_destination_id_values) ?: $this->getEntityId($row);
    if (!empty($entity_id) && ($entity = $this->storage->load($entity_id))) {
      // Do not update the entity here, just return loaded object.
      return $entity;
    }
    else {
      return parent::getEntity($row, $old_destination_id_values);
    }
  }

}
