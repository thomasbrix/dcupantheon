<?php

namespace Drupal\dcu_api\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * Alter Rest urls to make sure it is not 301 redirectet with language prefix in url
   * as this breaks dcu app
   *
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('rest.user_rest_resource.GET')) {
      $route->setDefault('_disable_route_normalizer', TRUE);
    }
    if ($route = $collection->get('rest.user_favorites_rest_resource.GET')) {
      $route->setDefault('_disable_route_normalizer', TRUE);
    }
    if ($route = $collection->get('rest.remove_user_favorite_rest_resource.GET')) {
      $route->setDefault('_disable_route_normalizer', TRUE);
    }
    if ($route = $collection->get('rest.campsites_rest_resource.GET')) {
      $route->setDefault('_disable_route_normalizer', TRUE);
    }
    if ($route = $collection->get('rest.benefits_rest_resource.GET')) {
      $route->setDefault('_disable_route_normalizer', TRUE);
    }
    if ($route = $collection->get('rest.add_user_favorite_rest_resource.GET')) {
      $route->setDefault('_disable_route_normalizer', TRUE);
    }
    if ($route = $collection->get('rest.queue_rest_resource.GET')) {
      $route->setDefault('_disable_route_normalizer', TRUE);
    }
    if ($route = $collection->get('rest.app_message_rest_resource.GET')) {
      $route->setDefault('_disable_route_normalizer', TRUE);
    }
  }

}

