<?php

namespace Drupal\commerce_klaviyo_review;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Defines a class to build a listing of Commerce Klaviyo Review entities.
 *
 * @ingroup commerce_klaviyo_review
 */
class CommerceKlaviyoReviewListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('Commerce Klaviyo Review ID');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\commerce_klaviyo_review\Entity\CommerceKlaviyoReviewInterface */
    $row['id'] = $entity->id();
    return $row + parent::buildRow($entity);
  }

}
