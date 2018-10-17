<?php

namespace Drupal\address_helper\Data;

/**
 * Base suggestion item class.
 */
abstract class SuggestionItemBase implements SuggestionItemInterface {

  /**
   * {@inheritdoc}
   */
  public function toArray() {
    return [
      'label' => $this->getSuggestionLabel(),
      'secondary_label' => $this->getSecondaryLabel(),
      'address' => $this->getAddressFields(),
    ];
  }

  /**
   * Get address fields array.
   *
   * @return array
   *   Address fields array.
   */
  protected function getAddressFields() {
    $address = $this->getAddress();
    return [
      'administrative_area' => $address->getAdministrativeArea(),
      'locality' => $address->getLocality(),
      'dependent_locality' => $address->getDependentLocality(),
      'postal_code' => $address->getPostalCode(),
      'sorting_code' => $address->getSortingCode(),
      'address_line1' => $address->getAddressLine1(),
      'address_line2' => $address->getAddressLine2(),
      'organization' => $address->getOrganization(),
    ];
  }

}
