<?php

namespace Drupal\commerce_nmi\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\commerce\Response\NeedsRedirectException;
use Drupal\commerce_checkout\CheckoutOrderManagerInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Controller\PaymentCheckoutController as BasePaymentCheckoutController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Extends payment endpoints for off-site payments.
 */
class PaymentCheckoutController extends BasePaymentCheckoutController {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new PaymentCheckoutController object.
   *
   * @param \Drupal\commerce_checkout\CheckoutOrderManagerInterface $checkout_order_manager
   *   The checkout order manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(CheckoutOrderManagerInterface $checkout_order_manager, MessengerInterface $messenger, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($checkout_order_manager, $messenger);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('commerce_checkout.checkout_order_manager'),
      $container->get('messenger'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function returnPage(OrderInterface $commerce_order, Request $request) {
    try {
      parent::returnPage($commerce_order, $request);
    }
    catch (NeedsRedirectException $e) {
      throw new NeedsRedirectException(Url::fromRoute('entity.commerce_payment.collection', [
        'commerce_order' => $commerce_order->id(),
      ])->toString());
    }
  }

  /**
   * Checks access for the admin payment return page.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function checkAccess(RouteMatchInterface $route_match, AccountInterface $account) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $route_match->getParameter('commerce_order');
    if ($order->getState()->value == 'canceled' || !$account->isAuthenticated()) {
      return AccessResult::forbidden()->addCacheableDependency($order);
    }

    /** @var \Drupal\commerce_payment\PaymentAccessControlHandler $payment_access */
    $payment_access = $this->entityTypeManager->getAccessControlHandler('commerce_payment');
    $customer_check = AccessResult::allowedIf($account->id() == $order->getCustomerId())
      ->orIf($payment_access->createAccess(NULL, $account, [], TRUE));

    $access = AccessResult::allowedIf($customer_check)
      ->andIf(AccessResult::allowedIf($order->hasItems()))
      ->addCacheableDependency($order);

    return $access;
  }

}
