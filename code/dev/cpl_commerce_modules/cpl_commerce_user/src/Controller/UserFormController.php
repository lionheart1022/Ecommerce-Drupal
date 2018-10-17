<?php

namespace Drupal\cpl_commerce_user\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller routines for user routes.
 */
class UserFormController extends ControllerBase {

  public function contentBusiness($user) {
    return $this->content($user, TRUE);
  }

  public function content($user, $business = FALSE) {

    $form = $this->entityFormBuilder()->getForm($user);
    $fields_to_hide = [
      'notify',
      'roles',
      'status',
      'path',
      'field_domain_access',
      'field_domain_all_affiliates',
      'field_domain_admin',
      'rabbit_hole',
    ];
    $business_fields = [
      'pass',
      'current_pass',
      'mail',
      'name',
      'field_first_name',
      'field_last_name',
      'field_phone',
    ];
    if ($business) {
      $fields_to_hide = array_merge($fields_to_hide, $business_fields);
    }
    foreach ($fields_to_hide as $field_name) {
      if (isset($form['account'][$field_name])) {
        $form['account'][$field_name]['#access'] = FALSE;
      }
      elseif (isset($form[$field_name])) {
        $form[$field_name]['#access'] = FALSE;
      }
    }
    $form['account']['name']['#weight'] = -10;
    $form['account']['mail']['#weight'] = -9;

    return $form;
  }

}
