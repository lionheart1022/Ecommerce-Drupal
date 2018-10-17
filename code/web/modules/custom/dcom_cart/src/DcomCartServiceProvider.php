<?php

namespace Drupal\dcom_cart;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Custom service provider class.
 */
class DcomCartServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Unset Commerce Cart event subscriber, we're replacing it with our own.
    // @see \Drupal\dcom_cart\EventSubscriber\CartEventSubscriber
    try {
      $definition = $container->getDefinition('commerce_cart.cart_subscriber');
      $definition->clearTag('event_subscriber');
    }
    catch (ServiceNotFoundException $e) {
    }

    try {
      $cart_provider_definition = $container->getDefinition('commerce_cart.cart_provider');
      $cart_provider_definition->setClass('Drupal\dcom_cart\DcomCartProvider')->addArgument(new Reference('domain.negotiator'));
    }
    catch (ServiceNotFoundException $e) {
    }
  }

}
