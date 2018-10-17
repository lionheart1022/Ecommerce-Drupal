<?php

namespace Drupal\dcom_common;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Class DcomCommonServiceProvider.
 *
 * @package Drupal\dcom_common
 */
class DcomCommonServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $definition = $container->getDefinition('commerce_extra_items.promotion_order_processor');
    $tags = $definition->getTags();

    /*
     * The patch
     * https://drupal.org/files/issues/commerce-tax-discounts-2897190-29.patch
     * increases the priority of the PromotionOrderProcessor to 150 which is
     * higher than CommerceExtraItemsOrderProcessor. Therefore extra items are
     * added firstly and removed then. This is NOT the correct order.
     * Since the patch is not committed - fix it with a temporary solution -
     * using alters.
     */
    // TODO Remove this alter once the patch above is committed/declined.
    if (!empty($tags['commerce_order.order_processor'][0]['priority'])) {
      $tags['commerce_order.order_processor'][0]['priority'] = 170;
      $definition->setTags($tags);
    }
  }

}
