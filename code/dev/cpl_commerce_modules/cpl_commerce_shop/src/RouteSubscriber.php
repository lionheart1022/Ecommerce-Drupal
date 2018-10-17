<?php

namespace Drupal\cpl_commerce_shop;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Drupal\Core\Url;
use Symfony\Component\Routing\RouteCollection;

/**
 * Alter facet source routes, adding a parameter.
 *
 * Required since otherwise Drupal won't call Page Manager for faceted Shop.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  public function alterRoutes(RouteCollection $collection) {
    try {
      $url = Url::fromUri('internal:/collections/{facets_query}');

      if ($url && $sourceRoute = $collection->get($url->getRouteName())) {
        $sourceRoute->setRequirement('facets_query', '.*');

        // Core improperly checks for route parameters that can have a slash
        // in them, only making the route match for parameters that don't
        // have a slash.
        // Workaround that here by adding fake optional parameters to the
        // route, that'll never be filled, and won't get any value set because
        // {facets_query} will already have matched the whole path.
        // Note that until the core bug is resolved, the path maximum length
        // of 255 in the router table induces a limit to the number of facets
        // that can be triggered, which will depend on the facets source path
        // length. For a base path of "/search", 40 placeholders can be added,
        // which means 20 active filter pairs.
        // See https://www.drupal.org/project/drupal/issues/2741939
        $routePath = $sourceRoute->getPath();

        for ($i = 0; strlen($routePath) < 250; $i++) {
          $sourceRoute->setDefault('f' . $i, '');
          $routePath .= "/{f{$i}}";
        }

        $sourceRoute->setPath($routePath);
      }
    }
    catch (\Exception $e) {
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // Run after Page Manager.
    $events[RoutingEvents::ALTER][] = ['onAlterRoutes', -170];
    return $events;
  }

}
