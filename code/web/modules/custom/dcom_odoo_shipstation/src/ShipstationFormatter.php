<?php

namespace Drupal\dcom_odoo_shipstation;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dcom_odoo_entity_sync\CarrierResolverInterface;
use Drupal\dcom_odoo_entity_sync\Plugin\OdooEntitySync\DiscountOrderItem;
use Drupal\odoo_api\OdooApi\ClientInterface;
use Sabre\Xml\Element\Cdata;
use Sabre\Xml\Service as SabreXmlService;

/**
 * Shipstation feed formatter service.
 */
class ShipstationFormatter implements ShipstationFormatterInterface {

  /**
   * Logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Carrier resolver service.
   *
   * @var \Drupal\dcom_odoo_entity_sync\CarrierResolverInterface
   */
  protected $carrierResolver;

  /**
   * ShipstationFormatter constructor.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_channel_factory
   *   Logger channel factory.
   * @param \Drupal\dcom_odoo_entity_sync\CarrierResolverInterface
   *   Carrier resolver service.
   */
  public function __construct(LoggerChannelFactoryInterface $logger_channel_factory, CarrierResolverInterface $carrier_resolver) {
    $this->logger = $logger_channel_factory->get('dcom_odoo_shipstation');
    $this->carrierResolver = $carrier_resolver;
  }

  /**
   * {@inheritdoc}
   */
  public function formatFeedResponse(array $orders, $count, $page_size) {
    $service = new SabreXmlService();
    $writer = $service->getWriter();
    $writer->openMemory();
    $writer->setIndent(TRUE);
    $writer->startDocument();

    $writer->write([
      [
        'name' => 'Orders',
        'attributes' => [
          'pages' => (string) ceil($count / $page_size),
        ],
        'value' => $this->formatOrders($orders),
      ],
    ]);

    return $writer->outputMemory();
  }

  /**
   * Format Orders node.
   *
   * @param array $orders
   *   Odoo orders array.
   *
   * @return array
   *   Orders node.
   */
  protected function formatOrders(array $orders) {
    $nodes = [];

    foreach ($orders as $order) {
      $order_node = [
        'OrderID' => new Cdata($order['id']),
        'OrderNumber' => new Cdata($order['name']),
        'OrderDate' => $this->formatDate($order['create_date']),
        'OrderStatus' => new Cdata($order['state']),
        'LastModified' => $this->formatDate($order['write_date']),
        // @TODO PaymentMethod
        'OrderTotal' => $order['amount_total'],
        'TaxAmount' => number_format($order['amount_tax'], 2, '.', ''),
        // @TODO: ShippingAmount
        'ShippingAmount' => $order['delivery_price'],
        // @TODO: CustomerNotes
        // @TODO: InternalNotes
        // @TODO: Gift
        // @TODO: GiftMessage
        'Customer' => $this->formatCustomer($order),
        'Items' => $this->formatOrderItems($order),
      ];

      if (!($order_node['Customer'])) {
        // Skip orders with no correct address.
        continue;
      }

      if (isset($order['transfer']['carrier_id'][1])) {
        $order_node['ShippingMethod'] = new Cdata($order['transfer']['carrier_id'][1]);
      }
      $nodes[] = [
        'Order' => $order_node,
      ];
    }

    return $nodes;
  }

  /**
   * Format Odoo date in Shipstation format.
   *
   * @param string $write_date
   *   Odoo date string.
   *
   * @return string
   *   Shipstation date string.
   */
  protected function formatDate($write_date) {
    return DrupalDateTime::createFromFormat(ClientInterface::ODOO_DATETIME_FORMAT, $write_date, 'UTC')
      ->format('m/d/Y H:i');
  }

  /**
   * Format Customer node.
   *
   * @param array $order
   *   Order array.
   *
   * @return array|null
   *   Customer XML node array or NULL.
   */
  protected function formatCustomer(array $order) {
    if (!isset($order['partner'])
      || !isset($order['partner_invoice'])
      || !isset($order['partner_shipping'])) {
      $this
        ->logger
        ->error('Skipping order @id export: either partner, partner_invoice or partner_shipping is missing.', ['@id' => $order['id']]);
      return NULL;
    }

    $bill_to_fields = [
      'Name',
      'Company',
      'Phone',
      'Email',
    ];
    $ship_to_fields = [
      'Name',
      'Company',
      'Address1',
      'Address2',
      'City',
      'State',
      'PostalCode',
      'Country',
      'Phone',
    ];

    $email = isset($order['partner']['email']) ? $order['partner']['email'] : NULL;

    $bill_to = $this->formatAddress($order['partner_invoice'], $bill_to_fields, $order['id'], $order['partner'], $email);
    $ship_to = $this->formatAddress($order['partner_shipping'], $ship_to_fields, $order['id'], $order['partner']);

    if (!$bill_to || !$ship_to) {
      $this
        ->logger
        ->error('Skipping order @id export: either billing or shipping address is incomplete.', ['@id' => $order['id']]);
      return NULL;
    }

    return [
      'CustomerCode' => $order['partner']['id'],
      'BillTo' => $bill_to,
      'ShipTo' => $ship_to,
    ];
  }

