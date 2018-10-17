<?php

namespace Drupal\dcom_odoo_shipstation;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\odoo_api\OdooApi\ClientInterface;
use Drupal\odoo_api\OdooApi\Data\AddressResolverInterface;
use Drupal\odoo_api\OdooApi\Exception\DataException;

/**
 * Orders list fetcher service.
 */
class OrdersList implements OrdersListInterface {

  /**
   * Odoo API client.
   *
   * @var \Drupal\odoo_api\OdooApi\ClientInterface
   */
  protected $odooApi;

  /**
   * Odoo API client.
   *
   * @var \Drupal\odoo_api\OdooApi\Data\AddressResolverInterface
   */
  protected $addressResolver;

  /**
   * Customer profiles/contacts fetch cache.
   *
   * @var array
   */
  protected $contacts = [];

  /**
   * Products fetch cache.
   *
   * @var array
   */
  protected $products = [];

  /**
   * OrdersList constructor.
   *
   * @param \Drupal\odoo_api\OdooApi\ClientInterface $odoo_api_api_client
   *   Odoo API client service.
   * @param \Drupal\odoo_api\OdooApi\Data\AddressResolverInterface $address_resolver
   *   Odoo address resolver service.
   */
  public function __construct(ClientInterface $odoo_api_api_client, AddressResolverInterface $address_resolver) {
    $this->odooApi = $odoo_api_api_client;
    $this->addressResolver = $address_resolver;
  }

  /**
   * {@inheritdoc}
   */
  public function getOrdersList($offset = 0, $limit = 100, $start_date = NULL, $end_date = NULL) {
    $orders = $this->fetchOrders($offset, $limit, $start_date, $end_date);
    $this->appendCustomerProfiles($orders);
    $this->appendOrderItems($orders);
    $this->appendTransfers($orders);

    return $orders;
  }

  /**
   * {@inheritdoc}
   */
  public function getOrdersCount($start_date = NULL, $end_date = NULL) {
    return $this->odooApi->count('sale.order', $this->ordersFilter($start_date, $end_date));
  }

  /**
   * Get Odoo orders request filter.
   *
   * @param int|null $start_date
   *   Only get orders changed since given timestamp. NULL to omit filter.
   * @param int|null $end_date
   *   Only get orders changed before given timestamp. NULL to omit filter.
   *
   * @return array
   *   Odoo filters.
   */
  protected function ordersFilter($start_date = NULL, $end_date = NULL) {
    $filter = [
      // Do not fetch draft orders.
      ['state', '!=', 'draft'],
    ];

    if (isset($start_date)) {
      $odoo_start_date = $this->getOdooDateTimeString($start_date);

      $filter[] = '|';
      $filter[] = '|';
      $filter[] = [
        'write_date',
        '>=',
        $odoo_start_date,
      ];
      $filter[] = [
        'order_line.write_date',
        '>=',
        $odoo_start_date,
      ];
      $filter[] = [
        'picking_ids.write_date',
        '>=',
        $odoo_start_date,
      ];
    }

    if (isset($end_date)) {
      $odoo_end_date = $this->getOdooDateTimeString($end_date);
      $filter[] = '|';
      $filter[] = '|';
      $filter[] = [
        'write_date',
        '<=',
        $odoo_end_date,
      ];
      $filter[] = [
        'order_line.write_date',
        '<=',
        $odoo_end_date,
      ];
      $filter[] = [
        'picking_ids.write_date',
        '<=',
        $odoo_end_date,
      ];
    }

    return $filter;
  }

  /**
   * Fetch latest order from Odoo.
   *
   * @param int $offset
   *   Query offset.
   * @param int $limit
   *   Query limit.
   * @param int|null $start_date
   *   Only get orders changed since given timestamp. NULL to omit filter.
   * @param int|null $end_date
   *   Only get orders changed before given timestamp. NULL to omit filter.
   *
   * @return array
   *   Array of Odoo orders.
   */
  protected function fetchOrders($offset = 0, $limit = 100, $start_date = NULL, $end_date = NULL) {
    $fields = [
      // Object ID.
      'id',
      // Order number.
      'name',
      // Creation date.
      'create_date',
      // Change date.
      'write_date',
      // Transfer ID(s).
      'picking_ids',

      'amount_total',
      'amount_tax',
      'delivery_price',

      // Customer ID.
      'partner_id',

      // Shipping profile (contact) ID.
      'partner_shipping_id',
      // Billing profile (contact) ID.
      'partner_invoice_id',

      // Order lines.
      'order_line',

      // Order status.
      'state',
    ];

    // Sort by ID.
    // ShipStation will query pager.
    $orders = $this->odooApi->searchRead('sale.order', $this->ordersFilter($start_date, $end_date), $fields, $offset, $limit, 'id desc');

    return $orders;
  }

