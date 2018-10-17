<?php

namespace Drupal\shopify_migrate\Plugin\migrate\source;

/**
 * Provides a Shopify Customer migrate source.
 *
 * Usage example:
 *
 * @code
 * source:
 *   plugin: shopify_customer
 *   shopify:
 *     shop_domain: mydomain.myshopify.com
 *     api_key: API_KEY
 *     password: PASSWORD
 *     shared_secret: SHARED_SECRET
 * @endcode
 *
 * @MigrateSource(
 *  id = "shopify_customer"
 * )
 */
class Customer extends Base {

  /**
   * Increase API fetch limit.
   *
   * {@inheritdoc}
   */
  protected $pagerLimit = 200;

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
      'created_at' => $this->t('The date and time (ISO 8601 format) when the customer was created.'),
      'email' => $this->t('The unique email address of the customer.'),
      'first_name' => $this->t("The customer's first name."),
      'id' => $this->t('A unique identifier for the customer.'),
      'last_name' => $this->t("The customer's last name."),
      'phone' => $this->t('The unique phone number (E.164 format).'),
      'state' => $this->t("The state of the customer's account with a shop."),
      'updated_at' => $this->t('The date and time (ISO 8601 format) when the customer information was last updated.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function initializeIterator() {
    foreach (parent::initializeIterator() as $customer) {
      $addresses = [];
      if (!empty($customer['addresses'])) {
        foreach ($customer['addresses'] as $address) {
          $addresses[] = $address->id;
        }
      }
      $customer['addresses'] = $addresses;

      // Add default address, if avail.
      if (!empty($customer['default_address'])) {
        $customer['default_address'] = (array) $customer['default_address'];
      }
      yield $customer;
    }
  }

}
