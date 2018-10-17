<?php

namespace Drupal\dcom_odoo_migrate\EventSubscriber;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Cascade importer service.
 *
 * Forces Cron import of dependant objects.
 */
class CascadeImporter implements EventSubscriberInterface {

  /**
   * Commerce products variations storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $productsStorage;

  /**
   * Logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Cascade importer constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   Logger factory service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Entity type manager exception.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, LoggerChannelFactoryInterface $logger) {
    $this->productsStorage = $entity_type_manager->getStorage('commerce_product');
    $this->logger = $logger->get('dcom_odoo_migrate');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[MigrateEvents::POST_ROW_SAVE] = ['onPostRowSave'];

    return $events;
  }

  /**
   * React on row save.
   *
   * Importing product should force re-import of all of it's variations. To do
   * so, we set field_force_odoo_migrate which is then used by migration cron.
   *
   * @param \Drupal\migrate\Event\MigratePostRowSaveEvent $event
   *   Post row save event.
   */
  public function onPostRowSave(MigratePostRowSaveEvent $event) {
    if ($event->getMigration()->getDestinationPlugin()->getPluginId() == 'entity:commerce_product') {
      $product_ids = $event->getDestinationIdValues();
      if ($product_ids && $products = $this->productsStorage->loadMultiple($product_ids)) {
        foreach ($products as $product) {
          /** @var \Drupal\commerce_product\Entity\ProductInterface $product */
          foreach ($product->getVariations() as $variation) {
            if ($variation->hasField('field_force_odoo_migrate')) {
              $variation->field_force_odoo_migrate->value = TRUE;
              try {
                $variation->save();
              }
              catch (EntityStorageException $e) {
                $this->logger->error('Error saving product variation @id, the next import may not be forced.', ['@id' => $variation->id()]);
              }
            }
          }
        }
      }
    }
  }

}
