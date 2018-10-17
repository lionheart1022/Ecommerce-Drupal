<?php

namespace Drupal\dcom_tax\Plugin\Commerce\TaxType;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_taxcloud\Plugin\Commerce\TaxType\TaxCloud as TaxCloudBase;

/**
 * Custom TaxCloud plugin.
 *
 * @CommerceTaxType(
 *   id = "dcom_taxcloud_retail",
 *   label = @Translation("Diamond Commerce TaxCloud - Retail Only"),
 * )
 */
class TaxCloud extends TaxCloudBase {

  /**
   * {@inheritdoc}
   */
  public function applies(OrderInterface $order) {
    return $this->isRetailOrder($order) && parent::applies($order);
  }

  /**
   * Checks if given order is retail.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   Order entity.
   *
   * @return bool
   *   Whether given order is retail.
   */
  protected function isRetailOrder(OrderInterface $order) {
    if (!($customer = $order->getCustomer())) {
      // Anonymous orders are always retail.
      return TRUE;
    }

    // @TODO: Implement multiple wholesale roles.
    return !in_array('wholesale_1', $customer->getRoles());
  }

}
