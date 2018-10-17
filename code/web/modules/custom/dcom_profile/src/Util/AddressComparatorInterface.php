<?php

namespace Drupal\dcom_profile\Util;

use Drupal\address\AddressInterface;

/**
 * Interface AddressComparatorInterface.
 *
 * @package Drupal\dcom_profile\Util
 */
interface AddressComparatorInterface {

  /**
   * Returns an array of address query conditions.
   *
   * @param \Drupal\address\AddressInterface $address
   *   The address.
   *
   * @return array
   *   An array of field values, keyed by field names.
   */
  public function getQueryConditions(AddressInterface $address);

}
