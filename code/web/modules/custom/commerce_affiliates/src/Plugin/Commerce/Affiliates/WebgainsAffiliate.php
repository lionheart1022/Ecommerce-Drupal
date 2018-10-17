<?php

namespace Drupal\commerce_affiliates\Plugin\Commerce\Affiliates;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\odoo_api\OdooApi\Exception\ClientException;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class WebgainsAffiliate.
 *
 * @CommerceAffiliate(
 *   id = "webgains_affiliate",
 *   label = @Translation("Webgains"),
 *   trackingType = "html",
 * )
 */
class WebgainsAffiliate extends AffiliateBase implements ContainerFactoryPluginInterface {

  /**
   * The rounder.
   *
   * @var \Drupal\commerce_price\RounderInterface
   */
  protected $rounder;

  /**
   * The Guzzle HTTP Client service.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ClientInterface $http_client) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->httpClient = $http_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('http_client')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'program_id' => '',
      'event_id' => '',
      'vouchercode' => 'none',
      'customerid' => '',
      'api_key' => '',
      'event_settings' => [
        'checkout_completion' => 'checkout_completion',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['program_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Program ID'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['program_id'],
    ];
    $form['event_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Event ID'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['event_id'],
    ];
    $form['vouchercode'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Voucher Code'),
      '#default_value' => $this->configuration['vouchercode'],
    ];
    $form['customerid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Customer ID'),
      '#default_value' => $this->configuration['customerid'],
    ];
    $form['event_settings'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Settings'),
      '#options' => [
        'checkout_completion' => $this->t('Tracking on checkout completion'),
        'order_canceled' => $this->t('Cancel track for canceled order'),
      ],
      '#required' => TRUE,
      '#default_value' => $this->configuration['event_settings'],
    ];
    $form['api_key'] = [
      '#type' => 'textarea',
      '#rows' => 3,
      '#title' => $this->t('API Key'),
      '#default_value' => $this->configuration['api_key'],
      '#states' => [
        'visible' => [
          [':input[name="configuration[webgains_affiliate][event_settings][order_canceled]"]' => ['checked' => TRUE]],
        ],
        'required' => [
          [':input[name="configuration[webgains_affiliate][event_settings][order_canceled]"]' => ['checked' => TRUE]],
        ],
      ],
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
      'wgver' => '1.2.1',
      'wgprogramid' => $this->configuration['program_id'],
      'wgrs' => 1,
      'wgvalue' => $order->getTotalPrice()->getNumber(),
      'wgeventid' => $this->configuration['event_id'],
      'wgorderreference' => $order->getOrderNumber(),
      'wgitems' => '',
      'wgvouchercode' => $this->configuration['vouchercode'],
      'wgcustomerid' => $this->configuration['customerid'],
      'wgCurrency' => $order->getTotalPrice()->getCurrencyCode(),
    ];
    return [
      '#type' => 'html_tag',
      '#tag' => 'img',
      '#attributes' => [
        'src' => Url::fromUri('https://track.webgains.com/transaction.html', ['query' => $query])
          ->toUriString(),
        'class' => [Html::cleanCssIdentifier($this->getPluginId())],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function cancelTransaction(OrderInterface $order, $event_type) {
    if ($this->assertCancelingEvent($event_type)) {
      if ($transaction = $this->getTransaction($order)) {
        $uri = $this->prepareStatusUpdateRequestUri($transaction['id']);
        $request_data = $transaction;
        $request_data['status'] = 'cancelled';
        $response = $this->httpClient->put($uri, ['json' => $request_data]);
        if ($response->getStatusCode() != 200) {
          // TODO - logging request fail?.
        }
      }
    }
  }

  /**
   * Check if event handling is allowed for this plugin.
   *
   * @param string $event_type
   *   Event type. Ex: "order_canceled".
   *
   * @return bool
   *   TRUE - if canceling allowed.
   */
  protected function assertCancelingEvent($event_type) {
    return !empty($this->configuration['event_settings'][$event_type]);
  }

  /**
   * Get Webgains transaction by Order ID.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   Commerce order entity.
   *
   * @return null|array
   *   Webgains transaction.
   */
  protected function getTransaction(OrderInterface $order) {
    $uri = $this->prepareFindRequestUri($order);
    try {
      $response = $this->httpClient->get($uri);
      if ($response->getStatusCode() == 200) {
        $data = Json::decode($response->getBody());
        if (!empty($data)) {
          return reset($data);
        }
      }
      else {
        // TODO - logging request fail?
      }
    }
    catch (ClientException $exception) {
      // TODO - logging request fail?
    }
    return NULL;
  }

  /**
   * Prepare URI for request.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   Commerce order entity.
   *
   * @return string
   *   Request URI string.
   */
  protected function prepareFindRequestUri(OrderInterface $order) {
    $query = [
      'key' => $this->configuration['api_key'],
      'orderReferences' => $order->getOrderNumber(),
      'programId' => $this->configuration['program_id'],
    ];
    return Url::fromUri($this->getApiUri(), ['query' => $query])->toUriString();
  }

  /**
   * Prepare URI for request.
   *
   * @param int $transaction_id
   *   Webgains affiliate transaction ID.
   *
   * @return string
   *   Request URI string.
   */
  protected function prepareStatusUpdateRequestUri($transaction_id) {
    $query = [
      'key' => $this->configuration['api_key'],
      'changeReason' => 'order was canceled',
    ];
    return Url::fromUri("{$this->getApiUri()}/{$transaction_id}", ['query' => $query])->toUriString();
  }

  /**
   * API uri.
   *
   * @return string
   *   HasOffers API address.
   */
  protected function getApiUri() {
    return "http://api.webgains.com/2.0/transaction";
  }

}
