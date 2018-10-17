<?php

namespace Drupal\dcom_profile\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Validation constraint for telephone number in Profile entity.
 *
 * @Constraint(
 *   id = "DcomProfileTelephoneNumber",
 *   label = @Translation("Customer profile telephone number ", context = "Validation")
 * )
 */
class DcomProfileTelephoneNumberConstraint extends Constraint {

  public $message = 'The telephone number %value is not in the right format.';

}
