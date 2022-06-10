<?php

namespace Drupal\dcu_api\Plugin\rest\resource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides a resource to get users favorites
 *
 * @RestResource(
 *   id = "user_favorites_rest_resource",
 *   label = @Translation("User favorites rest resource"),
 *   uri_paths = {
 *     "canonical" = "/api/getuserfavorites"
 *   }
 * )
 */
class UserFavoritesRestResource extends ResourceBase {

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
    $database = \Drupal::database();
    $query = $database->query("SELECT entity_id FROM {flagging} WHERE flag_id = :flag and uid = :uid", [
      ':flag' => 'favorite',
      ':uid' => $this->currentUser->id()
    ]);
    $result = $query->fetchAll();
    $favorites = [];
    if (!empty($result)){
      foreach ($result as $favorite) {
        $favorites[] = $favorite->entity_id;
      }
    }
    $response = new ResourceResponse(['favorites' => [$favorites]], 200);
    $cacheData = new CacheableMetadata();
    $cacheData->setCacheMaxAge(0);
    $cacheData->addCacheContexts(['user', 'url.query_args', 'url.path']);
    $response->addCacheableDependency($cacheData);
    return $response;
  }

}
