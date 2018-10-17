<?php

namespace Drupal\commerce_nmi\Form;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\commerce\EntityHelper;
use Drupal\commerce_checkout\CheckoutOrderManagerInterface;
use Drupal\commerce_nmi\Plugin\Commerce\PaymentGateway\NMI;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\commerce_payment\Exception\DeclineException;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\Form\PaymentAddForm as BasePaymentAddForm;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsAuthorizationsInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsStoredPaymentMethodsInterface;
use Drupal\commerce_price\Price;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides the payment add form for the "nmicom" payment gateway.
 *
 * @package Drupal\commerce_nmi\Form
 */
class PaymentAddForm extends BasePaymentAddForm implements ContainerInjectionInterface {

  use LoggerChannelTrait;

  /**
   * The checkout order manager.
   *
   * @var \Drupal\commerce_checkout\CheckoutOrderManagerInterface
   */
  protected $checkoutOrderManager;

  /**
   * The payment method storage.
   *
   * @var \Drupal\commerce_payment\PaymentMethodStorageInterface
   */
  protected $paymentMethodStorage;

  /**
   * Constructs a new PaymentAddForm instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Drupal\commerce_checkout\CheckoutOrderManagerInterface $checkout_order_manager
   *   The checkout order manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RouteMatchInterface $route_match, CheckoutOrderManagerInterface $checkout_order_manager) {
    parent::__construct($entity_type_manager, $route_match);
    $this->checkoutOrderManager = $checkout_order_manager;
    $this->paymentMethodStorage = $this->entityTypeManager->getStorage('commerce_payment_method');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_route_match'),
      $container->get('commerce_checkout.checkout_order_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_nmi_payment_add_form';
  }

  /**
   * Builds the form for selecting a payment method.
   *
   * @param array $form
   *   The parent form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the complete form.
   *
   * @return array
   *   The built form.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function buildPaymentGatewayForm(array $form, FormStateInterface $form_state) {
    if (!$this->order->getCustomerId()) {
      throw new AccessDeniedHttpException();
    }

    /** @var \Drupal\commerce_payment\PaymentGatewayStorageInterface $payment_gateway_storage */
    $payment_gateway_storage = $this->entityTypeManager->getStorage('commerce_payment_gateway');
    $payment_gateways = $payment_gateway_storage->loadMultipleForOrder($this->order);
    $payment_gateways = array_filter($payment_gateways, function ($payment_gateway) {
      /** @var \Drupal\commerce_payment\Entity\PaymentGateway $payment_gateway */
      return $payment_gateway->getPluginId() == 'nmicom';
    });

    if (count($payment_gateways) < 1) {
      throw new AccessDeniedHttpException();
    }

    if (!$this->order->getBillingProfile() || !$this->order->getTotalPrice()) {
      $form['message'] = [
        '#type' => 'markup',
        '#markup' => $this->t('To pay the order make sure it has a billing profile and products attached.'),
      ];
      return $form;
    }

