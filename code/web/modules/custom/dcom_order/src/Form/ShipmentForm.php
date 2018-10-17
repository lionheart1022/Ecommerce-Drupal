<?php

namespace Drupal\dcom_order\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\commerce_shipping\PackerManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides the form for managing the primary shipment for the order.
 *
 * @package Drupal\dcom_order\Form
 */
class ShipmentForm extends FormBase implements ContainerInjectionInterface {

  const SHIPPING_PROFILE_STEP = 'shipping_profile';
  const SHIPMENT_STEP = 'shipment';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current order.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $order;

  /**
   * The packer manager.
   *
   * @var \Drupal\commerce_shipping\PackerManagerInterface
   */
  protected $packerManager;

  /**
   * Constructs a new ShipmentForm instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Drupal\commerce_shipping\PackerManagerInterface $packer_manager
   *   The packer manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RouteMatchInterface $route_match, PackerManagerInterface $packer_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->order = $route_match->getParameter('commerce_order');
    $this->packerManager = $packer_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_route_match'),
      $container->get('commerce_shipping.packer_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dcom_order_shipment_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Set #parents to 'top-level' by default.
    $form += ['#parents' => []];
    // Prepare the form for ajax.
    $form['#wrapper_id'] = Html::getUniqueId('dcom-order-shipment-form-wrapper');
    $form['#prefix'] = '<div id="' . $form['#wrapper_id'] . '">';
    $form['#suffix'] = '</div>';
    $form['#tree'] = TRUE;

    $step = $form_state->get('step');
    $step = $step ?: self::SHIPPING_PROFILE_STEP;
    $form_state->set('step', $step);

    if (!$this->order->getCustomerId()) {
      throw new AccessDeniedHttpException();
    }

    if ($step == self::SHIPPING_PROFILE_STEP) {
      $form = $this->buildShippingProfileForm($form, $form_state);
    }
    elseif ($step == self::SHIPMENT_STEP) {
      $form = $this->buildShippingMethodForm($form, $form_state);
    }

    $form['actions']['#type'] = 'actions';

    return $form;
  }

  /**
   * Builds the form for selecting a shipping profile.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the complete form.
   *
   * @return array
   *   The built form.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function buildShippingProfileForm(array $form, FormStateInterface $form_state) {
    $store = $this->order->getStore();
    $shipping_profile = $form_state->get('shipping_profile');

    if (!$shipping_profile) {
      $shipping_profile = $this->getShippingProfile();
      $form_state->set('shipping_profile', $shipping_profile);
    }
    elseif (!$shipping_profile->isNew()) {
      // Update the stored entity with the latest version.
      // See the explanation why do we need it here:
      // dcom_checkout\src\Plugin\Commerce\CheckoutPane\ShippingProfile.php.
      $shipping_profile = $this->entityTypeManager
        ->getStorage('profile')
        ->load($shipping_profile->id());
    }

    $available_countries = [];
    foreach ($store->get('shipping_countries') as $country_item) {
      $available_countries[] = $country_item->value;
    }

    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    foreach ($this->order->shipments->referencedEntities() as $shipment) {
      $form_state->set('shipping_method', $shipment->getShippingMethod());
      $form_state->set('shipping_service', $shipment->getShippingService());
      break;
    }

    $form['shipping_profile'] = [
      '#type' => 'commerce_profile_select',
      '#default_value' => $shipping_profile,
      '#default_country' => $store->getAddress()->getCountryCode(),
      '#available_countries' => $available_countries,
    ];
    $form['removed_shipments'] = [
      '#type' => 'value',
      '#value' => [],
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Continue'),
      '#button_type' => 'primary',
    ];

    $shipments = $this->order->shipments->referencedEntities();
    if (empty($shipments)) {
      list(, $removed_shipments) = $this->packerManager->packToShipments($this->order, $shipping_profile, $shipments);

      // Store the IDs of removed shipments for submitPaneForm().
      $form['removed_shipments']['#value'] = array_map(function ($shipment) {
        /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
        return $shipment->id();
      }, $removed_shipments);
    }

    return $form;
  }

  /**
   * Builds the form for selecting a shipping method.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the complete form.
   *
   * @return array
   *   The built form.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function buildShippingMethodForm(array $form, FormStateInterface $form_state) {
    $shipping_profile = $form_state->get('shipping_profile');
    $shipping_profile = $shipping_profile ?: $this->getShippingProfile($form_state);

    $form['removed_shipments'] = [
      '#type' => 'value',
      '#value' => [],
    ];
    $form['shipments'] = [
      '#type' => 'container',
    ];
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface[] $shipments */
    $shipments = $this->order->shipments->referencedEntities();
    /** @var \Drupal\commerce_shipping\Entity\ShippingMethodInterface|null $shipping_method */
    $shipping_method = $form_state->get('shipping_method');
    /** @var \Drupal\commerce_shipping\ShippingService $shipping_service */
    $shipping_service = $form_state->get('shipping_service');

