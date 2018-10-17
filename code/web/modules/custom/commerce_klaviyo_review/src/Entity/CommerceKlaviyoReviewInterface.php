<?php

namespace Drupal\commerce_klaviyo_review\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Commerce Klaviyo Review entities.
 *
 * @ingroup commerce_klaviyo_review
 */
interface CommerceKlaviyoReviewInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {

  const DEFAULT_URL_PROPERTY = 'KlaviyoReviewURL';

  /**
   * Gets the Commerce Klaviyo Review creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Commerce Klaviyo Review.
   */
  public function getCreatedTime();

  /**
   * Sets the Commerce Klaviyo Review creation timestamp.
   *
   * @param int $timestamp
   *   The Commerce Klaviyo Review creation timestamp.
   *
   * @return \Drupal\commerce_klaviyo_review\Entity\CommerceKlaviyoReviewInterface
   *   The called Commerce Klaviyo Review entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the Commerce Klaviyo Review published status indicator.
   *
   * Unpublished Commerce Klaviyo Review are only visible to restricted users.
   *
   * @return bool
   *   TRUE if the Commerce Klaviyo Review is published.
   */
  public function isPublished();

  /**
   * Sets the published status of a Commerce Klaviyo Review.
   *
   * @param bool $published
   *   TRUE to set this Commerce Klaviyo Review to published,
   *   FALSE to set it to unpublished.
   *
   * @return \Drupal\commerce_klaviyo_review\Entity\CommerceKlaviyoReviewInterface
   *   The called Commerce Klaviyo Review entity.
   */
  public function setPublished($published);

  /**
   * Gets the review comments.
   *
   * @return \Drupal\comment\CommentInterface[]
   *   The order items.
   */
  public function getComments();

  /**
   * Sets the review comments.
   *
   * @param \Drupal\comment\CommentInterface[] $comments
   *   The order items.
   *
   * @return $this
   */
  public function setComments(array $comments);

}
