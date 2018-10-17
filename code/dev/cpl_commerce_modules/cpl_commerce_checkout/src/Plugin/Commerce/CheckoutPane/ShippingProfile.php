<?php

namespace Drupal\cpl_commerce_checkout\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\commerce_shipping\OrderShipmentSummaryInterface;
use Drupal\commerce_shipping\PackerManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the shipping information pane.
 *
 * Collects the shipping profile, then the information for each shipment.
 * Assumes that all shipments share the same shipping profile.
 *
 * @CommerceCheckoutPane(
 *   id = "cpl_commerce_checkout_shipping_profile",
 *   label = @Translation("CPL - Shipping information - Profile"),
 *   display_label = @Translation("Shipping address"),
 *   wrapper_element = "fieldset",
 * )
 */
class ShippingProfile extends CheckoutPaneBase implements ContainerFactoryPluginInterface {

  /**
   * The packer manager.
   *
   * @var \Drupal\commerce_shipping\PackerManagerInterface
   */
  protected $packerManager;

  /**
   * The order shipment summary.
   *
   * @var \Drupal\commerce_shipping\OrderShipmentSummaryInterface
   */
  protected $orderShipmentSummary;

  /**
   * Constructs a new ShippingInformation object.
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
   * @param \Drupal\commerce_shipping\PackerManagerInterface $packer_manager
   *   The packer manager.
   * @param \Drupal\commerce_shipping\OrderShipmentSummaryInterface $order_shipment_summary
   *   The order shipment summary.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CheckoutFlowInterface $checkout_flow, EntityTypeManagerInterface $entity_type_manager, PackerManagerInterface $packer_manager, OrderShipmentSummaryInterface $order_shipment_summary) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $checkout_flow, $entity_type_manager);

    $this->packerManager = $packer_manager;
    $this->orderShipmentSummary = $order_shipment_summary;
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
      $container->get('commerce_shipping.packer_manager'),
      $container->get('commerce_shipping.order_shipment_summary')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function isVisible() {
    if (!$this->order->hasField('shipments')) {
      return FALSE;
    }

    // The order must contain at least one shippable purchasable entity.
    foreach ($this->order->getItems() as $order_item) {
      $purchased_entity = $order_item->getPurchasedEntity();
      if ($purchased_entity && $purchased_entity->hasField('weight')) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneSummary() {
    $summary = [];
    if ($this->isVisible()) {
      $summary = $this->orderShipmentSummary->build($this->order);
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $store = $this->order->getStore();
    /** @var \Drupal\profile\Entity\ProfileInterface|null $shipping_profile */
    $shipping_profile = $this->order->getData('shipping_profile');
    $shipping_profile = $shipping_profile ?: $form_state->get('shipping_profile');

    if (!$shipping_profile) {
      $shipping_profile = $this->getShippingProfile();
      $form_state->set('shipping_profile', $shipping_profile);
    }
    elseif (!$shipping_profile->isNew()) {
      /*
       * Fix the following problem: user enters a shipping information and
       * goes to the "Shipping method" checkout step -> a shipping profile is
       * created or reused an existing one, the order data "shipping_profile"
       * and the form state property "shipping_profile" is equal to
       * the created/reused profile -> On another browser window the user
       * updates the profile. Now the profile entity and order->data
       * 'shipping_profile' && form_state['shipping_profile'] are different.
       * That's why EntityChangedConstraintValidator adds a violation.
       */
      // Update the stored entity with the latest version.
      $shipping_profile = $this->entityTypeManager
        ->getStorage('profile')
        ->load($shipping_profile->id());
    }

    $available_countries = [];
    foreach ($store->get('shipping_countries') as $country_item) {
      $available_countries[] = $country_item->value;
    }

    // Prepare the form for ajax.
    // Not using Html::getUniqueId() on the wrapper ID to avoid #2675688.
    $pane_form['#wrapper_id'] = 'shipping-information-wrapper';
    $pane_form['#prefix'] = '<div id="' . $pane_form['#wrapper_id'] . '">';
    $pane_form['#suffix'] = '</div>';

    $pane_form['shipping_profile'] = [
      '#type' => 'commerce_profile_select',
      '#default_value' => $shipping_profile,
      '#default_country' => $store->getAddress()->getCountryCode(),
      '#available_countries' => $available_countries,
    ];
    $pane_form['removed_shipments'] = [
      '#type' => 'value',
      '#value' => [],
    ];

    $shipments = $this->order->shipments->referencedEntities();
    if (empty($shipments)) {
      list(, $removed_shipments) = $this->packerManager->packToShipments($this->order, $shipping_profile, $shipments);

      // Store the IDs of removed shipments for submitPaneForm().
      $pane_form['removed_shipments']['#value'] = array_map(function ($shipment) {
        /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
        return $shipment->id();
      }, $removed_shipments);
    }
    return $pane_form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    /** @var \Drupal\profile\Entity\ProfileInterface $shipping_profile */
    $shipping_profile = $pane_form['shipping_profile']['#profile'];
    // In case if the user has logged in - update the shipping profile owner.
    $shipping_profile->setOwnerId($this->order->getCustomerId());
    $shipping_profile->save();
    $this->order->setData('shipping_profile', $shipping_profile);
    $form_state->set('shipping_profile', $shipping_profile);
  }

  /**
   * Gets the shipping profile.
   *
   * The shipping profile is assumed to be the same for all shipments.
   * Therefore, it is taken from the first found shipment, or created from
   * scratch if no shipments were found.
   *
   * @return \Drupal\profile\Entity\ProfileInterface
   *   The shipping profile.
   */
  protected function getShippingProfile() {
    $shipping_profile = NULL;
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    foreach ($this->order->shipments->referencedEntities() as $shipment) {
      $shipping_profile = $shipment->getShippingProfile();
      break;
    }
    if (!$shipping_profile) {
      $shipping_profile = $this->entityTypeManager->getStorage('profile')->create([
        'type' => 'customer',
        'uid' => $this->order->getCustomerId(),
      ]);
    }

    return $shipping_profile;
  }

}
