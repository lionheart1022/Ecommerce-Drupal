<?php

namespace Drupal\cpl_commerce_user\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller routines for user routes.
 */
class UserController extends ControllerBase {

  /**
   * Access callback for "cpl_commerce_user.my_account" route.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function accountPageAccess() {
    /** @var \Drupal\user\Entity\User $current_user */
    $current_user = $this->entityTypeManager()
      ->getStorage('user')
      ->load($this->currentUser()->id());
    // Check user.update access.
    return $this->entityTypeManager()
      ->getAccessControlHandler('user')
      ->access($current_user, 'update', $current_user, TRUE);
  }

  /**
   * Redirects users to their account page.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Returns a redirect to the account of the currently logged in user.
   */
  public function accountPage() {
    return $this->redirect('cpl_commerce_user.account', ['user' => $this->currentUser()->id()]);
  }

}
