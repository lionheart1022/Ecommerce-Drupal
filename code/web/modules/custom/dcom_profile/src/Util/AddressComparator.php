<?php

namespace Drupal\dcom_profile\Util;

use Drupal\address\AddressInterface;

/**
 * The AddressComparator service.
 *
 * @package Drupal\dcom_profile\Util
 */
class AddressComparator extends ComparatorBase implements AddressComparatorInterface {

  /**
   * {@inheritdoc}
   */
  public function getQueryConditions(AddressInterface $address) {
    return [
      'country_code' => $address->getCountryCode(),
      'administrative_area' => $address->getAdministrativeArea(),
      'locality' => $address->getLocality(),
      'postal_code' => $address->getPostalCode(),
      'address_line1' => $address->getAddressLine1(),
      'address_line2' => $address->getAddressLine2(),
      'organization' => $address->getOrganization(),
      'given_name' => $address->getGivenName(),
      'family_name' => $address->getFamilyName(),
    ];
  }

}
