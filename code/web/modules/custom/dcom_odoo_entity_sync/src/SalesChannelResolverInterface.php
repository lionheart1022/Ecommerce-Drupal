<?php

namespace Drupal\dcom_odoo_entity_sync;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\domain\DomainInterface;

/**
 * Odoo Sales Channel resolver interface.
 */
interface SalesChannelResolverInterface {

  /**
   * Resolve Odoo sales channel ID by domain.
   *
   * @param \Drupal\domain\DomainInterface $domain
   *   Domain object.
   *
   * @return int
   *   Odoo sales channel ID.
   */
  public function resolveByDomain(DomainInterface $domain);

  /**
   * Resolve Odoo sales channel ID by order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   Order object.
   *
   * @return int
   *   Odoo sales channel ID.
   */
  public function resolveByOrder(OrderInterface $order);

}
