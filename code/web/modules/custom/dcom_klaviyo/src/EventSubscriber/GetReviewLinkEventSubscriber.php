<?php

namespace Drupal\dcom_klaviyo\EventSubscriber;

use Drupal\commerce_klaviyo_review\Event\GetReviewLinkEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\domain\DomainLoaderInterface;

/**
 * Class GetReviewLinkEventSubscriber.
 */
class GetReviewLinkEventSubscriber implements EventSubscriberInterface {

  /**
   * Drupal\domain\DomainLoaderInterface definition.
   *
   * @var \Drupal\domain\DomainLoaderInterface
   */
  protected $domainLoader;

  /**
   * Constructs a new GetReviewLinkEventSubscriber object.
   */
  public function __construct(DomainLoaderInterface $domain_loader) {
    $this->domainLoader = $domain_loader;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[GetReviewLinkEvent::GET_REVIEW_LINK_EVENT] = ['onGetReviewLink'];

    return $events;
  }

  /**
   * Adjusting review URL.
   *
   * @param \Drupal\commerce_klaviyo_review\Event\GetReviewLinkEvent $event
   *   Get review link event.
   */
  public function onGetReviewLink(GetReviewLinkEvent $event) {
    $url = $event->getReviewUrl();
    $order = $event->getReviewOrder();
    // Domain specific handling.
    if ($order->hasField('field_domain') && $domain_id = $order->get('field_domain')->getString()) {
      $domain = $this->domainLoader->load($domain_id);
      $url->setOption('base_url', $domain->getRawPath());
    }
  }

}
