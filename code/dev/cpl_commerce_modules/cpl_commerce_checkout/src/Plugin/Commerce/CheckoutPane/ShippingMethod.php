<?php

namespace Drupal\cpl_commerce_checkout\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\commerce_shipping\OrderShipmentSummaryInterface;
use Drupal\commerce_shipping\PackerManagerInterface;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Element;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the shipping information pane.
 *
 * Collects the shipping profile, then the information for each shipment.
 * Assumes that all shipments share the same shipping profile.
 *
 * @CommerceCheckoutPane(
 *   id = "cpl_commerce_checkout_shipping_method",
 *   label = @Translation("CPL - Shipping information - Method selection"),
 *   display_label = @Translation("Shipping method"),
 *   wrapper_element = "fieldset",
 * )
 */
class ShippingMethod extends CheckoutPaneBase implements ContainerFactoryPluginInterface {

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
    $shipping_profile = $this->order->getData('shipping_profile');
    $shipping_profile = $shipping_profile ?: $this->getShippingProfile();

    $pane_form['#attributes']['class'][] = 'cpl-commerce-shipping-method';
    $pane_form['removed_shipments'] = [
      '#type' => 'value',
      '#value' => [],
    ];
    $pane_form['shipments'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'shipments-container',
        ],
      ],
    ];

    $shipments = $this->order->shipments->referencedEntities();

    // Initialize shipments if they aren't set yet.
    if (empty($shipments)) {
      list($shipments, $removed_shipments) = $this->packerManager->packToShipments($this->order, $shipping_profile, $shipments);

      // Store the IDs of removed shipments for submitPaneForm().
      $pane_form['removed_shipments']['#value'] = array_map(function ($shipment) {
        /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
        return $shipment->id();
      }, $removed_shipments);
    }

    $single_shipment = count($shipments) === 1;
    foreach ($shipments as $index => $shipment) {
      /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
      $pane_form['shipments'][$index] = [
        '#parents' => array_merge($pane_form['#parents'], [
          'shipments',
          $index,
        ]),
        '#array_parents' => array_merge($pane_form['#parents'], [
          'shipments',
          $index,
        ]),
        '#type' => $single_shipment ? 'container' : 'fieldset',
        '#title' => $shipment->getTitle(),
      ];
      $form_display = EntityFormDisplay::collectRenderDisplay($shipment, 'default');
      $form_display->removeComponent('shipping_profile');
      $form_display->removeComponent('title');
      $form_display->buildForm($shipment, $pane_form['shipments'][$index], $form_state);
      $pane_form['shipments'][$index]['#shipment'] = $shipment;
    }

    return $pane_form;
  }

  /**
   * {@inheritdoc}
   */
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    foreach (Element::children($pane_form['shipments']) as $index) {
      $shipment = clone $pane_form['shipments'][$index]['#shipment'];
      $form_display = EntityFormDisplay::collectRenderDisplay($shipment, 'default');
      $form_display->removeComponent('shipping_profile');
      $form_display->removeComponent('title');
      $form_display->extractFormValues($shipment, $pane_form['shipments'][$index], $form_state);
      $form_display->validateFormValues($shipment, $pane_form['shipments'][$index], $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    // Save the modified shipments.
    $shipments = [];
    foreach (Element::children($pane_form['shipments']) as $index) {
      /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
      $shipment = clone $pane_form['shipments'][$index]['#shipment'];
      $form_display = EntityFormDisplay::collectRenderDisplay($shipment, 'default');
      $form_display->removeComponent('shipping_profile');
      $form_display->removeComponent('title');
      $form_display->extractFormValues($shipment, $pane_form['shipments'][$index], $form_state);
      $shipment->save();
      $shipments[] = $shipment;
    }
    $this->order->shipments = $shipments;

    // Delete shipments that are no longer in use.
    $removed_shipment_ids = $pane_form['removed_shipments']['#value'];
    if (!empty($removed_shipment_ids)) {
      $shipment_storage = $this->entityTypeManager->getStorage('commerce_shipment');
      $removed_shipments = $shipment_storage->loadMultiple($removed_shipment_ids);
      $shipment_storage->delete($removed_shipments);
    }

    $this->order->setData('shipping_profile', NULL);
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
