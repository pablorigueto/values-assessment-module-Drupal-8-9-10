<?php

namespace Drupal\va_custom\EventSubscriber;

use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Redirects to langcode if the path didn't have the / at the end.
 */
class RedirectSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['onRequest'];
    return $events;
  }

  /**
   * Limit this solution only to homepage.
   */
  public function onRequest(RequestEvent $event) {
    $path = $event->getRequest()->getPathInfo();

    if ($path === '/') {
      return;
    }

    $path_parts = explode('/', $path);
    if (isset($path_parts[2])) {
      return;
    }

    if (!str_ends_with($path, '/') && strpos($path_parts[1], '-') !== false) {
      $response = new TrustedRedirectResponse($path.'/');
      $event->setResponse($response);
      $event->stopPropagation();
 
    }

  }

}
