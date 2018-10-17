<?php

namespace Drupal\dcom_odoo_entity_sync\Plugin\OdooEntitySync;

use Drupal\Core\Entity\EntityInterface;
use Drupal\dcom_odoo_entity_sync\DcomEntitySyncInterface;
use Drupal\odoo_api_entity_sync\Plugin\EntitySyncBase;

/**
 * Payment gateway sync.
 *
 * @OdooEntitySync(
 *   id = "dcom_odoo_entity_sync_payment_gateway",
 *   entityType = "commerce_payment_gateway",
 *   odooModel = "payment.acquirer",
 * )
 */
class PaymentGateway extends EntitySyncBase implements DcomEntitySyncInterface {

  /**
   * {@inheritdoc}
   */
  protected function shouldSync(EntityInterface $entity) {
    /** @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface $payment_gateway */
    $payment_gateway = $entity;
    return $payment_gateway->status();
  }

  /**
   * {@inheritdoc}
   */
  protected function getOdooFields(EntityInterface $entity) {
    /** @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface $payment_gateway */
    $payment_gateway = $entity;

    $fields = [
      'name' => 'Drupal - ' . $payment_gateway->label(),
      'provider' => 'manual',
      'specific_countries' => FALSE,
      'journal_id' => static::DIAMONDCBD_ODOO_JOURNAL_BANK_ID,
      'payment_flow' => 's2s',
      'company_id' => static::DIAMONDCBD_ODOO_COMPANY_ID,
      'website_published' => TRUE,
    ];
    $configuration = $payment_gateway->getPluginConfiguration();
    if (!empty($configuration['display_label'])) {
      $fields['display_name'] = $configuration['display_label'];
    }

    return $fields;
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
  protected function recreateDeleted(EntityInterface $entity) {
    return TRUE;
  }

}
