<?php

namespace Drupal\commerce_klaviyo_review;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Class CommerceKlaviyoReviewConfigHelper.
 */
class CommerceKlaviyoReviewConfigHelper implements CommerceKlaviyoReviewConfigHelperInterface {

  /**
   * Commerce klaviyo review config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Creates new CommerceKlaviyoReviewConfigHelper service.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->config = $config_factory->get('commerce_klaviyo_review.config');
  }

  /**
   * Checks whether klaviyo review functionality enabled or not.
   *
   * @return bool
   *   True if enabled, false - otherwise.
   */
  public function isEnabled() {
    return $this->config->get('enabled') && $this->config->get('fields_config');
  }

}
