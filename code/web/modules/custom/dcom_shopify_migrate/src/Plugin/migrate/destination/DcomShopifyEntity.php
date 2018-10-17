<?php

namespace Drupal\dcom_shopify_migrate\Plugin\migrate\destination;

use Drupal\migrate\Plugin\migrate\destination\Entity;

/**
 * Provides an extended generic destination to import entities.
 *
 * @MigrateDestination(
 *   id = "dcom_shopify_entity",
 *   deriver = "Drupal\dcom_shopify_migrate\Plugin\Derivative\DcomShopifyMigrateEntity"
 * )
 *
 * @see Drupal\migrate\Plugin\migrate\destination\Entity
 */
abstract class DcomShopifyEntity extends Entity {

}
