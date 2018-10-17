<?php

namespace Drupal\commerce_nmi\Util;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_price\Price;
use Drupal\profile\Entity\ProfileInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * The service for making requests to NMI.
 *
 * @package Drupal\commerce_nmi\Util
 */
class NmiRequest implements NmiRequestInterface {

  /**
   * URL to the NMI payment gateway that all requests will be made against.
   *
   * @var string
   */
  const GATEWAY_URL = 'https://secure.networkmerchants.com/api/v2/three-step';

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * The XML request to NMI.
   *
   * @var \DOMDocument
   */
  private $xmlRequest;

  /**
   * An array of the request data for a request to NMI.
   *
   * @var array
   */
  private $request = [];

  /**
   * Creates new NmiRequest service.
   *
   * @param \GuzzleHttp\ClientInterface $client
   *   Guzzle http client.
   */
  public function __construct(ClientInterface $client) {
    $this->httpClient = $client;
    $this->xmlRequest = new \DOMDocument('1.0', 'UTF-8');
    $this->xmlRequest->formatOutput = TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getRequest() {
    return $this->request;
  }

  /**
   * {@inheritdoc}
   */
  public function setApiKey($api_key) {
    $this->request['api-key'] = $api_key;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setAmount(Price $amount) {
    $this->request['amount'] = $amount->getNumber();
    $this->request['currency'] = $amount->getCurrencyCode();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setAmountFromOrder(OrderInterface $order) {
    $this->request['amount'] = $order->getTotalPrice()->getNumber();
    $this->request['currency'] = $order->getTotalPrice()->getCurrencyCode();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getAmount() {
    return [
      'number' => $this->request['amount'],
      'currency_code' => $this->request['currency'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function setOrder(OrderInterface $order) {
    $order_created = new \DateTime('@' . $order->getCreatedTime());
    $description = [];

    foreach ($order->getItems() as $order_item) {
      $description[] = round($order_item->getQuantity(), 2) . 'x ' . $order_item->label();
    }

    $this->request['order-id'] = $order->getOrderNumber() ?: $order->id();
    $this->request['order-description'] = implode(', ', $description);
    $this->request['order-date'] = $order_created->format('ymd');
    $this->request['ip-address'] = $order->getIpAddress();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setCustomerInfo(ProfileInterface $profile, OrderInterface $order, $phone_field_name, $type = 'billing') {
    $type = $type == 'billing' ? 'billing' : 'shipping';

    if (!$profile->hasField($phone_field_name) || $profile->get($phone_field_name)->isEmpty()) {
      throw new PaymentGatewayException(sprintf("The profile '%s' (ID %s) doesn't has a phone field or it is empty.", $profile->bundle(), $profile->id()));
    }

    if ($profile->hasField('address') && !$profile->get('address')->isEmpty()) {
      /** @var \Drupal\address\Plugin\Field\FieldType\AddressItem $billing_address */
      $billing_address = $profile->get('address')->first();

      $this->request[$type]['address1'] = $billing_address->getAddressLine1();
      $this->request[$type]['address2'] = $billing_address->getAddressLine2();
      $this->request[$type]['city'] = $billing_address->getLocality();
      $this->request[$type]['state'] = $billing_address->getAdministrativeArea();
      $this->request[$type]['postal'] = $billing_address->getPostalCode();
      $this->request[$type]['country'] = $billing_address->getCountryCode();
      $this->request[$type]['first-name'] = $billing_address->getGivenName();
      $this->request[$type]['last-name'] = $billing_address->getFamilyName();
      $this->request[$type]['company'] = $billing_address->getOrganization();
    }

    /** @var \Drupal\telephone\Plugin\Field\FieldType\TelephoneItem $phone */
    $phone = $profile->get($phone_field_name)->first();
    $this->request[$type]['phone'] = $phone->get('value')->getValue();
    $this->request[$type]['email'] = $order->getEmail();

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function saleRequest($redirect_url, $capture, $customer_vault_id = NULL) {
    if (!empty($customer_vault_id)) {
      $this->request['customer-vault-id'] = $customer_vault_id;
    }

    $this->request['redirect-url'] = $redirect_url;
    return $this->execute($capture ? 'sale' : 'auth');
  }

  /**
   * {@inheritdoc}
   */
  public function doCapture($transaction_id, $amount = NULL) {
    if ($amount) {
      $this->request['amount'] = $amount;
    }
    $this->request['transaction-id'] = $transaction_id;
    return $this->execute('capture');
  }

  /**
   * {@inheritdoc}
   */
  public function doRefund($transaction_id, $amount = NULL) {
    if ($amount) {
      $this->request['amount'] = $amount;
    }
    $this->request['transaction-id'] = $transaction_id;
    return $this->execute('refund');
  }

  /**
   * {@inheritdoc}
   */
  public function doVoid($transaction_id) {
    $this->request['transaction-id'] = $transaction_id;
    return $this->execute('void');
  }

  /**
   * {@inheritdoc}
   */
  public function addCustomer() {
    $this->request['add-customer'] = [];
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteCustomerVault($customer_vault_id) {
    $this->request['customer-vault-id'] = $customer_vault_id;
    return $this->execute('delete-customer');
  }

  /**
   * {@inheritdoc}
   */
  public function completeAction($token_id) {
    $this->request['token-id'] = $token_id;
    return $this->execute('complete-action');
  }

  /**
   * Adds all parameters from $this->request to the $parentNode.
   *
   * @param \DOMElement $parentNode
   *   The DOM element.
   */
  protected function processRequest(\DOMElement $parentNode) {
    foreach ($this->request as $key => $value) {
      if (is_array($value)) {
        $parent = $this->xmlRequest->createElement($key);

        foreach ($value as $child_key => $child_value) {
          $this->appendXmlNode($parent, $child_key, $child_value);
        }

        $parentNode->appendChild($parent);
      }
      else {
        $this->appendXmlNode($parentNode, $key, $value);
      }
    }
  }

  /**
   * Appends XML mode to the DOMElement.
   *
   * @param \DOMElement $parentNode
   *   The DOMElement.
   * @param string $name
   *   Name of the node.
   * @param string $value
   *   Value.
   */
  protected function appendXmlNode(\DOMElement $parentNode, $name, $value) {
    $childNode = $this->xmlRequest->createElement($name);
    $childNodeValue = $this->xmlRequest->createTextNode($value);
    $childNode->appendChild($childNodeValue);
    $parentNode->appendChild($childNode);
  }

  /**
   * Executes a request to NMI.
   *
   * @param string $type
   *   Request type. E.g. sale, auth, refund, capture and so on.
   *
   * @return \SimpleXMLElement
   *   The XML response from NMI.
   *
   * @throws \Drupal\commerce_payment\Exception\PaymentGatewayException
   *   In case of a wrong response status code/body or if RequestException.
   */
  protected function execute($type) {
    $xml = $this->xmlRequest->createElement($type);
    $this->processRequest($xml);
    $this->xmlRequest->appendChild($xml);
    $xmlString = $this->xmlRequest->saveXML();

    try {
      /** @var \Psr\Http\Message\ResponseInterface $response */
      $response = $this->httpClient->post(self::GATEWAY_URL, [
        'headers' => [
          'Content-type' => 'text/xml',
        ],
        'timeout' => 30,
        'body' => $xmlString,
        'verify' => TRUE,
      ]);

      if ($response->getStatusCode() != 200) {
        throw new PaymentGatewayException("The request returned with error code {$response->getStatusCode()}");
      }
      elseif (!$response->getBody()) {
        throw new PaymentGatewayException("The NMI response did not have a body");
      }

      return @new \SimpleXMLElement($response->getBody()->getContents());
    }
    catch (RequestException $e) {
      throw new PaymentGatewayException($e->getMessage(), $e->getCode(), $e);
    }
  }

}