  /**
   * Format Shipstation address (BillTo/ShipTo).
   *
   * @param array $address
   *   Address (contact) array.
   * @param array $fields
   *   Array of fields to return.
   * @param int $order_id
   *   Order ID, used for logging.
   * @param array $partner
   *   Primary partner object fields array.
   * @param string|null $email
   *   Customer email address, if known.
   *
   * @return array
   *   Address node array.
   */
  protected function formatAddress(array $address, array $fields, $order_id, array $partner, $email = NULL) {
    $required_fields = [
      'name' => 'Name',
      'phone' => 'Phone',
      'street' => 'Address1',
      'city' => 'City',
      'country' => 'Country',
      'state' => 'State',
      'zip' => 'PostalCode',
    ];

    if (empty($address['name']) && !empty($partner['name'])) {
      // Fetch primary user contact name if address name is not available.
      $address['name'] = $partner['name'];
    }

    $node = [];
    foreach ($required_fields as $odoo_field => $shipstation_field) {
      if (array_search($shipstation_field, $fields) !== FALSE) {
        // Field is in the return list.
        if (empty($address[$odoo_field])) {
          // No field data, fail.
          $message = (string) (new FormattableMarkup('Missing required address %field field for an order ID %id', ['%field' => $odoo_field, '%id' => $order_id]));
          $this
            ->logger
            ->error($message);
          return NULL;
        }

        // Field data is ok, add to the node.
        $node[$shipstation_field] = new Cdata($address[$odoo_field]);
      }

    }

    // Additional fields.
    if (isset($email) && array_search('Email', $fields) !== FALSE) {
      $node['Email'] = new Cdata($email);
    }
    if (!empty($address['street2']) && array_search('Address2', $fields) !== FALSE) {
      $node['Address2'] = new Cdata($address['street2']);
    }

    return $node;
  }

  /**
   * Format order Items node.
   *
   * @param array $order
   *   Order array.
   *
   * @return array|null
   *   Order Items node.
   */
  protected function formatOrderItems(array $order) {
    if (!isset($order['order_line_rows'])) {
      $this
        ->logger
        ->error('Missing order items, order ID: @id.', ['@id' => $order['id']]);
      return NULL;
    }

    $items = [];
    foreach ($order['order_line_rows'] as $order_line_row) {
      $items[] = $this->formatOrderLine($order_line_row);
    }
    foreach ($order['order_line_rows'] as $order_line_row) {
      if ($adjustment_lines = $this->formatAdjustmentOrderLines($order_line_row)) {
        foreach ($adjustment_lines as $adjustment_line) {
          $items[] = $adjustment_line;
        }
      }
    }

    return $items;
  }

  /**
   * Format order item node.
   *
   * @param array $order_line
   *   Order line array.
   *
   * @return array|null
   *   Order item node or NULL.
   */
  protected function formatOrderLine(array $order_line) {
    if (!isset($order_line['product'])) {
      $this
        ->logger
        ->error('Missing product for an order line, order line ID: @id.', ['@id' => $order_line['id']]);
      return NULL;
    }
    $product = $order_line['product'];

    if ($product['id'] == DiscountOrderItem::ODOO_PROMOTION_PRODUCT_ID) {
      // Skip order line if it's a promotion.
      // Promotions are handled separately.
      return NULL;
    }
    if ($this->carrierResolver->isDeliveryProduct($product['id'])) {
      // Skip order line if it's a delivery product.
      return NULL;
    }

    $node = [
      'LineItemID' => $order_line['id'],
      'SKU' => $product['default_code'],
      'Name' => trim(str_replace('[' . $product['default_code'] . ']', '', $product['display_name'])),
      'Quantity' => $order_line['product_qty'],
      'UnitPrice' => $order_line['price_unit'],
      // Odoo stores weight in kilograms.
      'Weight' => $product['weight'] * 1000,
      'WeightUnits' => 'Grams',
    ];

    // Append product attribute, if present.
    if (!empty($product['attribute_name'])
      && !empty($product['attribute_value'])) {
      $node['Options'] = [
        'Option' => [
          'Name' => $product['attribute_name'],
          'Value' => $product['attribute_value'],
        ],
      ];
    }

    return [
      'Item' => $node,
    ];
  }

  /**
   * Format order item node.
   *
   * @param array $order_line
   *   Order line array.
   *
   * @return array|null
   *   Order item node or NULL.
   */
  protected function formatAdjustmentOrderLines(array $order_line) {
    if (!isset($order_line['product'])) {
      // Same error already logged at formatOrderLine(), no need to do it twice.
      return NULL;
    }
    $product = $order_line['product'];

    if ($product['id'] != DiscountOrderItem::ODOO_PROMOTION_PRODUCT_ID) {
      // Skip order line if it's not a promotion.
      return NULL;
    }

    $lines = [];
    foreach (explode(PHP_EOL, $order_line['name']) as $discount_line) {
      $discount_array = @Json::decode($discount_line);
      if (!$discount_array || count($discount_array) != 4) {
        // Invalid JSON, skip the line.
        continue;
      }

      list ($discount_type, $code, $amount,) = $discount_array;
      $sku = $name = '';

      switch ($discount_type) {
        case 'COUPON':
          $sku = 'COUPON:' . $code;
          $name = 'Coupon code: ' . $code;
          break;

        case 'PROMOTION':
          $sku = 'PROMOTION:' . $code;
          $name = 'Promotion/discount: ' . $code;
          break;

        default:
          // Skip invalid line.
          continue;
      }

      $node = [
        'SKU' => new Cdata($sku),
        'Name' => new Cdata($name),
        'Quantity' => 1,
        'UnitPrice' => $amount,
        'Adjustment' => 'true',
      ];

      $lines[] = [
        'Item' => $node,
      ];
    }

    return $lines;
  }

}
