<?php

namespace Drupal\dcu_api\Plugin\rest\resource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides a resource for app messages.
 *
 * @RestResource(
 *   id = "app_message_rest_resource",
 *   label = @Translation("App messagge rest resource"),
 *   uri_paths = {
 *     "canonical" = "/api/appmessage"
 *   }
 * )
 */
class AppMessageRestResource extends ResourceBase {

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->logger = $container->get('logger.factory')->get('dcu_api');
    $instance->currentUser = $container->get('current_user');
    $instance->currentRequest = $container->get('request_stack')->getCurrentRequest();
    return $instance;
  }

  /**
   * Responds to GET requests.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   */
  public function get() {
    if (!$this->currentUser->hasPermission('access content')) {
      throw new AccessDeniedHttpException();
    }
    $version = $this->currentRequest->query->get('version');
    if (empty($version)) {
      $response['response']['error_message'] = 'Missing version number';
      return new ResourceResponse($response, 200);
    }
    $config = \Drupal::config('dcu_admin.sitesettings');
    $messages = [];
    if ($config->get('app_broadcast_message_active')) {
      $messages['broadcast'] =  $config->get('app_broadcast_message_content');
    }
    if ($config->get('app_equalto_message_active')) {
      if ($config->get('app_equalto_message_active') && $version == $config->get('app_equalto_message_version')) {
        $messages['equalto'] =  $config->get('app_equalto_message_content');
      }
    }
    if ($config->get('app_lessthan_message_active')) {
      if ($config->get('app_lessthan_message_active') && version_compare($version, $config->get('app_lessthan_message_version'), '<')) {
        $messages['lessthan'] =  $config->get('app_lessthan_message_content');
      }
    }

    $response = new ResourceResponse($messages, 200);
    //$response->getCacheableMetadata()->addCacheContexts(['user']);
    $cacheData = new CacheableMetadata();
    $cacheData->setCacheMaxAge(0);
    $cacheData->addCacheContexts(['user', 'url.query_args', 'url.path']);
    $response->addCacheableDependency($cacheData);
    return $response;
  }

}
