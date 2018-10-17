<?php

namespace Drupal\dcom_cart\EventSubscriber;

use Drupal\commerce_cart\Event\CartEntityAddEvent;
use Drupal\commerce_cart\EventSubscriber\CartEventSubscriber as CommerceCartEventSubscriber;

/**
 * Cart event subscriber.
 */
class CartEventSubscriber extends CommerceCartEventSubscriber {

  /**
   * {@inheritdoc}
   */
  public function displayAddToCartMessage(CartEntityAddEvent $event) {
    // Do not show messages for extra items.
    if ($event->getOrderItem()->type->target_id != 'extra_item') {
      parent::displayAddToCartMessage($event);
    }
  }

}
