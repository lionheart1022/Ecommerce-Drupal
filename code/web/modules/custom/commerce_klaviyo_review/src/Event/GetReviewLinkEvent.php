<?php

namespace Drupal\commerce_klaviyo_review\Event;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\Event;

/**
 * Event for creating link for klaviyo review.
 */
class GetReviewLinkEvent extends Event {

  const GET_REVIEW_LINK_EVENT = 'commerce_klaviyo_review.get_review_link';

  /**
   * Review URL.
   *
   * @var \Drupal\Core\Url
   */
  protected $reviewUrl;

  /**
   * Commerce order.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $order;

  /**
   * Builds a new FlaggingEvent.
   *
   * @param \Drupal\Core\Url $url
   *   The review link.
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   Commerce order.
   */
  public function __construct(Url $url, OrderInterface $order) {
    $this->reviewUrl = $url;
    $this->order = $order;
  }

  /**
   * Returns the review URL associated with the Event.
   *
   * @return \Drupal\Core\Url
   *   Review URL.
   */
  public function getReviewUrl() {
    return $this->reviewUrl;
  }

  /**
   * Returns the commerce order associated with the Event.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface
   *   Commerce order.
   */
  public function getReviewOrder() {
    return $this->order;
  }

}
