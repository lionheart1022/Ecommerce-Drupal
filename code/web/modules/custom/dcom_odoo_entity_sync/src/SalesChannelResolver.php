<?php

namespace Drupal\dcom_odoo_entity_sync;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\domain\DomainInterface;

/**
 * Odoo Sales Channel resolver service.
 */
class SalesChannelResolver implements SalesChannelResolverInterface {

  /**
   * {@inheritdoc}
   */
  public function resolveByDomain(DomainInterface $domain) {
    $map = [
      // Export old orders as 'Diamond CBD website'.
      'default' => 7,
      'diamondcbd_domain' => 7,
      'mbio_domain' => 9,
      'medipets_domain' => 10,
    ];

    if (isset($map[$domain->id()])) {
      return $map[$domain->id()];
    }

    return $this->getDefaultSalesChannelId();
  }

  /**
   * {@inheritdoc}
   */
  public function resolveByOrder(OrderInterface $order) {
    if ($order->hasField('field_order_source') && !$order->get('field_order_source')->isEmpty()) {
      $order_source = $order->get('field_order_source')->first()->getValue()['value'];

      if ($order_source == 'shopify_wholesale') {
        // Odoo ID 4 = Shopify Website (wholesale);
        return 4;
      }
      elseif ($order_source == 'shopify_retail') {
        // Odoo ID 3 = Shopify Website (retail);
        return 3;
      }
    }

    if (!empty($order->field_domain->entity)) {
      return $this->resolveByDomain($order->field_domain->entity);
    }

    return $this->getDefaultSalesChannelId();
  }

  /**
   * Default sales channel ID.
   *
   * @return int
   *   Default value.
   */
  protected function getDefaultSalesChannelId() {
    return 11;
  }

}
