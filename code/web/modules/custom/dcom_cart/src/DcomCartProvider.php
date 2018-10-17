<?php

namespace Drupal\dcom_cart;

use Drupal\commerce_cart\CartProvider;
use Drupal\commerce_cart\CartSessionInterface;
use Drupal\commerce_store\CurrentStoreInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\domain\DomainNegotiatorInterface;

/**
 * Default implementation of the cart provider.
 */
class DcomCartProvider extends CartProvider {

  /**
   * The domain negotiator.
   *
   * @var \Drupal\domain\DomainNegotiatorInterface
   */
  protected $domainNegotiator;

  /**
   * Constructs a new DcomCartProvider object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\commerce_store\CurrentStoreInterface $current_store
   *   The current store.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\commerce_cart\CartSessionInterface $cart_session
   *   The cart session.
   * @param \Drupal\domain\DomainNegotiatorInterface $domain_negotiator
   *   The domain negotiator.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    CurrentStoreInterface $current_store,
    AccountInterface $current_user,
    CartSessionInterface $cart_session,
    DomainNegotiatorInterface $domain_negotiator
  ) {
    parent::__construct($entity_type_manager, $current_store, $current_user, $cart_session);

    $this->domainNegotiator = $domain_negotiator;
  }

  /**
   * Loads the cart data for the given user.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user. If empty, the current user is assumed.
   *
   * @return array
   *   The cart data.
   */
  protected function loadCartData(AccountInterface $account = NULL) {
    $account = $account ?: $this->currentUser;
    $uid = $account->id();
    if (isset($this->cartData[$uid])) {
      return $this->cartData[$uid];
    }

    // Finds a Carts assigned to active domain.
    $cart_ids = $this->findCartIds($account);

    $this->cartData[$uid] = [];
    if (!$cart_ids) {
      return [];
    }
    // Getting the cart data and validating the cart IDs received from the
    // session requires loading the entities. This is a performance hit, but
    // it's assumed that these entities would be loaded at one point anyway.
    /** @var \Drupal\commerce_order\Entity\OrderInterface[] $carts */
    $carts = $this->orderStorage->loadMultiple($cart_ids);
    $non_eligible_cart_ids = [];
    foreach ($carts as $cart) {
      if ($cart->isLocked()) {
        // Skip locked carts, the customer is probably off-site for payment.
        continue;
      }
      if ($cart->getCustomerId() != $uid || empty($cart->cart) || $cart->getState()->value != 'draft') {
        // Skip carts that are no longer eligible.
        $non_eligible_cart_ids[] = $cart->id();
        continue;
      }

      $this->cartData[$uid][$cart->id()] = [
        'type' => $cart->bundle(),
        'store_id' => $cart->getStoreId(),
      ];
    }
    // Avoid loading non-eligible carts on the next page load.
    if (!$account->isAuthenticated()) {
      foreach ($non_eligible_cart_ids as $cart_id) {
        $this->cartSession->deleteCartId($cart_id);
      }
    }

    return $this->cartData[$uid];
  }

  /**
   * Find a cart assigned to active domain.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   User account.
   *
   * @return array|int|int[]
   *   Cart Order IDs.
   */
  protected function findCartIds(AccountInterface $account) {
    if ($account->isAuthenticated()) {
      $query = $this->orderStorage->getQuery()
        ->condition('state', 'draft')
        ->condition('cart', TRUE)
        ->condition('uid', $account->id())
        ->sort('order_id', 'DESC');
      $or = $query->orConditionGroup();
      $or
        ->condition('field_domain', $this->domainNegotiator->getActiveId())
        ->notExists('field_domain');
      $query->condition($or);
      $cart_ids = $query->execute();
    }
    else {
      $cart_ids = $this->cartSession->getCartIds();
    }

    return $cart_ids;
  }

}
