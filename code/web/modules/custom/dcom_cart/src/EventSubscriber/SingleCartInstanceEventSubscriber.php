<?php

namespace Drupal\dcom_cart\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\commerce_order\Event\OrderEvents;
use Drupal\commerce_order\Event\OrderAssignEvent;
use Drupal\commerce_cart\CartProviderInterface;

/**
 * Force the user to only have one cart instance.
 */
class SingleCartInstanceEventSubscriber implements EventSubscriberInterface {

  /**
   * The cart provider.
   *
   * @var \Drupal\commerce_cart\CartProviderInterface
   */
  protected $cartProvider;

  /**
   * SingleCartInstanceEventSubscriber constructor.
   *
   * @param \Drupal\commerce_cart\CartProviderInterface $cart_provider
   *   The cart provider.
   */
  public function __construct(CartProviderInterface $cart_provider) {
    $this->cartProvider = $cart_provider;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];

    $events[OrderEvents::ORDER_ASSIGN][] = ['onAssign'];

    return $events;

  }

  /**
   * Users may only have one cart instance.
   *
   * We keep the last created one and
   * delete previously created ones.
   *
   * @param \Drupal\commerce_order\Event\OrderAssignEvent $event
   *   The order assign event.
   */
  public function onAssign(OrderAssignEvent $event) {
    $account = $event->getAccount();

    $carts = $this->cartProvider->getCarts($account);
    if (!empty($carts)) {
      foreach ($carts as $cart) {
        $cart->delete();
      }
    }
  }

}
