<?php

namespace Drupal\commerce_affiliates\Plugin\Commerce\Affiliates;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\EntityAdjustableInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_price\RounderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ConversantCjAffiliate.
 *
 * @code
 *  <iframe
 *    height="1"
 *    width="1"
 *    frameborder="0"
 *    scrolling="no"
 *    src="https://www.emjcd.com/tags/c?containerTagId=[ContainerID]&TYPE=[TYPE]&CID=[CID]&ITEMx=[ItemSku]&AMTx=[AmountofItem]&QTYx=[Quantity]&DCNTx=[ItemDiscount]&OID=[OID]&DISCOUNT=[DiscountAmount]&CURRENCY=[CURRENCY]&COUPON=[couponcode]"
 *   name="cj_conversion">
 *  </iframe>
 * @endcode
 *
 * @CommerceAffiliate(
 *   id = "conversant_cj_affiliate",
 *   label = @Translation("Conversant CJ (formerly Commission Junction)"),
 *   trackingType = "html",
 * )
 */
class ConversantCjAffiliate extends AffiliateBase implements ContainerFactoryPluginInterface {

  /**
   * The rounder.
   *
   * @var \Drupal\commerce_price\RounderInterface
   */
  protected $rounder;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    RounderInterface $rounder
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->rounder = $rounder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('commerce_price.rounder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'container_tag_id' => '',
      'action_id' => '',
      'cid' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['container_tag_id'] = [
      '#type' => 'textfield',
      '#title' => 'ContainerTagID',
      '#required' => TRUE,
      '#default_value' => $this->configuration['container_tag_id'],
    ];
    $form['action_id'] = [
      '#type' => 'textfield',
      '#title' => 'Action ID (TYPE)',
      '#required' => TRUE,
      '#default_value' => $this->configuration['action_id'],
    ];
    $form['cid'] = [
      '#type' => 'textfield',
      '#title' => 'CID',
      '#required' => TRUE,
      '#default_value' => $this->configuration['cid'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function track(OrderInterface $order) {
    $query = [
      'containerTagId' => $this->configuration['container_tag_id'],
      'TYPE' => $this->configuration['action_id'],
      'CID' => $this->configuration['cid'],
      'OID' => $order->getOrderNumber(),
      'CURRENCY' => $order->getTotalPrice()->getCurrencyCode(),
      'DISCOUNT' => $this->getDiscountAmount($order),
      'COUPON' => $this->getOrderCouponsString($order),
    ];

    $query = array_merge($query, $this->prepareOrderItemsQuery($order));

    $url = Url::fromUri('https://www.emjcd.com/tags/c', ['query' => $query]);

    return [
      '#type' => 'html_tag',
      '#tag' => 'iframe',
      '#attributes' => [
        'src' => $url->toUriString(),
        'name' => 'cj_conversion',
        'height' => '1',
        'width' => '1',
        'frameborder' => '0',
        'scrolling' => 'no',
      ],
    ];
  }

  /**
   * Prepare array with url query parameters for each Order Item.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   Commerce Order entity.
   *
   * @return array
   *   CJ query parameters for each Order Item.
   */
  protected function prepareOrderItemsQuery(OrderInterface $order) {
    $order_item_query = [];

    // The item level parameters (ITEM,QTY, AMT and DCNT) need to start with the
    // index of 1 , instead of 0.
    $index = 1;
    foreach ($order->getItems() as $order_item) {
      if ($product = $order_item->purchased_entity->referencedEntities()) {
        /** @var \Drupal\commerce_product\Entity\ProductVariation $product */
        $product = reset($product);
        $order_item_query['ITEM' . $index] = $product->getSku();
        $order_item_query['AMT' . $index] = $this->rounder->round($order_item->getUnitPrice())->getNumber();
        $order_item_query['QTY' . $index] = (int) $order_item->getQuantity();
        // TODO - is it necessary to multiply discount by the quantity?
        $order_item_query['DCNT' . $index] = $this->getDiscountAmount($order_item);

        $index++;
      }
    }
    return $order_item_query;
  }

  /**
   * Return string with coupon ID.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   Commerce Order entity.
   *
   * @return string
   *   String with coupon ID or multiple codes separated by commas.
   */
  protected function getOrderCouponsString(OrderInterface $order) {
    $coupons = '';
    if ($order->hasField('coupons')) {
      // Multiple codes can be passed, separated by commas.
      $coupons = $order->coupons->getString();
    }
    return $coupons;
  }

  /**
   * Calculate discount amount of Order.
   *
   * @param \Drupal\commerce_order\EntityAdjustableInterface $element
   *   Commerce Order or Order Item entity.
   *
   * @return float|int
   *   Order discount amount.
   */
  protected function getDiscountAmount(EntityAdjustableInterface $element) {
    $currency_code = $element->getTotalPrice()->getCurrencyCode();
    $discount_total = new Price('0', $currency_code);
    foreach ($element->getAdjustments() as $adjustment) {
      if ($adjustment->getType() == 'promotion') {
        $discount_total = $discount_total->add($adjustment->getAmount());
      }
    }
    return abs($this->rounder->round($discount_total)->getNumber());
  }

  /**
   * {@inheritdoc}
   */
  public function cancelTransaction(OrderInterface $order, $event_type) {
    // TODO: Implement cancelTransaction() method.
  }

}
