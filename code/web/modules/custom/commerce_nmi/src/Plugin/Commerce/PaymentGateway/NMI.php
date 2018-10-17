<?php

namespace Drupal\commerce_nmi\Plugin\Commerce\PaymentGateway;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Url;
use Drupal\commerce_nmi\Util\NmiRequestInterface;
use Drupal\commerce_nmi\Util\NmiRequestTrait;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderType;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Exception\DeclineException;
use Drupal\commerce_payment\Exception\InvalidResponseException;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\PaymentMethodTypeManager;
use Drupal\commerce_payment\PaymentTypeManager;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\commerce_price\Price;
use Drupal\field\FieldConfigInterface;
use Drupal\user\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides the NMI.com payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "nmicom",
 *   label = "NMI.com (Network Merchants, Inc.)",
 *   display_label = "NMI.com (Network Merchants, Inc.)",
 *   forms = {
 *     "add-payment" = "Drupal\commerce_nmi\PluginForm\NmiCom\PaymentAddForm",
 *     "add-payment-method" = "Drupal\commerce_nmi\PluginForm\NmiCom\PaymentMethodAddForm",
 *     "offsite-payment" = "Drupal\commerce_nmi\PluginForm\NmiCom\PaymentOffsiteForm",
 *   },
 *   payment_method_types = {"nmi_3sr_cc"},
 *   credit_card_types = {
 *     "amex", "dinersclub", "discover", "jcb", "mastercard", "visa"
 *   },
 * )
 */
class NMI extends OffsitePaymentGatewayBase implements NMIInterface {

  use LoggerChannelTrait;
  use NmiRequestTrait;

  /**
   * The PrivateTempStore.
   *
   * @var \Drupal\user\PrivateTempStoreFactory
   */
  protected $userPrivateTempstore;

  /**
   * The Entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $entityFieldManager;

  /**
   * The order refresh service.
   *
   * @var \Drupal\commerce_order\OrderRefreshInterface
   */
  protected $orderRefresh;

  /**
   * Constructs a new NMI object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\commerce_payment\PaymentTypeManager $payment_type_manager
   *   The payment type manager.
   * @param \Drupal\commerce_payment\PaymentMethodTypeManager $payment_method_type_manager
   *   The payment method type manager.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time.
   * @param \Drupal\user\PrivateTempStoreFactory $user_private_tempstore
   *   The PrivateTempStore.
   * @param \Drupal\Core\Entity\EntityFieldManager $entity_field_manager
   *   The Entity field manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, PaymentTypeManager $payment_type_manager, PaymentMethodTypeManager $payment_method_type_manager, TimeInterface $time, PrivateTempStoreFactory $user_private_tempstore, EntityFieldManager $entity_field_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $payment_type_manager, $payment_method_type_manager, $time);

    $this->userPrivateTempstore = $user_private_tempstore;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.commerce_payment_type'),
      $container->get('plugin.manager.commerce_payment_method_type'),
      $container->get('datetime.time'),
      $container->get('user.private_tempstore'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'api_key' => '',
      'phone_fields' => [],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API key'),
      '#default_value' => $this->configuration['api_key'],
      '#required' => TRUE,
    ];
    $form['phone_container'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#attributes' => ['class' => ['phone-container']],
    ];
    $form['phone_container']['description'] = [
      '#type' => 'item',
      '#markup' => $this->t('Please specify a phone number field of the customer, which can be sent to NMI. Profiles that are attached to a payment_method, a shipping or an order entities MUST has a required phone field.'),
    ];

    $customer_profile = FALSE;
    $base_phone_field = [
      '#type' => 'select',
      '#title' => $this->t('Phone field'),
      '#required' => TRUE,
    ];
    // 1) Fetch profile bundles that are attached to order types.
    /** @var \Drupal\commerce_order\Entity\OrderTypeInterface $order_type */
    foreach (OrderType::loadMultiple() as $order_type) {
      $bundle = $order_type->bundle();
      $definitions = $this->entityFieldManager->getFieldDefinitions('commerce_order', $bundle);

      if (isset($definitions['billing_profile'])) {
        /** @var \Drupal\Core\Field\BaseFieldDefinition $field_definition */
        $field_definition = $definitions['billing_profile'];
        $handler_settings = $field_definition->getSetting('handler_settings');
        $target_type = $field_definition->getSetting('target_type');
        $target_bundles = !empty($handler_settings['target_bundles']);

        if ($target_type && $target_bundles) {
          foreach ($handler_settings['target_bundles'] as $profile_bundle) {
            if (!empty($form['phone_container'][$profile_bundle]['phone'])) {
              continue;
            }

            $phone_fields = $this->getContentEntityPhoneFields($profile_bundle, $target_type);
            $default_value = $this->configuration['phone_fields'][$profile_bundle] ?: NULL;

            $form['phone_container'][$profile_bundle]['phone'] = $base_phone_field + [
              '#description' => $this->t('The @target_type_entity entity bundle "@target_type_entity_bundle" which is attached to the order type %order_type', [
                '@target_type_entity' => $target_type,
                '@target_type_entity_bundle' => $profile_bundle,
                '%order_type' => $order_type->label(),
              ]),
              '#options' => $phone_fields,
              '#default_value' => $default_value,
            ];
          }

          // Check if the order type uses "customer" profile type.
          if (!$customer_profile) {
            $customer_profile = in_array('customer', $handler_settings['target_bundles']);
          }
        }
      }
    }

