<?php

namespace Drupal\dcom_rakuten\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\commerce_price\Price;
use Drupal\commerce_price\RounderInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\Component\Serialization\SerializationInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides Rakuten Marketing Tracking pane.
 *
 * @CommerceCheckoutPane(
 *   id = "dcom_rakuten_marketing",
 *   label = @Translation("Rakuten Marketing Tracking"),
 *   default_step = "complete",
 * )
 */
class RakutenTracking extends CheckoutPaneBase {

  /**
   * JSON serialization service.
   *
   * @var \Drupal\Component\Serialization\SerializationInterface
   */
  protected $json;

  /**
   * Price rounder service.
   *
   * @var \Drupal\commerce_price\RounderInterface
   */
  protected $rounder;

  /**
   * Constructs a new RakutenTracking object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface $checkout_flow
   *   The parent checkout flow.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Component\Serialization\SerializationInterface $json_serializer
   *   JSON serialization service.
   * @param \Drupal\commerce_price\RounderInterface $rounder
   *   Price rounder service.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   Logger channel.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CheckoutFlowInterface $checkout_flow, EntityTypeManagerInterface $entity_type_manager, SerializationInterface $json_serializer, RounderInterface $rounder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $checkout_flow, $entity_type_manager);

    $this->json = $json_serializer;
    $this->rounder = $rounder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, CheckoutFlowInterface $checkout_flow = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $checkout_flow,
      $container->get('entity_type.manager'),
      $container->get('serialization.json'),
      $container->get('commerce_price.rounder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    try {
      $pane_form['#attributes']['data-rakuten-tracking'] = $this->json->encode($this->trackingData());
    }
    catch (\Exception $e) {
      \Drupal::logger('dcom_rakuten')->error('An exception occured in Rakuten tracking code generation process. Skipping.');
    }

    $pane_form['#attributes']['style'] = ['height:0;', 'width:0;'];
    $pane_form['#attributes']['class'][] = 'dcom-rakuten-tracking';
    $pane_form['#attached']['library'][] = 'dcom_rakuten/checkout_tracking';

    return $pane_form;
  }

  /**
   * Get Rakuten Marketing tracking data.
   *
   * @return array
   *   Tracking data array.
   */
  protected function trackingData() {
    $data = [
      'affiliateConfig' => [
        'ranMID' => 43500,
        'discountType' => 'item',
      ],
      'orderid' => $this->order->id(),
      'currency' => $this->order->getTotalPrice()->getCurrencyCode(),
      'customerStatus' => $this->getCustomerStatus(),
      'conversionType' => 'Sale',
      'customerID' => $this->order->getCustomerId(),
      'discountCode' => $this->getDiscountCode(),
      'discountAmount' => $this->getDiscountAmount(),
      'taxAmount' => $this->getTaxAmount(),
      'lineitems' => $this->getLineItemsData(),
    ];

    return $data;
  }

  /**
   * Get list or order line items for Rakuten.
   *
   * @return array
   *   Tracking data lineitems array.
   */
  protected function getLineItemsData() {
    $line_items = [];

    foreach ($this->order->getItems() as $order_item) {
      $purchased = $order_item->getPurchasedEntity();
      if ($purchased instanceof ProductVariationInterface) {
        $line_items[] = [
          'quantity' => $order_item->getQuantity(),
          'unitPrice' => $this->rounder->round($order_item->getAdjustedUnitPrice())->getNumber(),
          'unitPriceLessTax' => $order_item->getAdjustedUnitPriceWithoutTax()->getNumber(),
          'SKU' => $purchased->getSku(),
          'productName' => $purchased->getTitle(),
        ];
      }
    }

    return $line_items;
  }

  /**
   * Get customer status.
   *
   * @return string
   *   Customer Status as 'Existing' or 'New'.
   */
  protected function getCustomerStatus() {
    $query = $this
      ->entityTypeManager
      ->getStorage('commerce_order')
      ->getQuery();

    // Count existing orders.
    $orders_count = $query
      ->accessCheck(FALSE)
      ->condition('cart', FALSE)
      ->condition('state', 'draft', '!=')
      ->condition('order_id', $this->order->id(), '!=')
      ->condition('uid', $this->order->getCustomerId())
      ->count()
      ->execute();

    return $orders_count ? 'Existing' : 'New';
  }

  /**
   * Get order discount code.
   *
   * @return string
   *   Order coupon code or empty string.
   */
  protected function getDiscountCode() {
    if (empty($this->order->coupons->entity)) {
      return '';
    }

    /** @var \Drupal\commerce_promotion\Entity\CouponInterface $coupon */
    $coupon = $this->order->coupons->entity;
    return $coupon->getCode();
  }

  /**
   * Get order discount amount.
   *
   * From Rakuten doc:
   * If the user has qualified for any discounts to their order based on a
   * discount code that they’ve entered, please populate the total discount
   * amount into this field. If no discount code was entered, please leave the
   * parameter with a 0 value. Note that only numeric values should be
   * submitted with any currency codes or thousands delimiters removed.
   *
   * @return float
   *   Total order discount amount.
   */
  protected function getDiscountAmount() {
    if (empty($this->order->coupons->entity)) {
      return 0;
    }

    /** @var \Drupal\commerce_promotion\Entity\CouponInterface $coupon */
    $coupon = $this->order->coupons->entity;
    $discount = new Price(0, $this->order->getTotalPrice()
      ->getCurrencyCode());

    foreach ($this->order->collectAdjustments() as $adjustment) {
      // Only discounts based on coupon code are calculated.
      if ($adjustment->getType() == 'promotion'
        && $adjustment->getSourceId() == $coupon->getPromotionId()) {
        $discount = $discount->add($adjustment->getAmount());
      }
    }

    return $this->rounder->round($discount)->getNumber();
  }

  /**
   * Get order tax amount.
   *
   * @return float
   *   Total order tax amount.
   */
  protected function getTaxAmount() {
    $tax = new Price(0, $this->order->getTotalPrice()->getCurrencyCode());

    foreach ($this->order->collectAdjustments() as $adjustment) {
      if ($adjustment->getType() == 'tax') {
        $tax = $tax->add($adjustment->getAmount());
      }
    }

    return $this->rounder->round($tax)->getNumber();
  }

}
