<?php

namespace Drupal\dcom_profile;

use Drupal\commerce_order\Element\ProfileSelect as BaseProfileSelect;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class ProfileSelect.
 *
 * Extends the commerce_order ProfileSelect element to avoid profiles
 * duplication. It is done in this way so anywhere in the code where we use
 * commerce_profile_select RenderElement profiles duplication won't be allowed.
 *
 * @package Drupal\dcom_profile
 */
class ProfileSelect extends BaseProfileSelect {

  /**
   * {@inheritdoc}
   */
  public static function submitForm(array &$element, FormStateInterface $form_state) {
    $form_display = EntityFormDisplay::collectRenderDisplay($element['#profile'], 'default');
    $form_display->extractFormValues($element['#profile'], $element, $form_state);
    /** @var \Drupal\profile\Entity\ProfileInterface $profile */
    $profile = $element['#profile'];

    // Try to find an existing profile if the current one is new and has
    // a phone number and address.
    $phone = $profile->get('field_phone_number');
    $address = $profile->get('address');
    if (!$phone->isEmpty() && !$address->isEmpty()) {
      /** @var \Drupal\dcom_profile\Util\ProfileComparatorInterface $profile_comparator */
      $profile_comparator = \Drupal::service('dcom_profile.profile_comparator');

      if ($profile->isNew()) {
        $existing_profile = $profile_comparator->findProfileByProfile($profile, TRUE);
      }
      elseif ($form_state->getBuildInfo()['form_id'] == 'commerce_checkout_flow_cpl_commerce_checkout_4step') {
        // Do not update existing profiles which are referenced by shipped or
        // placed orders: on the checkout page the user enters
        // a shipping information -> The profile comparator returns an existing
        // profile which has a reference to a placed or shipped order ->
        // The customer goes next and then goes back to the shipping profile
        // step for some reason -> here the profile can be update. Avoid it.
        $original_profile = \Drupal::entityTypeManager()
          ->getStorage('profile')
          ->load($profile->id());

        // If the profile has been changed.
        if (!$profile_comparator->equals($original_profile, $profile)) {
          // We do not call $profile->access('update') because it might trigger
          // other access check handlers which can return FALSE even if there
          // is no placed/shipped order reference to the profile.
          /** @var \Drupal\Core\Access\AccessResultInterface $result */
          $result = dcom_profile_profile_access($profile, 'update', \Drupal::currentUser());

          // The function dcom_profile_profile_access doesn't allow to update
          // the profile because there is a reference to it from placed or
          // shipped order.
          if ($result->isForbidden()) {
            // Save the new profile with the updated data, instead of updating
            // the existing one which has to a placed/shipped order.
            $element['#profile'] = $profile->createDuplicate();
          }
        }
      }
    }

    if (isset($existing_profile) && $existing_profile) {
      // In case if the user already has few equal profiles - select
      // the first one.
      $element['#profile'] = reset($existing_profile);
    }
    else {
      parent::submitForm($element, $form_state);
    }
  }

}
