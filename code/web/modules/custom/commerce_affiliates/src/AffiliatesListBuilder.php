<?php

namespace Drupal\commerce_affiliates;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Defines the list builder for affiliate types.
 */
class AffiliatesListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Affiliate type');
    $header['plugin_label'] = $this->t('Affiliate Type');
    $header['plugin_tracking_type'] = $this->t('Tracking Type');
    $header['status'] = $this->t('Status');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\commerce_affiliates\Entity\AffiliateTypeInterface $entity */
    $row['label'] = $entity->label();
    $row['plugin_label'] = $entity->getPlugin()->getLabel();
    $row['plugin_tracking_type'] = $entity->getPlugin()->getTrackingType();
    $row['status'] = $entity->status() ? $this->t('Enabled') : $this->t('Disabled');
    return $row + parent::buildRow($entity);
  }

}
