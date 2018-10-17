<?php

namespace Drupal\cpl_commerce_profile\Routing;

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
    /** @var \Symfony\Component\Routing\Route $route */
    if ($route = $collection->get('entity.profile.type.user_profile_form')) {
      $route->setDefault('_controller', '\Drupal\cpl_commerce_profile\Controller\ProfileController::userProfileForm');
      $route->setDefault('_title_callback', NULL);
      $route->setDefault('_title', 'Address book');
    }

    // Edit user page permission.
    if ($route = $collection->get('entity.user.edit_form')) {
      $route->setRequirement('_permission', 'administer users');
    }
  }

}
