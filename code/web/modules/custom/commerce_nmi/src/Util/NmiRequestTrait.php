<?php

namespace Drupal\commerce_nmi\Util;

/**
 * Allows setter injection and simple usage of the service.
 *
 * @package Drupal\commerce_nmi\Util
 */
trait NmiRequestTrait {

  /**
   * The NMI Request.
   *
   * @var \Drupal\commerce_nmi\Util\NmiRequestInterface
   */
  protected $nmiRequest;

  /**
   * Sets the NMI Request.
   *
   * @param \Drupal\commerce_nmi\Util\NmiRequestInterface $nmi_request
   *   The NMI Request.
   *
   * @return $this
   */
  public function setNmiRequest(NmiRequestInterface $nmi_request) {
    $this->nmiRequest = $nmi_request;
    return $this;
  }

  /**
   * Gets the NMI Request.
   *
   * @return \Drupal\commerce_nmi\Util\NmiRequestInterface
   *   The NMI Request.
   */
  public function getNmiRequest() {
    if (empty($this->nmiRequest)) {
      $this->nmiRequest = \Drupal::service('commerce_nmi.nmi_request');
    }
    return $this->nmiRequest;
  }

}