    $user_input = $form_state->getUserInput();
    $first_payment_gateway = reset($payment_gateways);
    $selected_payment_gateway_id = $first_payment_gateway->id();
    if (isset($user_input['payment_gateway'])) {
      $selected_payment_gateway_id = $user_input['payment_gateway'];
    }
    $selected_payment_gateway = $payment_gateways[$selected_payment_gateway_id];
    $selected_payment_gateway_plugin = $selected_payment_gateway->getPlugin();
    $form['payment_gateway'] = [
      '#type' => 'radios',
      '#title' => $this->t('Payment gateway'),
      '#options' => EntityHelper::extractLabels($payment_gateways),
      '#default_value' => $selected_payment_gateway_id,
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [get_class($this), 'ajaxRefresh'],
        'wrapper' => $form['#wrapper_id'],
      ],
    ];

    if ($selected_payment_gateway_plugin instanceof SupportsStoredPaymentMethodsInterface) {
      $billing_countries = $this->order->getStore()->getBillingCountries();
      $payment_methods = $this->paymentMethodStorage
        ->loadReusable($this->order->getCustomer(), $selected_payment_gateway, $billing_countries);

      $payment_method_options = [];
      foreach ($payment_methods as $id => $payment_method) {
        $payment_method_options[$id] = $payment_method->label();
        if ($payment_method->isDefault()) {
          $selected_payment_method = $payment_method->id();
        }
      }

      // Add options to create new stored payment methods of supported types.
      $add_payment_methods_options = $this->buildAddNewPaymentMethodOptions($selected_payment_gateway);
      $payment_method_options += array_column($add_payment_methods_options, 'label', 'id');

      if (!isset($selected_payment_method)) {
        reset($payment_method_options);
        $selected_payment_method = key($payment_method_options);
      }

      $form['payment_method'] = [
        '#type' => 'radios',
        '#title' => $this->t('Payment method'),
        '#options' => $payment_method_options,
        '#default_value' => $selected_payment_method,
        '#required' => TRUE,
        '#after_build' => [
          [get_class($this), 'clearValue'],
        ],
      ];
      $form['amount'] = [
        '#type' => 'commerce_price',
        '#title' => $this->t('Amount'),
        '#default_value' => $this->order->getTotalPrice()->toArray(),
        '#required' => TRUE,
      ];
      $form['transaction_type'] = [
        '#type' => 'radios',
        '#title' => $this->t('Transaction type'),
        '#title_display' => 'invisible',
        '#options' => [
          'authorize' => $this->t('Authorize only'),
          'capture' => $this->t('Authorize and capture'),
        ],
        '#default_value' => 'capture',
        '#access' => $selected_payment_gateway_plugin instanceof SupportsAuthorizationsInterface,
      ];
    }

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Continue'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * Returns a list of option to create payment methods for the payment gateway.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentGatewayInterface $payment_gateway
   *   The payment gateway.
   *
   * @return array
   *   An array of options or empty array otherwise.
   */
  protected function buildAddNewPaymentMethodOptions(PaymentGatewayInterface $payment_gateway) {
    $options = [];
    $payment_gateway_plugin = $payment_gateway->getPlugin();
    $payment_method_type_counts = [];
    $payment_method_types = $payment_gateway_plugin->getPaymentMethodTypes();
    foreach ($payment_method_types as $payment_method_type_id => $payment_method_type) {
      if (!isset($payment_method_type_counts[$payment_method_type_id])) {
        $payment_method_type_counts[$payment_method_type_id] = 1;
      }
      else {
        $payment_method_type_counts[$payment_method_type_id]++;
      }
    }

    foreach ($payment_method_types as $payment_method_type_id => $payment_method_type) {
      $option_id = 'new--' . $payment_method_type_id . '--' . $payment_gateway->id();
      $option_label = $payment_method_type->getCreateLabel();
      // If there is more than one option for this payment method type,
      // append the payment gateway label to avoid duplicate option labels.
      if ($payment_method_type_counts[$payment_method_type_id] > 1) {
        $option_label = $this->t('@payment_method_label (@payment_gateway_label)', [
          '@payment_method_label' => $payment_method_type->getCreateLabel(),
          '@payment_gateway_label' => $payment_gateway_plugin->getDisplayLabel(),
        ]);
      }

      $options[$option_id] = [
        'id' => $option_id,
        'label' => $option_label,
        'payment_gateway' => $payment_gateway->id(),
        'payment_method_type' => $payment_method_type_id,
      ];
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaymentForm(array $form, FormStateInterface $form_state) {
    $capture = $form_state->getValue('transaction_type') == 'capture';
    $amount = $form_state->getValue('amount');
    $amount = new Price($amount['number'], $amount['currency_code']);
    $payment_gateway = $form_state->getValue('payment_gateway');
    $payment_method = $form_state->getValue('payment_method');
    $payment_method_value = explode('--', $payment_method);

    $add_payment_method = count($payment_method_value) == 3 && $payment_method_value[2] == $payment_gateway;
    $add_payment_method = $add_payment_method && $payment_method_value[0] == 'new';
    // Make sure the order has a checkout flow attached.
    $this->checkoutOrderManager->getCheckoutFlow($this->order);

    if ($add_payment_method) {
      $payment_method_type_id = $payment_method_value[1];
      /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method */
      $payment_method = $this->paymentMethodStorage->create([
        'type' => $payment_method_type_id,
        'payment_gateway' => $payment_gateway,
        'uid' => $this->order->getCustomerId(),
        'billing_profile' => $this->order->getBillingProfile(),
      ]);

      /** @var \Drupal\commerce_nmi\Plugin\Commerce\PaymentGateway\NMIInterface $payment_gateway_plugin */
      $payment_gateway_plugin = $payment_method->getPaymentGateway()->getPlugin();

      try {
        $payment_details = [
          'order_id' => $this->order->id(),
          'return_url' => NMI::buildReturnUrl($this->order, TRUE)->toString(),
          'capture' => $capture,
          'amount' => $amount,
        ];
        $payment_gateway_plugin->createPaymentMethod($payment_method, $payment_details);
        $form_state->setValue('payment_method', $payment_method->id());
      }
      catch (DeclineException $e) {
        $this->getLogger('commerce_payment')->warning($e->getMessage());
        throw new DeclineException($this->t('We encountered an error processing your payment method. Please verify your details and try again.'));
      }
      catch (PaymentGatewayException $e) {
        $this->getLogger('commerce_payment')->warning($e->getMessage());
        throw new PaymentGatewayException($this->t('We encountered an unexpected error processing your payment method. Please try again later.'));
      }
    }

    $form = parent::buildPaymentForm($form, $form_state);
    $form['payment']['#capture'] = $capture;
    $form['payment']['#amount'] = $amount;

    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $form['payment']['#default_value'];
    /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method */
    $payment_method = $payment->getPaymentMethod();
    $this->order->set('payment_gateway', $payment_method->getPaymentGateway());
    $this->order->set('payment_method', $payment_method);
    $this->order->save();
    return $form;
  }

}
