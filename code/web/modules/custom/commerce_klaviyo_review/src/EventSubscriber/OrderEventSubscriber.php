<?php

namespace Drupal\commerce_klaviyo_review\EventSubscriber;

use Drupal\commerce_klaviyo_review\ReviewBuilderInterface;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Order events subscriber for notifying Klaviyo about placing/fulfillment.
 */
class OrderEventSubscriber implements EventSubscriberInterface {

  /**
   * The order total summary service.
   *
   * @var \Drupal\commerce_order\OrderTotalSummaryInterface
   */
  protected $orderTotalSummary;

  /**
   * The klaviyo review storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $reviewStorage;

  /**
   * Creates an instance of the class.
   *
   * @param \Drupal\commerce_klaviyo_review\ReviewBuilderInterface $review_builder
   *   Review builder.
   */
  public function __construct(ReviewBuilderInterface $review_builder) {
    $this->reviewBuilder = $review_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [
      'commerce_order.fulfill.post_transition' => 'onFulfillTransition',
    ];
    return $events;
  }

  /**
   * Creates klaviyo review entity associated with current order.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The transition event.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   See \Drupal\Core\Entity\EntityTypeManagerInterface::getStorage().
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   See \Drupal\Core\Entity\EntityTypeManagerInterface::getStorage().
   */
  public function onFulfillTransition(WorkflowTransitionEvent $event) {
    $order = $event->getEntity();
    $this->reviewBuilder->createReview($order);
  }

}
