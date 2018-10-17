<?php

namespace Drupal\dcom_back_in_stock\CacheContext;

use Drupal\Core\Cache\Context\CacheContextInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Session\SessionManager;

/**
 * Defines the StockNotificationCacheContext service.
 *
 * Cache context ID: 'stock_notification_cc'.
 */
class StockNotificationCacheContext implements CacheContextInterface {

  /**
   * Current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Current session id.
   *
   * @var \Drupal\Core\Session\SessionManager
   */
  protected $sessionId;

  /**
   * Entity query.
   *
   * @var \Drupal\Core\Entity\Query\QueryFactory
   */
  protected $entityQuery;

  /**
   * StockNotificationCacheContext constructor.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   Current user.
   * @param \Drupal\Core\Entity\Query\QueryFactory $entity_query
   *   Entity query.
   * @param \Drupal\Core\Session\SessionManager $session_id
   *   Current session id.
   */
  public function __construct(AccountProxyInterface $current_user, QueryFactory $entity_query, SessionManager $session_id) {
    $this->currentUser = $current_user;
    $this->entityQuery = $entity_query;
    $this->sessionId = $session_id;
  }

  /**
   * {@inheritdoc}
   */
  public static function getLabel() {
    return t('Stock Notification Cache Context');
  }

  /**
   * {@inheritdoc}
   */
  public function getContext() {
    $query = $this->entityQuery->get('stock_notification');
    // Force save session for anonymous.
    // @see \Drupal\Core\Session\SessionManager::start()
    $_SESSION['save'] = 1;

    $group = $query->orConditionGroup()
      ->condition('user_id', $this->currentUser->getAccount()->id())
      ->condition('session_id', $this->sessionId->getId());

    return $query
      ->condition($group)
      ->range(0, 1)
      ->sort('changed', 'DESC')
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata() {
    return new CacheableMetadata();
  }

}