  /**
   * Fetch customer profiles and append them to given orders list.
   *
   * @param array $orders
   *   Orders list.
   */
  protected function appendCustomerProfiles(array &$orders) {
    $contact_ids = [];

    foreach ($orders as $order) {
      // Fetch master customer's profile, shipping and profiles.
      $fields = ['partner_id', 'partner_shipping_id', 'partner_invoice_id'];
      foreach ($fields as $field) {
        if (!empty($order[$field][0])) {
          $contact_ids[$order[$field][0]] = $order[$field][0];
        }
      }
    }

    $contacts = $this->getContacts($contact_ids);

    foreach ($orders as &$order) {
      // Fetch master customer's profile, shipping and profiles.
      $fields = [
        'partner_id' => 'partner',
        'partner_shipping_id' => 'partner_shipping',
        'partner_invoice_id' => 'partner_invoice',
      ];
      foreach ($fields as $id_field => $object_field) {
        $order[$object_field] = NULL;
        if (!empty($order[$id_field][0])) {
          $contact_id = $order[$id_field][0];
          if (!isset($contacts[$contact_id])) {
            // @TODO: Log warning.
          }
          else {
            $order[$object_field] = $contacts[$contact_id];
          }
        }
      }
    }
  }

  /**
   * Fetch contacts with given IDs.
   *
   * This method may use internal cache to avoid re-downloading same objects.
   *
   * @param array $contact_ids
   *   Array of Odoo Contacts IDs.
   *
   * @return array
   *   Contacts fetched from Odoo, keyed by ID.
   */
  protected function getContacts(array $contact_ids) {
    $contacts_to_fetch = array_diff_key($contact_ids, $this->contacts);

    // @TODO: Fetch companies.
    if ($contacts_to_fetch) {
      $fields = [
        'id',
        'name',
        'street',
        'street2',
        'city',
        'state_id',
        'zip',
        'country_id',
        'phone',
        'email',
      ];
      foreach ($this->odooApi->read('res.partner', array_values($contacts_to_fetch), $fields) as $contact) {
        try {
          if (isset($contact['country_id'][0])) {
            $contact['country'] = $this
              ->addressResolver
              ->findCountryCodeById($contact['country_id'][0]);

            if (isset($contact['state_id'][0])) {
              $contact['state'] = $this
                ->addressResolver
                ->findStateCodeById($contact['country_id'][0], $contact['state_id'][0]);
            }
          }
        }
        catch (DataException $e) {
          // @TODO: Log error.
        }
        $this->contacts[$contact['id']] = $contact;
      }
    }

    // Only return profiles requested.
    return array_intersect_key($this->contacts, $contact_ids);
  }

  /**
   * Fetch order lines and append them to given orders list.
   *
   * @param array $orders
   *   Orders list.
   */
  protected function appendOrderItems(array &$orders) {
    $order_lines_to_fetch = [];

    foreach ($orders as $order) {
      if (!empty($order['order_line'])) {
        $order_lines_to_fetch = array_merge($order_lines_to_fetch, $order['order_line']);
      }
    }

    $order_lines = $this->getOrderLines($order_lines_to_fetch);

    foreach ($orders as &$order) {
      // Fetch master customer's profile, shipping and profiles.
      if (!empty($order['order_line'])) {
        $order['order_line_rows'] = array_intersect_key($order_lines, array_combine($order['order_line'], $order['order_line']));
      }
    }

    // @TODO: Add order adjustments.
  }

  /**
   * Fetch order lines with given IDs.
   *
   * @param array $order_line_ids
   *   IDs of order lines.
   *
   * @return array
   *   Order lines list, with products.
   */
  protected function getOrderLines(array $order_line_ids) {
    $fields = [
      'id',
      'product_qty',
      'product_id',
      'price_unit',
      'name',
    ];

    $order_lines = [];
    foreach ($this->odooApi->read('sale.order.line', $order_line_ids, $fields) as $row) {
      $order_lines[$row['id']] = $row;
    }

    $this->appendProducts($order_lines);

    return $order_lines;
  }

  /**
   * Fetch products and append them to order lines.
   *
   * @param array $order_lines
   *   Order lines list.
   */
  protected function appendProducts(array &$order_lines) {
    $products_to_fetch = [];

    foreach ($order_lines as $order_line) {
      if (!empty($order_line['product_id'][0])) {
        $products_to_fetch[$order_line['product_id'][0]] = $order_line['product_id'][0];
      }
    }

    $products = $this->getProducts($products_to_fetch);

    foreach ($order_lines as &$order_line) {
      if (!empty($order_line['product_id'][0])
        && !empty($products[$order_line['product_id'][0]])) {
        $order_line['product'] = $products[$order_line['product_id'][0]];
      }
    }
  }

