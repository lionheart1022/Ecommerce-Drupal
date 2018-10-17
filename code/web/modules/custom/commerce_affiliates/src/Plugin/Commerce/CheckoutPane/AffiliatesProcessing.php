<?php

namespace Drupal\commerce_affiliates\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the affiliates processing pane.
 *
 * @CommerceCheckoutPane(
 *   id = "affiliates_processing",
 *   label = @Translation("Affiliates Processing"),
 *   default_step = "complete",
 * )
 */
class AffiliatesProcessing extends CheckoutPaneBase {

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $pane_form['#attributes']['style'] = ['height:0;', 'width:0;'];
    return $pane_form + $this->applyTracking($this->order);
  }

  /**
   * Apply tracking for "api" plugins and collect images for "html" plugins.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   Commerce Order entity.
   *
   * @return array
   *   List of "html" images with tracking src.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function applyTracking(OrderInterface $order) {
    $html = [];
    $affiliate_type_storage = $this->entityTypeManager->getStorage('commerce_affiliate_type');
    /** @var \Drupal\commerce_affiliates\Entity\AffiliateTypeInterface[] $affiliate_types */
    $affiliate_types = $affiliate_type_storage->loadMultiple();

    $context = ['order_checkout' => $order];
    \Drupal::moduleHandler()->alter('commerce_affiliate_types', $affiliate_types, $context);

    $affiliate_types = array_filter($affiliate_types, function ($affiliate_type) {
      return $affiliate_type->status();
    });

    foreach ($affiliate_types as $affiliate_type) {
      $affiliate_plugin = $affiliate_type->getPlugin();
      switch ($affiliate_plugin->getTrackingType()) {
        case 'html':
          $html[] = $affiliate_plugin->track($order);
          break;

        default:
          $affiliate_plugin->track($order);
          break;
      }
    }
    return $html;
  }

}
