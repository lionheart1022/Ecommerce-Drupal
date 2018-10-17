<?php

namespace Drupal\commerce_affiliates\Plugin\Commerce\Affiliates;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_price\RounderInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class HasOffersAffiliate.
 *
 * @CommerceAffiliate(
 *   id = "hasoffers_affiliate",
 *   label = @Translation("HasOffers"),
 *   trackingType = "html",
 * )
 */
class HasOffersAffiliate extends AffiliateBase implements ContainerFactoryPluginInterface {

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
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    RounderInterface $rounder,
    ClientInterface $http_client
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->rounder = $rounder;
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
      $container->get('commerce_price.rounder'),
      $container->get('http_client')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'network_id' => '',
      'offer_id' => '',
      'affiliate_id' => '',
      'type' => 'https_iframe',
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
    $form['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Pixel tracking type'),
      '#required' => TRUE,
      '#options' => [
        'https_iframe' => $this->t('HTTPS iFrame pixel'),
        'https_img' => $this->t('HTTPS Image pixel'),
        'http_iframe' => $this->t('HTTP iFrame pixel'),
        'http_img' => $this->t('HTTP Image pixel'),
      ],
      '#default_value' => $this->configuration['type'],
    ];
    $form['network_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Network ID'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['network_id'],
    ];
    $form['offer_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Offer ID'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['offer_id'],
    ];
    $form['affiliate_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Affiliate ID'),
      '#default_value' => $this->configuration['affiliate_id'],
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
          [':input[name="configuration[hasoffers_affiliate][event_settings][order_canceled]"]' => ['checked' => TRUE]],
        ],
        'required' => [
          [':input[name="configuration[hasoffers_affiliate][event_settings][order_canceled]"]' => ['checked' => TRUE]],
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
    // @see https://help.tune.com/hasoffers/pixel-tracking
    switch ($this->configuration['type']) {
      case 'https_iframe':
        $prefix = 'https';
        $html_tag = 'iframe';
        break;

      case 'https_img':
        $prefix = 'https';
        $html_tag = 'img';
        break;

      case 'http_iframe':
        $prefix = 'http';
        $html_tag = 'iframe';
        break;

      case 'http_img':
        $prefix = 'http';
        $html_tag = 'img';
        break;

      default:
        $prefix = 'https';
        $html_tag = 'iframe';
        break;
    }

    $uri = "{$prefix}://{$this->configuration['network_id']}.go2cloud.org/aff_l";
    $query = [
      'offer_id' => $this->configuration['offer_id'],
      'amount' => $this->rounder->round($order->getTotalPrice())->getNumber(),
      'adv_sub' => $order->getOrderNumber(),
    ];

    if (!empty($this->configuration['affiliate_id'])) {
      $query['aff_id'] = $this->configuration['affiliate_id'];
    }

    $attributes = [
      'src' => Url::fromUri($uri, ['query' => $query])->toUriString(),
      'class' => [Html::cleanCssIdentifier($this->getPluginId())],
    ];
    $this->setAdditionalAttributes($attributes, $html_tag);
    return [
      '#type' => 'html_tag',
      '#tag' => $html_tag,
      '#attributes' => $attributes,
    ];
  }

  /**
   * Add attributes that depend on the selected html tag.
   *
   * @param array $attributes
   *   HTML element attributes.
   * @param string $html_tag
   *   Pixel html tag.
   */
  protected function setAdditionalAttributes(array &$attributes, $html_tag) {
    switch ($html_tag) {
      case 'img':
        $attributes['style'][] = 'width:0;';
        $attributes['style'][] = 'height:0;';
        break;

      case 'iframe':
        $attributes['height'] = '1';
        $attributes['width'] = '1';
        $attributes['frameborder'] = '0';
        $attributes['scrolling'] = 'no';
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function cancelTransaction(OrderInterface $order, $event_type) {
    if ($this->assertCancelingEvent($event_type)) {
      if ($transaction_id = $this->findTransactionId($order)) {
        $uri = $this->prepareStatusUpdateRequestUri($transaction_id);
        $response = $this->httpClient->get($uri);
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
   * Find HasOffers transaction ID by Order ID.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   Commerce order entity.
   *
   * @return null|int
   *   HasOffers transaction ID.
   */
  protected function findTransactionId(OrderInterface $order) {
    $uri = $this->prepareFindRequestUri($order);
    $response = $this->httpClient->get($uri);
    if ($response->getStatusCode() == 200) {
      $data = Json::decode($response->getBody());
      if (!empty($data['response']['data'])) {
        $transaction_data = reset($data['response']['data']);
        return $transaction_data['Conversion']['id'];
      }
    }
    else {
      // TODO - logging request fail?.
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
      'NetworkToken' => $this->configuration['api_key'],
      'Target' => 'Conversion',
      'Method' => 'findAll',
      'fields' => [
        'id',
        'status',
        'advertiser_info',
      ],
      'filters' => [
        'advertiser_info' => $order->getOrderNumber(),
        'offer_id' => $this->configuration['offer_id'],
      ],
    ];
    return Url::fromUri($this->getApiUri(), ['query' => $query])->toUriString();
  }

  /**
   * Prepare URI for request.
   *
   * @param int $transaction_id
   *   HasOffers affiliate transaction ID.
   *
   * @return string
   *   Request URI string.
   */
  protected function prepareStatusUpdateRequestUri($transaction_id) {
    $query = [
      'NetworkToken' => $this->configuration['api_key'],
      'Target' => 'Conversion',
      'Method' => 'updateStatus',
      'id' => $transaction_id,
      'status' => 'rejected',
    ];
    return Url::fromUri($this->getApiUri(), ['query' => $query])->toUriString();
  }

  /**
   * API uri.
   *
   * @return string
   *   HasOffers API address.
   */
  protected function getApiUri() {
    return "https://{$this->configuration['network_id']}.api.hasoffers.com/Apiv3/json";
  }

}
