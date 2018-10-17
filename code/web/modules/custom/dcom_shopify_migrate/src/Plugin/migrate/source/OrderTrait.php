<?php

namespace Drupal\dcom_shopify_migrate\Plugin\migrate\source;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Trait OrderTrait.
 *
 * @package Drupal\dcom_shopify_migrate\Plugin\migrate\source
 */
trait OrderTrait {

  use StringTranslationTrait;

  /**
   * Returns available fields on the source.
   *
   * @return array
   *   Available fields in the source, keys are the field machine names as used
   *   in field mappings, values are descriptions.
   */
  public function fields() {
    return [
      'id' => $this->t('Order ID'),
      'created_at' => $this->t('Creation date'),
      'processed_at' => $this->t('The date and time (ISO 8601) when an order is said to be created'),
      'updated_at' => $this->t('Update date'),
      'cancelled_at' => $this->t('The date and time (ISO 8601 format) when the order was canceled'),
      'closed_at' => $this->t('The date and time (ISO 8601 format) when the order was closed'),
      'billing_address' => $this->t('Billing address ID'),
      'shipping_address' => $this->t('Shipping address ID'),
      'customer' => $this->t('Customer ID'),
      'line_items' => $this->t('Line items IDs'),
      // @TODO: Add more fields.
    ];
  }

}
