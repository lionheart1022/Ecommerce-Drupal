<?php

namespace Drupal\commerce_affiliates\EventSubscriber;

use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Class AffiliatesTrackingSubscriber.
 */
class AffiliatesTrackingSubscriber implements EventSubscriberInterface {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new AffiliatesTrackingSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [
      'commerce_order.cancel.pre_transition' => ['cancelAffiliateTransaction'],
    ];
    return $events;
  }

  /**
   * Rollback tracking for canceled order.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The transition event.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function cancelAffiliateTransaction(WorkflowTransitionEvent $event) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $event->getEntity();

    $affiliate_type_storage = $this->entityTypeManager->getStorage('commerce_affiliate_type');
    /** @var \Drupal\commerce_affiliates\Entity\AffiliateTypeInterface[] $affiliate_types */
    $affiliate_types = $affiliate_type_storage->loadMultiple();
    foreach ($affiliate_types as $affiliate_type) {
      if ($affiliate_type->status()) {
        $affiliate_plugin = $affiliate_type->getPlugin();
        $affiliate_plugin->cancelTransaction($order, 'order_canceled');
      }
    }
  }

}
