<?php

namespace Drupal\dcom_odoo_migrate\EventSubscriber;

use Drupal\dcom_odoo_migrate\MigrateIdMapTrait;
use Drupal\odoo_api_entity_sync\Event\OdooExportEvent;
use Drupal\odoo_api_entity_sync\MappingManagerInterface;
use Drupal\odoo_api_entity_sync\MappingManagerTrait;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Odoo ID mapper service.
 *
 * Subscribes for Odoo export events and creates corresponding Migrate ID
 * mapping values.
 */
class OdooIdMapper implements EventSubscriberInterface {

  use MigrateIdMapTrait;
  use MappingManagerTrait;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[OdooExportEvent::CREATE] = ['handleCreate'];
    $events[OdooExportEvent::WRITE] = ['handleWrite'];
    $events[MigrateEvents::POST_ROW_SAVE] = ['onMigratePostRowSave'];

    return $events;
  }

  /**
   * React on Odoo object create event.
   *
   * @param \Drupal\odoo_api_entity_sync\Event\OdooExportEvent $event
   *   Event object.
   */
  public function handleCreate(OdooExportEvent $event) {
    $this->saveIdMap($event->getEntity(), $event->getOdooModel(), $event->getOdooObjectId());
  }

  /**
   * React on Odoo object write event.
   *
   * @param \Drupal\odoo_api_entity_sync\Event\OdooExportEvent $event
   *   Event object.
   */
  public function handleWrite(OdooExportEvent $event) {
    $this->saveIdMap($event->getEntity(), $event->getOdooModel(), $event->getOdooObjectId());
  }

  /**
   * Reacts on finishing a migration import operation from Odoo.
   *
   * @param \Drupal\migrate\Event\MigratePostRowSaveEvent $event
   *   The event object.
   */
  public function onMigratePostRowSave(MigratePostRowSaveEvent $event) {
    $migration = $event->getMigration();
    $migration_tags = $migration->getMigrationTags();

    if (in_array('odoo_api_entity_sync_save_mapping', $migration_tags)) {
      $prefix = 'odoo_api_entity_sync_export_type_';
      $prefix_strlen = strlen($prefix);
      $prefix = str_replace('_', '\_', $prefix);

      foreach ($migration_tags as $tag) {
        if (preg_match('/^' . $prefix . '/s', $tag)) {
          // Remove prefix.
          $export_type = substr($tag, $prefix_strlen);
          break;
        }
      }
    }

    // If the migration requires save odoo mapping and has an export type.
    if (isset($export_type)) {
      $destination_plugin_id = $migration->getDestinationPlugin()->getPluginId();
      $destination_plugin_id = explode(':', $destination_plugin_id);

      // Make sure - destination plugin is entity:entity_type_id_here.
      if (count($destination_plugin_id) == 2 && reset($destination_plugin_id) == 'entity') {
        // Fetch entity type, Odoo model, mapping, export type and mapping.
        $entity_type_id = end($destination_plugin_id);
        $odoo_model = $migration->getSourceConfiguration()['model'];
        $entity_ids = $event->getDestinationIdValues();
        $source_ids = $event->getRow()->getSourceIdValues();
        $id_map = [reset($entity_ids) => reset($source_ids)];

        $this->getOdooMappingManager()->setSyncStatus($entity_type_id, $odoo_model, $export_type, $id_map, MappingManagerInterface::STATUS_SYNCED);
      }
    }
  }

}
