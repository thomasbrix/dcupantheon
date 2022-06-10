<?php

namespace Drupal\dcu_api\Plugin\rest\resource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides a resource to add user favorite
 *
 * @RestResource(
 *   id = "add_user_favorite_rest_resource",
 *   label = @Translation("Add user favorite rest resource"),
 *   uri_paths = {
 *     "canonical" = "/api/adduserfavorite"
 *   }
 * )
 */
class AddUserFavoriteRestResource extends ResourceBase {

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;

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
    $nid = $this->currentRequest->query->get('nid');
    if (empty($nid) || !is_numeric($nid)) {
      $response['response']['error_message'] = 'Missing nid';
      return new ResourceResponse($response, 200);
    }
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
    if (empty($node)) {
      $response['response']['error_message'] = 'Non existing node';
      return new ResourceResponse($response, 200);
    }
    $flag_service = \Drupal::service('flag');
    $flag = $flag_service->getFlagById('favorite');
    $flagging = $flag_service->getFlagging($flag, $node, $this->currentUser);
    if (!$flagging) {
      $flag_service->flag($flag, $node, $this->currentUser);
    }
    $responseData['response']['success'] = 'Favorite added to user';
    $response = new ResourceResponse($responseData, 200);
    $cacheData = new CacheableMetadata();
    $cacheData->setCacheMaxAge(0);
    $cacheData->addCacheContexts(['user', 'url.query_args', 'url.path']);
    $response->addCacheableDependency($cacheData);
    return $response;
  }
}
