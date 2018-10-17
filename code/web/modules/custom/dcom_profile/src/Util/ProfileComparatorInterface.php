<?php

namespace Drupal\dcom_profile\Util;

use Drupal\profile\Entity\ProfileInterface;

/**
 * Interface ProfileComparatorInterface.
 *
 * @package Drupal\dcom_profile\Util
 */
interface ProfileComparatorInterface {

  /**
   * Checks whether the profiles are equal or not.
   *
   * @param \Drupal\profile\Entity\ProfileInterface $original_profile
   *   The original profile.
   * @param \Drupal\profile\Entity\ProfileInterface $profile
   *   The profile.
   *
   * @return bool
   *   TRUE if the profiles are equal or FALSE otherwise.
   */
  public function equals(ProfileInterface $original_profile, ProfileInterface $profile);

  /**
   * Finds an equal profile.
   *
   * @param \Drupal\profile\Entity\ProfileInterface $profile
   *   The source profile.
   * @param bool $load_entities
   *   Whether load found entities or not.
   *
   * @return \Drupal\profile\Entity\ProfileInterface[]|string[]
   *   An array of profile IDs or loaded profiles if $load_entities - TRUE.
   */
  public function findProfileByProfile(ProfileInterface $profile, $load_entities = FALSE);

  /**
   * Finds an equal profile by the provided details.
   *
   * @param array $profile
   *   The profile details in the following structure:
   *   type - profile type
   *   status - active or not
   *   field_phone_number - phone
   *   address:
   *     given_name - first name
   *     family_name - last name
   *     organization - organization
   *     address_line1 - address_line1
   *     address_line2 - address_line2
   *     locality - e.g. city
   *     administrative_area - administrative_area
   *     postal_code - postal_code
   *     country_code - country_code.
   * @param bool $load_entities
   *   Whether load found entities or not.
   *
   * @return \Drupal\profile\Entity\ProfileInterface[]|string[]
   *   An array of profile IDs or loaded profiles if $load_entities - TRUE.
   */
  public function findProfileByArray(array $profile, $load_entities = FALSE);

}
