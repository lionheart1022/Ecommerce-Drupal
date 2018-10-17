<?php

namespace Drupal\dcom_odoo_migrate\EventSubscriber;

use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateImportEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The CurrentMigration service.
 *
 * Subscribes to save an information about the current running migration.
 */
class CurrentMigration implements EventSubscriberInterface {

  /**
   * The current running migration.
   *
   * Track the migration currently running, so handlers can easily determine it
   * without having to pass a Migration object everywhere.
   *
   * @var \Drupal\migrate\Plugin\MigrationInterface
   */
  protected static $currentMigration;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[MigrateEvents::PRE_IMPORT] = ['onMigratePreImport'];
    $events[MigrateEvents::POST_IMPORT] = ['onMigratePostImport'];

    return $events;
  }

  /**
   * Saves the current running migration.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $event
   *   The migrate import event.
   */
  public function onMigratePreImport(MigrateImportEvent $event) {
    static::$currentMigration = $event->getMigration();
  }

  /**
   * Resets the current migration property.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $event
   *   The migrate import event.
   */
  public function onMigratePostImport(MigrateImportEvent $event) {
    static::$currentMigration = NULL;
  }

  /**
   * Gets the current running migration.
   *
   * @return \Drupal\migrate\Plugin\MigrationInterface|null
   *   The current running migration or NULL if no migration is running.
   */
  public static function getCurrentMigration() {
    return static::$currentMigration;
  }

}
