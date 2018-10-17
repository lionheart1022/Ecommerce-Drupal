<?php

namespace Drupal\commerce_klaviyo_review;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\commerce_klaviyo_review\Event\GetReviewLinkEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class CommerceKlaviyoReviewReviewBuilder.
 */
class ReviewBuilder implements ReviewBuilderInterface {

  /**
   * Drupal\Core\Config\ConfigFactoryInterface definition.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new CommerceKlaviyoReviewReviewBuilder object.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EventDispatcherInterface $event_dispatcher, EntityTypeManagerInterface $entity_type_manager) {
    $this->configFactory = $config_factory;
    $this->eventDispatcher = $event_dispatcher;
    $this->reviewStorage = $entity_type_manager->getStorage('klaviyo_review');
  }

  /**
   * {@inheritdoc}
   */
  public function getReviewLink(OrderInterface $order) {
    $order_id = $order->id();
    $url = new Url('commerce_klaviyo_review.order_review', [
      'commerce_order' => $order_id,
      'token' => Crypt::hmacBase64($order_id, Settings::getHashSalt()),
    ], []);

    $this->eventDispatcher->dispatch(GetReviewLinkEvent::GET_REVIEW_LINK_EVENT, new GetReviewLinkEvent($url, $order));

    $url->setAbsolute();
    return $url->toString();
  }

  /**
   * {@inheritdoc}
   */
  public function createReview(OrderInterface $order) {
    $order_id = $order->id();
    $data = [
      'order_id' => $order_id,
      'token' => Crypt::hmacBase64($order_id, Settings::getHashSalt()),
      'user_id' => $order->getCustomerId(),
      'status' => 0,
    ];
    $klaviyo_review = $this->reviewStorage->create($data);
    $klaviyo_review->save();
  }

}
