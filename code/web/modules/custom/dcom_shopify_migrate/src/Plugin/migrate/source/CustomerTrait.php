<?php

namespace Drupal\dcom_shopify_migrate\Plugin\migrate\source;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Trait CustomerTrait.
 *
 * @package Drupal\dcom_shopify_migrate\Plugin\migrate\source
 */
trait CustomerTrait {

  use StringTranslationTrait;

  /**
   * Returns available fields on the source.
   *
   * @return array
   *   Available fields in the source, keys are the field machine names as used
   *   in field mappings, values are descriptions.
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

}
