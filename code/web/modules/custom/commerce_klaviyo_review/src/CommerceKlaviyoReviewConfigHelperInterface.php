<?php

namespace Drupal\commerce_klaviyo_review;

/**
 * Provides the interface for the CommerceKlaviyoReviewConfigHelper service.
 *
 * Interface CommerceKlaviyoReviewConfigHelperInterface.
 */
interface CommerceKlaviyoReviewConfigHelperInterface {

  /**
   * Checks whether klaviyo review functionality enabled or not.
   *
   * @return bool
   *   True if enabled, false - otherwise.
   */
  public function isEnabled();

}
