<?php

namespace Drupal\commerce_klaviyo_review;

use Drupal\commerce_order\Entity\OrderInterface;

/**
 * Interface CommerceKlaviyoReviewReviewBuilderInterface.
 */
interface ReviewBuilderInterface {

  /**
   * Obtains link to add review page.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   Commerce order.
   *
   * @return \Drupal\Core\Url
   *   Url to add review page.
   */
  public function getReviewLink(OrderInterface $order);

  /**
   * Creates review.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   Commerce order.
   */
  public function createReview(OrderInterface $order);

}
