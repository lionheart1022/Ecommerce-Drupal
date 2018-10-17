<?php

namespace Drupal\shopify_migrate\Plugin\migrate\source;

/**
 * Provides a Shopify Customer Address migrate source.
 *
 * Usage example:
 *
 * @code
 * source:
 *   plugin: shopify_customer_address
 *   shopify:
 *     shop_domain: mydomain.myshopify.com
 *     api_key: API_KEY
 *     password: PASSWORD
 *     shared_secret: SHARED_SECRET
 * @endcode
 *
 * @MigrateSource(
 *  id = "shopify_customer_address"
 * )
 */
class CustomerAddress extends Base {

  /**
   * Increase API fetch limit.
   *
   * {@inheritdoc}
   */
  protected $pagerLimit = 200;

  /**
   * Addresses aren't countable since they are properties of customers.
   *
   * {@inheritdoc}
   */
  protected $skipCount = TRUE;

  /**
   * {@inheritdoc}
   */
  protected function getShopifyResource() {
    return 'customers';
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'address1' => $this->t("The customer's mailing address"),
      'address2' => $this->t("An additional field for the customer's mailing address"),
      'city' => $this->t("The customer's city"),
      'customer_id' => $this->t("A unique identifier for the customer."),
      'company' => $this->t("The customer's company"),
      'country' => $this->t("The customer's country"),
      'country_code' => $this->t("The two-letter country code corresponding to the customer's country"),
      'country_name' => $this->t("The customer's normalized country name"),
      'default' => $this->t("Whether this address is the default address for the customer"),
      'first_name' => $this->t("The customer's first name"),
      'id' => $this->t("A unique identifier for the address."),
      'last_name' => $this->t("The customer's last name"),
      'name' => $this->t("The customer's first and last names"),
      'phone' => $this->t("The customer's phone number at this address"),
      'province' => $this->t("The customer's province or state name"),
      'province_code' => $this->t("The two-letter province code for the customer's province or state"),
      'zip' => $this->t("The customer's zip or postal code"),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function initializeIterator() {
    foreach (parent::initializeIterator() as $customer) {
      if (empty($customer['addresses'])) {
        continue;
      }
      foreach ($customer['addresses'] as $address) {
        // Extract addresses from customers.
        $address_array = (array) $address;
        yield $address_array;
      }
    }
  }

}
