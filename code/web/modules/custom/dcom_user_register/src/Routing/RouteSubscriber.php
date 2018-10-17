<?php

namespace Drupal\dcom_user_register\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('multiple_registration.role_registration_page')) {
      $route->setDefault('_title_callback', 'dcom_user_register_get_title');
    }
  }

}
