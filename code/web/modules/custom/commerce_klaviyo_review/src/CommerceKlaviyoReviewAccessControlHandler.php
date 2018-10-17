<?php

namespace Drupal\commerce_klaviyo_review;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Commerce Klaviyo Review entity.
 *
 * @see \Drupal\commerce_klaviyo_review\Entity\CommerceKlaviyoReview.
 */
class CommerceKlaviyoReviewAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\commerce_klaviyo_review\Entity\CommerceKlaviyoReviewInterface $entity */
    switch ($operation) {
      case 'view':
        if (!$entity->isPublished()) {
          return AccessResult::allowedIfHasPermission($account, 'view unpublished commerce klaviyo review entities');
        }
        return AccessResult::allowedIfHasPermission($account, 'view published commerce klaviyo review entities');

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit commerce klaviyo review entities');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete commerce klaviyo review entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add commerce klaviyo review entities');
  }

}
