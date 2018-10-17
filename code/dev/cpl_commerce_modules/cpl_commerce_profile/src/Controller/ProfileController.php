<?php

namespace Drupal\cpl_commerce_profile\Controller;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\profile\Controller\ProfileController as ProfileControllerBase;
use Drupal\profile\Entity\ProfileTypeInterface;
use Drupal\user\UserInterface;

/**
 * Adds adjustment to the \Drupal\profile\Controller\ProfileController.
 */
class ProfileController extends ProfileControllerBase {

  /**
   * {@inheritdoc}
   */
  public function userProfileForm(RouteMatchInterface $route_match, UserInterface $user, ProfileTypeInterface $profile_type) {
    $build = parent::userProfileForm($route_match, $user, $profile_type);
    $multiple = $profile_type->getMultiple();
    $add_profile = isset($build['add_profile']);
    $active_profiles = isset($build['active_profiles']);
    $customer = $profile_type->id() == 'customer';

    // Do nothing if we don't have profiles view and "Add new address" link.
    if (!$multiple || !$add_profile || !$active_profiles || !$customer) {
      return $build;
    }

    // Rename the link to Add New Address. Add a class to it.
    $build['add_profile']['#title'] = $this->t('Add New Address');
    /** @var \Drupal\Core\Url $url */
    $url = $build['add_profile']['#url'];
    $attributes = $url->getOption('attributes') ?: [];
    $attributes['class'][] = 'add-new-address';
    $url->setOption('attributes', $attributes);

    // Render Add Profile form.
    $build['form'] = $this->addProfile($route_match, $user, $profile_type);

    // Remove the title.
    $build['active_profiles']['#pre_render'][] = [
      get_class($this),
      'activeProfilesRemoveTitle',
    ];

    // Change weight.
    $build['active_profiles']['#weight'] = 0;
    $build['add_profile']['#weight'] = 1;
    $build['form']['#weight'] = 2;

    return $build;
  }

  /**
   * Removes the view title.
   *
   * @param array $element
   *   The element.
   *
   * @return array
   *   The element.
   */
  public static function activeProfilesRemoveTitle(array $element) {
    /** @var \Drupal\views\ViewExecutable $view */
    $view = $element['view_build']['#view'];
    if (!empty($view->result)) {
      $view->display_handler->setOption('title', NULL);
      $view->setTitle('');
    }
    return $element;
  }

}
