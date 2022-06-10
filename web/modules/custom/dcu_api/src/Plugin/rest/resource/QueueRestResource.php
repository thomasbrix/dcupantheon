<?php

namespace Drupal\dcu_api\Plugin\rest\resource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides a resource for Advanced queue data. This is implemented to extract
 * data for statistical purposes from serialized data in payload from advanced queue.
 * Only used internaly and should only be used by administrator.
 *
 * @RestResource(
 *   id = "queue_rest_resource",
 *   label = @Translation("Advanced queue rest resource"),
 *   uri_paths = {
 *     "canonical" = "/api/getqueue"
 *   }
 * )
 */
class QueueRestResource extends ResourceBase {

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
    if (!$this->currentUser->getAccount()->hasRole('administrator')) {
      throw new AccessDeniedHttpException();
    }
    $database = \Drupal::database();
    $query = $database->select('advancedqueue', 'aq')
    ->condition('type', 'dcu_member_message_mail')
      ->fields('aq', ['payload']);
    $result = $query->execute();
    foreach ($result as $record) {
      $payload = json_decode($record->payload);
      $returnData[] = [
        'name' => $payload->name,
        'email' => $payload->email,
        'memberno' => $payload->memberno,
        'membertype' => $payload->membertype,
      ];
    }
    $response = new ResourceResponse($returnData, 200);
    $cacheData = new CacheableMetadata();
    $cacheData->setCacheMaxAge(0);
    $cacheData->addCacheContexts(['user', 'url.query_args', 'url.path']);
    $response->addCacheableDependency($cacheData);
    return $response;
  }
}