    // 2) Payment method and shipping relies on the "customer" profile entity.
    // @see \Drupal\commerce_payment\PluginForm\PaymentMethodAddForm::
    // buildConfigurationForm() - $billing_profile = Profile::create([
    // 'type' => 'customer'...
    // If no order type uses "customer" profile type:
    if (!$customer_profile) {
      $phone_fields = $this->getContentEntityPhoneFields('customer', 'profile');
      $form['phone_container']['customer']['phone'] = $base_phone_field + [
        '#description' => $this->t('The profile entity bundle "customer".'),
        '#options' => $phone_fields,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['phone_fields'] = [];
      $this->configuration['api_key'] = $values['api_key'];

      foreach ($values['phone_container'] as $profile_bundle => $fields) {
        if (!empty($fields['phone'])) {
          $this->configuration['phone_fields'][$profile_bundle] = $fields['phone'];
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onCancel(OrderInterface $order, Request $request) {
    /* @see Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase::onCancel() */
    // NMI is not supposed to "cancel" a payment, since it accepts only
    // a return_url. Therefore the parent's message does not make sense.
  }

  /**
   * {@inheritdoc}
   */
  public function onReturn(OrderInterface $order, Request $request) {
    if (!$token_id = $request->get('token-id')) {
      throw new PaymentGatewayException('The NMI token is missing.');
    }

    $tempstore = $this->userPrivateTempstore->get('commerce_nmi');
    $tempstore->delete($order->id() . '_nmi_form_url');
    $tempstore->delete($order->id() . '_amount_to_pay');

    $request = $this->getNmiRequest();
    $request->setApiKey($this->configuration['api_key']);
    $response = $request->completeAction($token_id);

    if (empty($response->{'result'}) || (string) $response->{'result'} != 1) {
      if ($this->isCustomerReportableError($response)) {
        if ($error = $this->getNmiError($response)) {
          drupal_set_message($this->t($error), 'error');
        }
      }
      throw new PaymentGatewayException($this->describeResponse($request, $response));
    }
    else {
      $transaction_id = !empty($response->{'transaction-id'});
      $amount_authorized = !empty($response->{'amount-authorized'});
      $currency = !empty($response->{'currency'});
      $customer_vault_id = !empty($response->{'customer-vault-id'});

      if (!$transaction_id || !$amount_authorized || !$currency || !$customer_vault_id) {
        $error = $this->t('The NMI response for the "complete-action" request must contains transaction-id, amount-authorized and currency. Request: @request Response: @response', [
          '@request' => print_r($request->getRequest(), TRUE),
          '@response' => print_r($response, TRUE),
        ]);
        throw new InvalidResponseException($error);
      }
    }

    $transaction_id = (string) $response->{'transaction-id'};
    $amount_authorized = (string) $response->{'amount-authorized'};
    $currency = (string) $response->{'currency'};
    $customer_vault_id = (string) $response->{'customer-vault-id'};
    $response_text = !empty($response->{'result-text'}) ?
      substr((string) $response->{'result-text'}, 0, 255) :
      NULL;

    /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $order_payment_method */
    $order_payment_method = $order->get('payment_method')->entity;

    if (!$order_payment_method->isReusable()) {
      $card_number = !empty($order_payment_method->card_number) && !empty($order_payment_method->card_number->value);
      $card_exp_month = !empty($order_payment_method->card_exp_month) && !empty($order_payment_method->card_exp_month->value);
      $card_exp_year = !empty($order_payment_method->card_exp_year) && !empty($order_payment_method->card_exp_year->value);

      if ($card_number && $card_exp_month && $card_exp_year) {
        $order_payment_method->customer_vault_id = $customer_vault_id;
        $order_payment_method->setReusable(TRUE);
        $order_payment_method->save();
      }
    }

    $action_type = empty($response->{'action-type'}) ? [] : (array) $response->{'action-type'};

    if ($action_type) {
      $capture = in_array('sale', $action_type);
    }
    else {
      $capture = $this->isCapturePayment($order);
    }

    $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
    $payment = $payment_storage->create([
      'state' => $capture ? 'completed' : 'authorization',
      'amount' => new Price($amount_authorized, $currency),
      'payment_gateway' => $this->entityId,
      'payment_method' => $order_payment_method->id(),
      'order_id' => $order->id(),
      'test' => $this->getMode() == 'test',
      'remote_id' => $transaction_id,
      'remote_state' => $response_text,
      'authorized' => $this->time->getRequestTime(),
    ]);
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function refundPayment(PaymentInterface $payment, Price $amount = NULL) {
    $this->assertPaymentState($payment, ['completed', 'partially_refunded']);
    // If not specified, refund the entire amount.
    $amount = $amount ?: $payment->getAmount();
    $this->assertRefundAmount($payment, $amount);

    $request = $this->getNmiRequest();
    $request->setApiKey($this->configuration['api_key']);
    $response = $request->doRefund($payment->getRemoteId(), $amount->getNumber());

    if (empty($response->{'result'}) || (string) $response->{'result'} != 1) {
      $message = $this->describeResponse($request, $response);
      $this->getLogger('commerce_nmi')->log('warning', $message);
      throw new DeclineException($this->describeResponse($request, $response, FALSE));
    }

    $old_refunded_amount = $payment->getRefundedAmount();
    $new_refunded_amount = $old_refunded_amount->add($amount);
    if ($new_refunded_amount->lessThan($payment->getAmount())) {
      $payment->setState('partially_refunded');
    }
    else {
      $payment->setState('refunded');
    }

    $payment->setRefundedAmount($new_refunded_amount);
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function voidPayment(PaymentInterface $payment) {
    $this->assertPaymentState($payment, ['authorization']);
    $request = $this->getNmiRequest();
    $request->setApiKey($this->configuration['api_key']);
    $response = $request->doVoid($payment->getRemoteId());

    if (empty($response->{'result'}) || (string) $response->{'result'} != 1) {
      $message = $this->describeResponse($request, $response);
      $this->getLogger('commerce_nmi')->log('warning', $message);
      throw new DeclineException($this->describeResponse($request, $response, FALSE));
    }

    $payment->setState('authorization_voided');
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function capturePayment(PaymentInterface $payment, Price $amount = NULL) {
    $this->assertPaymentState($payment, ['authorization']);
    // If not specified, capture the entire amount.
    $amount = $amount ?: $payment->getAmount();

    $remote_id = $payment->getRemoteId();
    $number = $amount->getNumber();
    $request = $this->getNmiRequest();
    $request->setApiKey($this->configuration['api_key']);
    $response = $request->doCapture($remote_id, $number);

    if (empty($response->{'result'}) || (string) $response->{'result'} != 1) {
      $message = $this->describeResponse($request, $response);
      $this->getLogger('commerce_nmi')->log('warning', $message);
      throw new DeclineException($this->describeResponse($request, $response, FALSE));
    }

    $payment->setState('completed');
    $payment->setAmount($amount);
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function createPaymentMethod(PaymentMethodInterface $payment_method, array $payment_details) {
    if (empty($payment_details['order_id'])) {
      throw new \InvalidArgumentException('$payment_details must contain the "order_id" key.');
    }

    $result = $this->doCreatePaymentMethod($payment_method, $payment_details);
    $payment_method->setRemoteId($result['transaction_id']);

    $tempstore = $this->userPrivateTempstore->get('commerce_nmi');
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $this->entityTypeManager->getStorage('commerce_order')
      ->load($payment_details['order_id']);
    $tempstore->set($order->id() . '_nmi_form_url', $result['form_url']);
    $tempstore->set($order->id() . '_amount_to_pay', $result['amount_to_pay']);

    $payment_method_type = $payment_method->getType()->getPluginId();

    switch ($payment_method_type) {
      case 'nmi_3sr_cc':
        $payment_method->setReusable(FALSE);
        break;
    }

    $payment_method->save();
  }

  /**
   * Sends a sale or auth request to NMI.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method
   *   The payment method.
   * @param array $payment_details
   *   The gateway-specific payment details.
   *
   * @return array
   *   An associative array with transaction_id, form_url and amount_to_pay.
   *
   * @throws \Drupal\commerce_payment\Exception\PaymentGatewayException
   *   If $payment_details doesn't has order or it can't be loaded.
   * @throws \Drupal\commerce_payment\Exception\DeclineException
   *   If get fail response from NMI.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function doCreatePaymentMethod(PaymentMethodInterface $payment_method, array $payment_details) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $this->entityTypeManager->getStorage('commerce_order')
      ->load($payment_details['order_id']);

    if (!$order) {
      throw new PaymentGatewayException(sprintf('The payment gateway "%s" requires an order entity at this checkout step.', $this->getDisplayLabel()));
    }

    $payment_method_type = $payment_method->getType()->getPluginId();
    switch ($payment_method_type) {
      case 'nmi_3sr_cc':
        $capture = isset($payment_details['capture']) ? $payment_details['capture'] : $this->isCapturePayment($order);
        $return_url = !empty($payment_details['return_url'])
          ? $payment_details['return_url']
          : static::buildReturnUrl($order)->toString();
        $amount = !empty($payment_details['amount']) ? $payment_details['amount'] : NULL;
        $return = $this->saleRequest($order, $payment_method, $return_url, $capture, $amount);

        break;
    }

    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function saleRequest(OrderInterface $order, PaymentMethodInterface $payment_method, $return_url, $capture, Price $amount = NULL) {
    $request = $this->getNmiRequest();
    $request->setApiKey($this->configuration['api_key']);
    $request->setOrder($order);
    $reuse_payment_method = FALSE;

    if ($amount) {
      $request->setAmount($amount);
    }
    else {
      $request->setAmountFromOrder($order);
    }

    if ($payment_method->isReusable()) {
      if ($customer_vault_id = $payment_method->customer_vault_id->value) {
        $reuse_payment_method = TRUE;
      }
    }

    if ($reuse_payment_method) {
      $amount_to_pay = $request->getAmount();
      $response = $request->saleRequest($return_url, $capture, $customer_vault_id);
    }
    else {
      $request->addCustomer();

      if ($billing_profile = $payment_method->getBillingProfile()) {
        $bundle = $billing_profile->bundle();

        if (!isset($this->configuration['phone_fields'][$bundle])) {
          throw new PaymentGatewayException(sprintf('There is no phone fields defined in the NMI gateway configuration for the "%s" profile.', $bundle));
        }

        $phone_field_name = $this->configuration['phone_fields'][$bundle];
        $request->setCustomerInfo($billing_profile, $order, $phone_field_name);
      }

      if ($order->hasField('shipments') || !$order->get('shipments')->isEmpty()) {
        /** @var \Drupal\Core\Field\EntityReferenceFieldItemList $shipments */
        $shipments = $order->get('shipments');

        /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
        foreach ($shipments->referencedEntities() as $shipment) {
          if ($shipping_profile = $shipment->getShippingProfile()) {
            $bundle = $shipping_profile->bundle();

            if (!isset($this->configuration['phone_fields'][$bundle])) {
              throw new PaymentGatewayException(sprintf('There is no phone fields defined in the NMI gateway configuration for the "%s" profile.', $bundle));
            }

            $phone_field_name = $this->configuration['phone_fields'][$bundle];
            $request->setCustomerInfo($shipping_profile, $order, $phone_field_name, 'shipping');
            break;
          }
        }
      }

      $amount_to_pay = $request->getAmount();
      $response = $request->saleRequest($return_url, $capture);
    }

    $result_ok = !empty($response->{'result'}) && (string) $response->{'result'} == 1;
    $fom_url = !empty($response->{'form-url'});
    $transaction_id = !empty($response->{'transaction-id'});

    if (!$result_ok || !$fom_url || !$transaction_id) {
      if ($this->isCustomerReportableError($response)) {
        if ($error = $this->getNmiError($response)) {
          drupal_set_message($this->t($error), 'error');
        }
      }

      throw new DeclineException($this->describeResponse($request, $response));
    }

    return [
      'form_url' => (string) $response->{'form-url'},
      'transaction_id' => (string) $response->{'transaction-id'},
      'amount_to_pay' => $amount_to_pay,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isCapturePayment(OrderInterface $order) {
    /** @var \Drupal\commerce_checkout\Entity\CheckoutFlowInterface $checkout_flow */
    $checkout_flow = $order->get('checkout_flow')->entity;
    return (bool) $checkout_flow->getPlugin()->getConfiguration()['panes']['payment_process']['capture'];
  }

  /**
   * {@inheritdoc}
   */
  public function deletePaymentMethod(PaymentMethodInterface $payment_method) {
    $this->deleteCustomerVault($payment_method);
    $payment_method->delete();
  }

  /**
   * {@inheritdoc}
   */
  public function deleteCustomerVault(PaymentMethodInterface $payment_method) {
    $reusable = $payment_method->isReusable();

    if ($reusable && ($customer_vault_id = $payment_method->customer_vault_id->value)) {
      $request = $this->getNmiRequest();
      $request->setApiKey($this->configuration['api_key']);
      $response = $request->deleteCustomerVault($customer_vault_id);

      if (empty($response->{'result'}) || (string) $response->{'result'} != 1) {
        $this->getLogger('commerce_nmi')->warning($this->describeResponse($request, $response));
        throw new PaymentGatewayException('An error occurred while attempting to delete the payment method. Please try again later.');
      }

      $payment_method->customer_vault_id = NULL;
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Formats an API response as a string.
   *
   * @param \Drupal\commerce_nmi\Util\NmiRequestInterface $request
   *   The NMI request.
   * @param \SimpleXMLElement $response
   *   The API response object.
   * @param bool $detailed
   *   Whether add request/response details or not.
   *
   * @return string
   *   The message.
   */
  protected function describeResponse(NmiRequestInterface $request, \SimpleXMLElement $response, $detailed = TRUE) {
    $code = NULL;

    if (!empty($response->{'result-code'})) {
      $args['@code'] = (string) $response->{'result-code'};
      $code = 'with code @code';
    }
    if (!empty($response->{'result-text'})) {
      $args['@error'] = (string) $response->{'result-text'};
    }
    else {
      $args['@error'] = 'An error message is not provided by NMI.';
    }

    $message = sprintf('Received response %s from Nmi.com: @error.', $code);
    $error = $this->t($message, $args);

    if ($detailed) {
      $error .= ' ' . $this->t('Request: @request. Response: @response', [
        '@request' => print_r($request->getRequest(), TRUE),
        '@response' => print_r($response, TRUE),
      ]);
    }

    return $error;
  }

  /**
   * Returns whether should show the original error to the customer or not.
   *
   * @param \SimpleXMLElement $response
   *   The API response object.
   *
   * @return bool
   *   TRUE or FALSE;
   */
  protected function isCustomerReportableError(\SimpleXMLElement $response) {
    if (!empty($response->{'result-code'})) {
      $code = (string) $response->{'result-code'};
      return in_array($code, [202, 203, 223, 224, 225, 226, 251, 253, 461]);
    }
    return FALSE;
  }

  /**
   * Provides an error message based on the error code.
   *
   * @param \SimpleXMLElement $response
   *   The API response object.
   *
   * @return string|null
   *   An error message or NULL if there is an unknown code.
   */
  protected function getNmiError(\SimpleXMLElement $response) {
    if (!empty($response->{'result-code'})) {
      $errors = $this->getNmiErrors();
      $code = (string) $response->{'result-code'};
      return $errors[$code] ?: NULL;
    }
  }

  /**
   * Returns an associative array of NMI errors keyed by the error code.
   *
   * @return array
   *   An array of errors.
   */
  private function getNmiErrors() {
    return [
      100 => 'Transaction was approved.',
      200 => 'Transaction was declined by processor.',
      201 => 'Do not honor.',
      202 => 'Insufficient funds.',
      203 => 'Over limit.',
      204 => 'Transaction not allowed.',
      220 => 'Incorrect payment information.',
      221 => 'No such card issuer.',
      222 => 'No card number on file with issuer.',
      223 => 'Expired card.',
      224 => 'Invalid expiration date.',
      225 => 'Invalid card security code.',
      226 => 'Invalid PIN.',
      240 => 'Call issuer for further information.',
      250 => 'Pick up card.',
      251 => 'Lost card.',
      253 => 'Fraudulent card.',
      260 => 'Declined with further instructions available.',
      261 => 'Declined-Stop all recurring payments.',
      262 => 'Declined-Stop this recurring program.',
      263 => 'Declined-Update cardholder data available.',
      264 => 'Declined-Retry in a few days.',
      300 => 'Transaction was rejected by gateway.',
      400 => 'Transaction error returned by processor.',
      410 => 'Invalid merchant configuration.',
      411 => 'Merchant account is inactive.',
      420 => 'Communication error.',
      421 => 'Communication error with issuer.',
      430 => 'Duplicate transaction at processor.',
      440 => 'Processor format error.',
      441 => 'Invalid transaction information.',
      460 => 'Processor feature not available.',
      461 => 'Unsupported card type.',
    ];
  }

  /**
   * Returns required telephone fields from the entity type.
   *
   * @param string $bundle
   *   The entity bundle.
   * @param string $entity_type
   *   The entity type.
   *
   * @return array
   *   An associative array if field labels keyed by the field name. Empty array
   *   if no phone fields available for the entity type.
   */
  protected function getContentEntityPhoneFields($bundle, $entity_type = 'profile') {
    $field_definitions = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle);

    foreach ($field_definitions as $field_name => $field_definition) {
      if ($field_definition instanceof FieldConfigInterface) {
        /** @var \Drupal\field\FieldConfigInterface $field_definition */
        if ($field_definition->getType() == 'telephone' && $field_definition->isRequired()) {
          $phone_fields[$field_name] = $field_definition->getLabel();
        }
      }
    }

    return $phone_fields ?: [];
  }

  /**
   * {@inheritdoc}
   */
  public static function buildReturnUrl(OrderInterface $order, $nmi_custom_route = FALSE) {
    $route = $nmi_custom_route ? 'commerce_nmi.payment.return' : 'commerce_payment.checkout.return';
    return Url::fromRoute($route, [
      'commerce_order' => $order->id(),
      'step' => 'payment',
    ], ['absolute' => TRUE]);
  }

  /**
   * {@inheritdoc}
   */
  public static function buildCancelUrl(OrderInterface $order) {
    return Url::fromRoute('commerce_payment.checkout.cancel', [
      'commerce_order' => $order->id(),
      'step' => 'payment',
    ], ['absolute' => TRUE]);
  }

}
