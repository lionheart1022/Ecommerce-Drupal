<?php

/**
 * @file
 * Contains Drupal\multiple_registration\Controller\MultipleRegistrationController.
 */

namespace Drupal\dcom_user\Controller;

use Drupal\multiple_registration\Controller\MultipleRegistrationController;

class MultipleRegistrationAlterController extends MultipleRegistrationController {


  /**
   * Check is field available for role.
   *
   * @param array $fieldRoles
   * @param string $route_name
   *
   * @return bool
   */
  public static function checkFieldAccess($fieldRoles) {
    $routeMatch = \Drupal::routeMatch();
    $roles = array();
    switch ($routeMatch->getRouteName()) {

      // Role page registration.
      case 'multiple_registration.role_registration_page':
        $roles = array($routeMatch->getParameter('rid'));
        break;

      // Default registration.
      case 'user.register':
        $roles = array(self::MULTIPLE_REGISTRATION_GENERAL_REGISTRATION_ID);
        break;

      // User edit page.
      case 'entity.user.edit_form':
      case 'cpl_commerce_user.business':
        $roles = $routeMatch->getParameter('user')->getRoles();
        if (!static::useRegistrationPage($roles)) {

          // Fall back to 'General registered users' if user does not have any
          // special role.
          $roles = array(self::MULTIPLE_REGISTRATION_GENERAL_REGISTRATION_ID);
        }
        break;
    }

    $extractKeys = array_intersect($roles, $fieldRoles);

    if (!empty($extractKeys)) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

}
