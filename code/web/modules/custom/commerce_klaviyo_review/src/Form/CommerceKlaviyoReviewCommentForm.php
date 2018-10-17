<?php

namespace Drupal\commerce_klaviyo_review\Form;

use Drupal\comment\CommentForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Overridden base handler for comment forms.
 *
 * @internal
 */
class CommerceKlaviyoReviewCommentForm extends CommentForm {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager'),
      $container->get('commerce_klaviyo_review.current_user'),
      $container->get('renderer'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time')
    );
  }

}