  /**
   * Fetch products with given IDs.
   *
   * This method may use internal cache to avoid re-downloading same objects.
   *
   * @param array $product_ids
   *   Array of Odoo Product IDs.
   *
   * @return array
   *   Products fetched from Odoo, keyed by ID.
   */
  protected function getProducts(array $product_ids) {
    $products_to_fetch = array_diff_key($product_ids, $this->products);

    if ($products_to_fetch) {
      $fields = [
        'id',
        'code',
        'default_code',
        'name',
        'display_name',
        'weight',
        'attribute_value_ids',
      ];
      foreach ($this->odooApi->read('product.product', array_values($products_to_fetch), $fields) as $product) {
        $this->products[$product['id']] = $product;
      }
      $this->appendProductAttributes(array_values($products_to_fetch));
    }

    // Only return products requested.
    return array_intersect_key($this->products, $product_ids);
  }

  /**
   * Convert timestamp to Odoo datetime string.
   *
   * @param int $timestamp
   *   Timestamp.
   *
   * @return string
   *   Odoo datetime string.
   */
  protected function getOdooDateTimeString($timestamp) {
    if (!is_numeric($timestamp)) {
      throw new \InvalidArgumentException('Timestamp should be integer.');
    }

    return DrupalDateTime::createFromTimestamp($timestamp)
      ->setTimezone(new \DateTimeZone('UTC'))
      ->format(ClientInterface::ODOO_DATETIME_FORMAT);
  }

  /**
   * Fetch orders transfers and append them to orders.
   *
   * @param array $orders
   *   Orders list.
   */
  protected function appendTransfers(array &$orders) {
    $transfers_to_fetch = [];

    foreach ($orders as $order) {
      // @TODO: Support multiple transfers.
      if (!empty($order['picking_ids'][0])) {
        $transfers_to_fetch[$order['picking_ids'][0]] = (int) $order['picking_ids'][0];
      }
    }

    $transfers = $this->getTransfers($transfers_to_fetch);

    foreach ($orders as &$order) {
      if (!empty($order['picking_ids'][0])
        && !empty($transfers[$order['picking_ids'][0]])) {
        $order['transfer'] = $transfers[$order['picking_ids'][0]];
      }
    }
  }

  /**
   * Fetch Transfsers from Odoo.
   *
   * @param array $transfer_ids
   *   Array of transfers IDs.
   *
   * @return array
   *   Array of Odoo Transfers.
   */
  protected function getTransfers(array $transfer_ids) {
    $fields = [
      'id',
      'delivery_type',
      'carrier_id',
    ];

    $transfers = [];
    foreach ($this->odooApi->read('stock.picking', array_values($transfer_ids), $fields) as $row) {
      $transfers[$row['id']] = $row;
    }

    return $transfers;
  }

  /**
   * Append attributes to cached products.
   *
   * @param array $product_ids
   *   Product IDs.
   */
  protected function appendProductAttributes(array $product_ids) {
    $fields = [
      'id',
      'attribute_id',
      'name',
    ];

    $attribute_values_to_fetch = [];
    foreach (array_intersect_key($this->products, array_combine($product_ids, $product_ids)) as $id => $product) {
      if (!empty($product['attribute_value_ids'])) {
        $attribute_values_to_fetch += array_combine($product['attribute_value_ids'], $product['attribute_value_ids']);
      }
    }

    foreach ($attribute_values_to_fetch as &$id) {
      $id = (int) $id;
    }

    $attribute_values = [];
    foreach ($this->odooApi->read('product.attribute.value', array_values($attribute_values_to_fetch), $fields) as $attribute_value) {
      $attribute_values[$attribute_value['id']] = $attribute_value;
    }

    foreach (array_intersect_key($this->products, array_combine($product_ids, $product_ids)) as $id => $product) {
      if (!empty($product['attribute_value_ids'])) {
        $attribute_value_id = reset($product['attribute_value_ids']);
        if (!empty($attribute_values[$attribute_value_id]['name'])
          && !empty($attribute_values[$attribute_value_id]['attribute_id'][1])) {
          $this->products[$id]['attribute_name'] = $attribute_values[$attribute_value_id]['attribute_id'][1];
          $this->products[$id]['attribute_value'] = $attribute_values[$attribute_value_id]['name'];
        }
      }
    }
  }

}
