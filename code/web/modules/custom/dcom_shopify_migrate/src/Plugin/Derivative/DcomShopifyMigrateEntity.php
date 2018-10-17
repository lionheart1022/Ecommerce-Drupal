<?php

namespace Drupal\dcom_shopify_migrate\Plugin\Derivative;

use Drupal\migrate\Plugin\Derivative\MigrateEntity;

/**
 * {@inheritdoc}
 */
class DcomShopifyMigrateEntity extends MigrateEntity {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    parent::getDerivativeDefinitions($base_plugin_definition);

    foreach ($this->entityDefinitions as $entity_type => $entity_info) {
      if (!is_subclass_of($entity_info->getClass(), 'Drupal\Core\Config\Entity\ConfigEntityInterface')) {
        $this->derivatives[$entity_type]['class'] = 'Drupal\dcom_shopify_migrate\Plugin\migrate\destination\DcomShopifyEntityContentBase';
      }
    }

    return $this->derivatives;
  }

}
