<?php

namespace Drupal\dcom_odoo_entity_sync\Exception;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\odoo_api_entity_sync\Exception\ExportException;
use Throwable;

/**
 * Exception in Migrate ID map lookup.
 */
class MigrateLookupException extends ExportException {

  /**
   * {@inheritdoc}
   */
  public function __construct($entity_type, $odoo_model, $entity_id, $message, Throwable $previous = NULL) {
    parent::__construct($entity_type, $odoo_model, 'migrate_lookup', $entity_id, $message, $previous);
  }

  /**
   * {@inheritdoc}
   */
  protected function getExceptionMessage() {
    $arguments = [
      '%message' => $this->getMessage(),
      '%entity_type' => $this->getEntityType(),
      '%odoo_model' => $this->getOdooModel(),
      '%id' => $this->getEntityId(),
    ];
    return (string) (new FormattableMarkup('Migrate ID lookup error. Message: %message. Entity type: %entity_type, Odoo model: %odoo_model, entity ID: %id.', $arguments));
  }

}
