<?php

namespace Drupal\dcom_profile\Plugin\Validation\Constraint;

use Drupal\profile\Entity\ProfileInterface;
use libphonenumber\CountryCodeToRegionCodeMap;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberUtil;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Constraint validator for checking telephone number format.
 */
class DcomProfileTelephoneNumberConstraintValidator extends ConstraintValidator {

  /**
   * Utility for international phone numbers.
   *
   * @var \libphonenumber\PhoneNumberUtil
   */
  private $phoneUtil;

  /**
   * Creates a new DcomProfileTelephoneNumberConstraintValidator instance.
   */
  public function __construct() {
    $this->phoneUtil = PhoneNumberUtil::getInstance();
  }

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    // Profile entity object.
    $entity = !empty($value->getParent()) ? $value->getEntity() : NULL;

    $country_code = $this->getCountryCode($entity);
    $item = $value->first();
    if (!empty($item->value)) {
      $number_proto = NULL;
      try {
        $number_proto = $this->phoneUtil->parse($item->value, $country_code);
      }
      catch (NumberParseException $e) {
        $this->context->addViolation($e->getMessage());
      }

      if ($number_proto instanceof PhoneNumber
        && !$this->isValidNumber($number_proto, $country_code)
      ) {
        $this->context->addViolation($constraint->message, ['%value' => $item->value]);
      }
    }
  }

  /**
   * Get country code form address field in Profile entity.
   *
   * @param object|null $profile
   *   Customer profile entity object.
   *
   * @return string|null
   *   Country code or NULL.
   */
  protected function getCountryCode($profile) {
    $country_code = NULL;
    if ($profile instanceof ProfileInterface) {
      $country_code = $profile->address->country_code;
    }
    return $country_code;
  }

  /**
   * Tests whether a phone number is valid for a certain region.
   *
   * @param \libphonenumber\PhoneNumber $number
   *   The phone number that we want to validate.
   * @param string $country_code
   *   Contry code.
   *
   * @return bool
   *   TRUE if the number is valid, FALSE otherwise
   */
  public function isValidNumber(PhoneNumber $number, $country_code) {
    if (!$this->phoneUtil->isValidNumberForRegion($number, $country_code)) {
      $region_code_map = CountryCodeToRegionCodeMap::$countryCodeToRegionCodeMap;
      $regions = $region_code_map[$this->phoneUtil->getCountryCodeForRegion($country_code)];
      if (!empty($regions)) {
        foreach ($regions as $region) {
          if ($this->phoneUtil->isValidNumberForRegion($number, $region)) {
            return TRUE;
          }
        }
      }
      return FALSE;
    }
    return TRUE;
  }

}
