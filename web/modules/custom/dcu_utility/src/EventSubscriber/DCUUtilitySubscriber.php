<?php

namespace Drupal\dcu_utility\EventSubscriber;

use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\user\Entity\User;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;

//Inspiration: https://drupal.stackexchange.com/questions/272288/how-to-redirect-user-page-to-front-page-for-specific-roles

class DCUUtilitySubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public function checkForRedirection(GetResponseEvent $event) {
    $currentRequestPath = \Drupal::service('path.current')->getPath();
    $userId = \Drupal::currentUser()->id();
    $userPathsRedirect = [
      '/user',
      '/user/' . $userId,
      '/user/' . $userId . '/edit',
    ];
    if (in_array($currentRequestPath, $userPathsRedirect)) {
      $user = User::load(\Drupal::currentUser()->id());
      if ($user->hasRole('icamp')) {
        if (!$user->hasRole('dcu_campsite_owner') &&
          !$user->hasRole('other_campsite_owner') &&
          !$user->hasRole('agent') &&
          !$user->hasRole('campsite_editor') &&
          !$user->hasRole('benefit_owner') &&
          $user->id() !== '1') {
          $redirectUrl = Url::fromRoute('dcu_member.user_profile', ['userId' => $user->id()]);
          $event->setResponse(new RedirectResponse($redirectUrl->toString(), 301));
        }
      }
    }
  }

  /**
   * @param FilterResponseEvent $event
   * if content type = test_centers - allow for it to be shown in iframe.
   */
  public function removeXFrameOptions(FilterResponseEvent $event) {
    $attr = $event->getRequest()->attributes;
    if (!empty($attr) && is_object($attr->get('node'))) {
      if ($attr->get('node')->get('type')->getString() == 'test_centers') {
        $response = $event->getResponse();
        $response->headers->remove('X-Frame-Options');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = array('checkForRedirection');
    $events[KernelEvents::RESPONSE][] = array('removeXFrameOptions', -10);
    return $events;
  }
}
