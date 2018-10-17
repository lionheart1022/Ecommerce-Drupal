<?php

namespace Drupal\dcom_odoo_entity_sync\Plugin\OdooEntitySync;

use Drupal\Core\Entity\EntityInterface;
use Drupal\odoo_api_entity_sync\Plugin\EntitySyncBase;

/**
 * Domains sync.
 *
 * @OdooEntitySync(
 *   id = "dcom_odoo_entity_sync_domain",
 *   entityType = "domain",
 *   odooModel = "x_product.domain"
 * )
 */
class Domain extends EntitySyncBase {

  /**
   * {@inheritdoc}
   */
  protected function shouldSync(EntityInterface $entity) {
    /** @var \Drupal\domain\DomainInterface $domain */
    $domain = $entity;
    return $domain->id() != 'default';
  }

  /**
   * {@inheritdoc}
   */
  protected function shouldDelete(EntityInterface $entity) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function getOdooFields(EntityInterface $entity) {
    /** @var \Drupal\domain\DomainInterface $domain */
    $domain = $entity;
    return [
      'x_name' => $domain->getHostname(),
    ];
  }

}
