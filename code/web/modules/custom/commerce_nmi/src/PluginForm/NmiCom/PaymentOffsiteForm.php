<?php

namespace Drupal\commerce_nmi\PluginForm\NmiCom;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\commerce_nmi\Ajax\OffEventsCommand;
use Drupal\commerce_nmi\Plugin\Commerce\PaymentGateway\NMI;
use Drupal\commerce_payment\CreditCard;
use Drupal\commerce_payment\Exception\DeclineException;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm as BasePaymentOffsiteForm;
use Drupal\commerce_price\Price;
use Drupal\user\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the PaymentOffsiteForm for the NMI payment gateway.
 *
 * @package Drupal\commerce_nmi\PluginForm\NmiCom
 */
class PaymentOffsiteForm extends BasePaymentOffsiteForm implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $currentRouteMatch;

  /**
   * The PrivateTempStore.
   *
   * @var \Drupal\user\PrivateTempStoreFactory
   */
  protected $userPrivateTempstore;

  /**
   * Constructs a new PaymentOffsiteForm object.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $current_route_match
   *   The current route match.
   * @param \Drupal\user\PrivateTempStoreFactory $user_private_tempstore
   *   The PrivateTempStore.
   */
  public function __construct(RouteMatchInterface $current_route_match, PrivateTempStoreFactory $user_private_tempstore) {
    $this->currentRouteMatch = $current_route_match;
    $this->userPrivateTempstore = $user_private_tempstore;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_route_match'),
      $container->get('user.private_tempstore')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    /** @var \Drupal\commerce_payment\Entity\Payment $payment */
    $payment = $this->getEntity();
    /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method */
    $payment_method = $payment->getOrder()->get('payment_method')->entity;
    $payment_gateway = $payment->getPaymentGateway();
    /** @var \Drupal\commerce_nmi\Plugin\Commerce\PaymentGateway\NMIInterface $payment_gateway_plugin */
    $payment_gateway_plugin = $payment_gateway->getPlugin();
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $this->currentRouteMatch->getParameter('commerce_order');
    $tempstore = $this->userPrivateTempstore->get('commerce_nmi');
    $redirect_url = $tempstore->get($order->id() . '_nmi_form_url');

    // Avoid sending double requests on ajax. Generate a form url here only in
    // case of reusing a credit card.
    if ($payment_method->isReusable() && !$redirect_url) {
      $amount = !empty($form['#amount']) ? $form['#amount'] : NULL;
      $result = $payment_gateway_plugin->saleRequest($order, $payment_method, $form['#return_url'], $form['#capture'], $amount);
      $redirect_url = $result['form_url'];
      $tempstore->set($order->id() . '_nmi_form_url', $result['form_url']);
      $tempstore->set($order->id() . '_amount_to_pay', $result['amount_to_pay']);
    }

    if (!$redirect_url) {
      throw new PaymentGatewayException(sprintf("NMI redirect URL is missing. For some reason the user doesn't has attached a unique NMI form URL to which the payment credentials needs to be sent. Payment method id: %s. Order id: %s.", $payment_method->id(), $order->id()));
    }

    // Build a month select list that shows months with a leading zero.
    $months = [];
    for ($i = 1; $i < 13; $i++) {
      $month = str_pad($i, 2, '0', STR_PAD_LEFT);
      $months[$month] = $month;
    }
    // Build a year select list that uses a 4 digit key with a 2 digit value.
    $current_year_4 = date('Y');
    $current_year_2 = date('y');
    $years = [];
    for ($i = 0; $i < 10; $i++) {
      $years[$current_year_4 + $i] = $current_year_2 + $i;
    }

    $form['#page_title'] = $payment_method->getType()->getLabel();
    $form['#attached']['library'][] = 'commerce_nmi/drupal.commerce_nmi.payment_offsite_form';
    // Currently we use Zurb Foundation theme with hardcoded status messages
    // with no ability to add a class to it, so we can get status messages
    // through JS. Add custom element for error messages.
    $form['errors'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'nmi-validation-messages'],
    ];
    // Placeholder for the detected card type.
    // Set by validateConfigurationForm().
    $form['type'] = [
      '#type' => 'hidden',
      '#value' => '',
    ];
    $form['#attributes']['class'][] = 'credit-card-form';
    $form['number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Card number'),
      '#attributes' => [
        'autocomplete' => 'off',
        'class' => ['cc-number'],
      ],
      '#required' => TRUE,
      '#maxlength' => 19,
      '#size' => 20,
      '#process' => [[get_class($this), 'ajaxifyForm']],
    ];
    $form['expiration'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['credit-card-form__expiration'],
      ],
    ];
    $form['expiration']['month'] = [
      '#type' => 'select',
      '#title' => $this->t('Month'),
      '#options' => $months,
      '#default_value' => date('m'),
      '#attributes' => ['class' => ['month']],
      '#required' => TRUE,
    ];
    $form['expiration']['divider'] = [
      '#type' => 'item',
      '#title' => '',
      '#markup' => '<span class="credit-card-form__divider">/</span>',
    ];
    $form['expiration']['year'] = [
      '#type' => 'select',
      '#title' => $this->t('Year'),
      '#options' => $years,
      '#default_value' => $current_year_4,
      '#attributes' => ['class' => ['year']],
      '#required' => TRUE,
    ];
    $form['security_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('CVV'),
      '#attributes' => [
        'autocomplete' => 'off',
        'class' => ['security-code'],
      ],
      '#required' => TRUE,
      '#maxlength' => 4,
      '#size' => 4,
    ];
    $form['ccnumber'] = [
      '#type' => 'hidden',
      '#value' => '',
      '#name' => 'ccnumber',
    ];
    $form['ccexp'] = [
      '#type' => 'hidden',
      '#value' => '',
      '#name' => 'ccexp',
    ];
    $form['billing-cvv'] = [
      '#type' => 'hidden',
      '#value' => '',
      '#name' => 'billing-cvv',
    ];

    if ($payment_gateway_plugin->getMode() == 'test') {
      /*
       * https://secure.networkmerchants.com/gw/merchants/resources/integration/integration_portal.php#testing_information
       */
      $form['number']['#default_value'] = 4111111111111111;
      $form['ccnumber']['#value'] = 4111111111111111;
      $form['expiration']['month']['#default_value'] = 10;
      $form['expiration']['year']['#default_value'] = 2025;
      $form['ccexp']['#value'] = 1025;
      $form['security_code']['#default_value'] = 999;
      $form['billing-cvv']['#value'] = 999;
    }

    if ($payment_method->isReusable()) {
      $form['number']['#access'] = FALSE;
      $form['ccnumber']['#access'] = FALSE;
      $form['expiration']['#access'] = FALSE;
      $form['ccexp']['#access'] = FALSE;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);
    $values = $form_state->getValue($form['#parents']);
    /** @var \Drupal\commerce_payment\Entity\Payment $payment */
    $payment = $this->getEntity();
    /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method */
    $payment_method = $payment->getOrder()->get('payment_method')->entity;

    if ($payment_method->isReusable()) {
      $card_type = $payment_method->card_type->value;
      $card_type = CreditCard::getType($card_type);
    }
    else {
      $card_type = CreditCard::detectType($values['number']);
      if (!$card_type) {
        $form_state->setError($form['number'], $this->t('You have entered a credit card number of an unsupported card type.'));
        return;
      }
      if (!CreditCard::validateNumber($values['number'], $card_type)) {
        $form_state->setError($form['number'], $this->t('You have entered an invalid credit card number.'));
      }
      if (!CreditCard::validateExpirationDate($values['expiration']['month'], $values['expiration']['year'])) {
        $form_state->setError($form['expiration'], $this->t('You have entered an expired credit card.'));
      }

      // Persist the detected card type.
      $form_state->setValueForElement($form['type'], $card_type->getId());
    }

    if (!CreditCard::validateSecurityCode($values['security_code'], $card_type)) {
      $form_state->setError($form['security_code'], $this->t('You have entered an invalid CVV.'));
    }
  }

  /**
   * Add ajax callback for the submit button.
   *
   * @param array $element
   *   The form element whose value is being processed.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @return array
   *   The processed form element.
   */
  public static function ajaxifyForm(array $element, FormStateInterface $form_state, array &$complete_form) {
    // The form actions are hidden by default, but needed in this case.
    $complete_form['actions']['#access'] = TRUE;
    foreach (Element::children($complete_form['actions']) as $element_name) {
      $complete_form['actions'][$element_name]['#access'] = TRUE;
    }

    $complete_form['#attached']['library'][] = 'commerce_nmi/drupal.commerce_nmi.commands';

    // We don't really need any checkout submit handlers, since we just want to
    // validate the form and redirect to NMI with POST data.
    unset($complete_form['actions']['next']['#submit']);
    unset($complete_form['#submit']);

    if ($complete_form['#form_id'] == 'commerce_nmi_payment_add_form' && isset($complete_form['actions']['submit'])) {
      $submit_button = 'submit';
      if (isset($complete_form['actions'][$submit_button]['#submit'])) {
        unset($complete_form['actions'][$submit_button]['#submit']);
      }
    }
    else {
      $submit_button = 'next';
    }

    $complete_form['actions'][$submit_button]['#ajax'] = [
      'callback' => [__CLASS__, 'ajaxRefresh'],
    ];

    return $element;
  }

  /**
   * Ajax callback.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return mixed
   *   See Drupal\Component\Utility\NestedArray::getValue().
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public static function ajaxRefresh(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $errors_element = [
      '#type' => 'container',
      '#attributes' => ['id' => 'nmi-validation-messages'],
    ];

    if ($form_state->getErrors()) {
      $errors_element['messages'] = [
        '#prefix' => '<div id="nmi-validation-messages">',
        '#suffix' => '</div>',
        '#type' => 'status_messages',
      ];
      $response->addCommand(new ReplaceCommand('#nmi-validation-messages', $errors_element));
    }
    else {
      // Remove validation messages if any.
      $response->addCommand(new ReplaceCommand('#nmi-validation-messages', $errors_element));

      /** @var \Drupal\Core\Routing\RouteMatchInterface $route_match */
      $route_match = \Drupal::service('current_route_match');
      /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
      $order = $route_match->getParameter('commerce_order');
      /** @var \Drupal\user\PrivateTempStore $tempstore */
      /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method */
      $payment_method = $order->get('payment_method')->entity;
      $tempstore = \Drupal::service('user.private_tempstore')->get('commerce_nmi');
      $redirect_url = $tempstore->get($order->id() . '_nmi_form_url');

      /** @var \Drupal\commerce_checkout\Entity\CheckoutFlowInterface $checkout_flow */
      $checkout_flow = $order->get('checkout_flow')->entity;
      $checkout_flow_plugin = $checkout_flow->getPlugin();
      $checkout_order_manager = \Drupal::service('commerce_checkout.checkout_order_manager');
      $step_id = $checkout_order_manager->getCheckoutStepId($order);
      $prev_step = $checkout_flow_plugin->getPreviousStepId($step_id);
      $redirect_to = Url::fromRoute('commerce_checkout.form', [
        'commerce_order' => $order->id(),
        'step' => $prev_step,
      ], ['absolute' => TRUE])->toString();

      if ($redirect_url) {
        $amount_to_pay = $tempstore->get($order->id() . '_amount_to_pay');
        $checkout_form = $route_match->getRouteName() == 'commerce_checkout.form';

        if ($checkout_form && $amount_to_pay) {
          $amount_to_pay = new Price($amount_to_pay['number'], $amount_to_pay['currency_code']);

          // For some reason the amount to pay which was sent to NMI earlier is
          // not equal the current order total - send another sale request
          // to NMI.
          if ($order->getTotalPrice()->compareTo($amount_to_pay) !== 0) {
            /** @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface $payment_gateway */
            $payment_gateway = $order->get('payment_gateway')->entity;
            $payment_method = $order->get('payment_method')->entity;
            /** @var \Drupal\commerce_nmi\Plugin\Commerce\PaymentGateway\NMIInterface $payment_gateway_plugin */
            $payment_gateway_plugin = $payment_gateway->getPlugin();
            $capture = $payment_gateway_plugin->isCapturePayment($order);
            $return_url = NMI::buildReturnUrl($order)->toString();

            try {
              $result = $payment_gateway_plugin->saleRequest($order, $payment_method, $return_url, $capture);
              $tempstore->set($order->id() . '_nmi_form_url', $result['form_url']);
              $tempstore->set($order->id() . '_amount_to_pay', $result['amount_to_pay']);
              $redirect_url = $result['form_url'];
            }
            catch (DeclineException $e) {
              $message = t('We encountered an error processing your payment method. Please verify your details and try again.');
              \Drupal::service('messenger')->addError($message);
              $response->addCommand(new RedirectCommand($redirect_to));
              return $response;
            }
            catch (PaymentGatewayException $e) {
              \Drupal::logger('commerce_payment')->error($e->getMessage());
              $message = t('We encountered an unexpected error processing your payment method. Please try again later.');
              \Drupal::service('messenger')->addError($message);
              $response->addCommand(new RedirectCommand($redirect_to));
              return $response;
            }
          }
        }

        $nmi_add_payment_form = $form['#form_id'] == 'commerce_nmi_payment_add_form' && isset($form['actions']['submit']);
        $submit_button = $nmi_add_payment_form ? 'submit' : 'next';
        $selector = $form['actions'][$submit_button]['#attributes']['data-drupal-selector'];
        $response->addCommand(new OffEventsCommand($selector));

        $selector = $nmi_add_payment_form ? 'form.commerce-nmi-payment-add-form' : 'form.commerce-checkout-flow';
        $response->addCommand(new InvokeCommand($selector, 'attr', [
          'action',
          $redirect_url,
        ]));
        $response->addCommand(new InvokeCommand($selector, 'submit'));
      }
      else {
        \Drupal::logger('commerce_payment')->error(sprintf("The user tried to submit the payment credentials but NMI redirect URL is missing. For some reason the user doesn't has attached a unique NMI form URL to which the payment credentials needs to be sent. Payment method id: %s. Order id: %s.", $payment_method->id(), $order->id()));
        $message = t('We encountered an unexpected error processing your payment method. Please try again later.');
        \Drupal::service('messenger')->addError($message);
        $response->addCommand(new RedirectCommand($redirect_to));
      }
    }
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_payment\Entity\Payment $payment */
    $payment = $this->getEntity();
    /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method */
    $payment_method = $payment->getOrder()->get('payment_method')->entity;

    // Save credit card details only if we adding a new card.
    if (!$payment_method->isReusable()) {
      $values = $form_state->getValue($form['#parents']);
      $payment_method->card_type = $values['type'];
      // Only the last 4 numbers are safe to store.
      $payment_method->card_number = substr($values['number'], -4);
      $payment_method->card_exp_month = $values['expiration']['month'];
      $payment_method->card_exp_year = $values['expiration']['year'];
      $expires = CreditCard::calculateExpirationTimestamp($values['expiration']['month'], $values['expiration']['year']);
      $payment_method->setExpiresTime($expires);
      $payment_method->setReusable(FALSE);
      $payment_method->save();
    }

    parent::submitConfigurationForm($form, $form_state);
  }

}
