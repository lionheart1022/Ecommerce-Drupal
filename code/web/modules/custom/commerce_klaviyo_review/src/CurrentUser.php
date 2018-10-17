<?php

namespace Drupal\commerce_klaviyo_review;

use Drupal\Core\Routing\ResettableStackedRouteMatchInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Class CurrentUser.
 */
class CurrentUser extends AccountProxy {

  protected $routeMatch;

  /**
   * Specific implementation of AccountInterface.
   *
   * @param \Drupal\Core\Routing\ResettableStackedRouteMatchInterface $route_match
   *   Route matcher.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   Current user.
   */
  public function __construct(ResettableStackedRouteMatchInterface $route_match, AccountProxyInterface $current_user) {
    $this->routeMatch = $route_match;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public function getAccount() {
    if ('commerce_klaviyo_review.order_review' == $this->routeMatch->getRouteName()
      && $order = $this->routeMatch->getParameter('commerce_order')) {
      if ($customer = $order->getCustomer()) {
        return $customer;
      }
    }

    return $this->currentUser;
  }

}
