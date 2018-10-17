<?php

namespace Drupal\cpl_commerce_user\EventSubscriber;

use Drupal\Core\EventSubscriber\HttpExceptionSubscriberBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;

/**
 * Exception subscriber for handling core default HTML error pages.
 */
class ErrorPagesSubscriber extends HttpExceptionSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function getHandledFormats() {
    return ['html'];
  }

  /**
   * {@inheritdoc}
   */
  public function on403(GetResponseForExceptionEvent $event) {
    $request = $event->getRequest();
    $route = $request->get('_route');

    switch ($route) {
      case 'entity.user.edit_form':
        // Redirects from "/user/{user}/edit" page to "/user/{user}/account".
        $user = $request->get('user');
        if ($user instanceof AccountInterface) {
          $url = Url::fromRoute('cpl_commerce_user.account', ['user' => $user->id()]);
          if ($password_reset_token = $request->query->get('pass-reset-token')) {
            $url->setOption('query', ['pass-reset-token' => $password_reset_token]);
          }
          $event->setResponse(new RedirectResponse($url->toString(), 303));
        }
        break;
    }
  }

}