    // Initialize shipments if they aren't set yet.
    if (empty($shipments)) {
      list($shipments, $removed_shipments) = $this->packerManager->packToShipments($this->order, $shipping_profile, $shipments);

      // Store the IDs of removed shipments for submitPaneForm().
      $form['removed_shipments']['#value'] = array_map(function ($shipment) {
        /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
        return $shipment->id();
      }, $removed_shipments);
    }

    $single_shipment = count($shipments) === 1;
    foreach ($shipments as $index => $shipment) {
      if ($shipping_method && $shipping_service) {
        $shipment->setShippingMethod($shipping_method);
        $shipment->setShippingService($shipping_service);
      }

      $parents = array_merge($form['#parents'], ['shipments', $index]);
      $form['shipments'][$index] = [
        '#parents' => $parents,
        '#array_parents' => $parents,
        '#type' => $single_shipment ? 'container' : 'fieldset',
        '#title' => $shipment->getTitle(),
      ];
      $form_display = EntityFormDisplay::collectRenderDisplay($shipment, 'default');
      $form_display->removeComponent('shipping_profile');
      $form_display->removeComponent('title');
      $form_display->buildForm($shipment, $form['shipments'][$index], $form_state);
      $form['shipments'][$index]['#shipment'] = $shipment;
    }

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save shipment'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->get('step') == self::SHIPMENT_STEP) {
      foreach (Element::children($form['shipments']) as $index) {
        $shipment = clone $form['shipments'][$index]['#shipment'];
        $form_display = EntityFormDisplay::collectRenderDisplay($shipment, 'default');
        $form_display->removeComponent('shipping_profile');
        $form_display->removeComponent('title');
        $form_display->extractFormValues($shipment, $form['shipments'][$index], $form_state);
        $form_display->validateFormValues($shipment, $form['shipments'][$index], $form_state);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $step = $form_state->get('step');
    if ($step == self::SHIPPING_PROFILE_STEP) {
      // Save the modified shipments.
      $shipping_profile = $form['shipping_profile']['#profile'];

      $form_state->set('shipping_profile', $shipping_profile);
      $form_state->set('step', self::SHIPMENT_STEP);
      $form_state->setRebuild(TRUE);
    }
    elseif ($step == self::SHIPMENT_STEP) {
      // Save the modified shipments.
      $shipments = [];
      foreach (Element::children($form['shipments']) as $index) {
        /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
        $shipment = clone $form['shipments'][$index]['#shipment'];
        $form_display = EntityFormDisplay::collectRenderDisplay($shipment, 'default');
        $form_display->removeComponent('shipping_profile');
        $form_display->removeComponent('title');
        $form_display->extractFormValues($shipment, $form['shipments'][$index], $form_state);
        $shipment->save();
        $shipments[] = $shipment;
      }
      $this->order->shipments = $shipments;
      $this->order->save();

      // Delete shipments that are no longer in use.
      $removed_shipment_ids = $form['removed_shipments']['#value'];
      if (!empty($removed_shipment_ids)) {
        $shipment_storage = $this->entityTypeManager->getStorage('commerce_shipment');
        $removed_shipments = $shipment_storage->loadMultiple($removed_shipment_ids);
        $shipment_storage->delete($removed_shipments);
      }

      $this->messenger()->addMessage($this->t('Shipment saved.'));
      $form_state->setRedirectUrl($this->order->toUrl());
    }
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
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
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
