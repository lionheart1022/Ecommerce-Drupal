<?php

namespace Drupal\dcom_back_in_stock;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a StockNotification entity.
 *
 * @ingroup content_entity_example
 */
interface StockNotificationInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {}
