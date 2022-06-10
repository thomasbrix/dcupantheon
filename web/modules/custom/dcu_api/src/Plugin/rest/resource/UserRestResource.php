<?php

namespace Drupal\dcu_api\Plugin\rest\resource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\dcu_navision\Client\NavisionRestClient;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides a resource for user data.
 *
 * @RestResource(
 *   id = "user_rest_resource",
 *   label = @Translation("User rest resource"),
 *   uri_paths = {
 *     "canonical" = "/api/getuser"
 *   }
 * )
 */
class UserRestResource extends ResourceBase {

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * @var NavisionRestClient $navisionClient
   */
  protected $navisionClient;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->logger = $container->get('logger.factory')->get('dcu_api');
    $instance->currentUser = $container->get('current_user');
    $instance->navisionClient = $container->get('dcu_navision.client');
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
    $user = $this->currentUser->getAccount();
    $memberId = $user->get('field_memberid')->getString();
    $memberData = $this->navisionClient->getMemberData($memberId);
    if (empty($memberData)) {
      new ResourceResponse([], 200);
    }
    $memberCardNumber = dcu_member_get_card_id($user);
    $returnData = [
      'member_id' => $memberCardNumber,
      'first_name' => $memberData->firstname,
      'last_name' => $memberData->lastname,
      'active_paying_member' => $memberData->memberstatus === 'AKTIV',
      'email' => $memberData->email,
      'membership_type' => $memberData->membertype,
      'address' => [
        'street' => $memberData->address,
        'house_number' => '',
        'house_letter' => '',
        'floor' => '',
        'suite' => '',
        'area' => '',
        'postal_code' => $memberData->postalcode,
        'city' => $memberData->city,
        'country' => $memberData->country,
      ],
    ];
    $response = new ResourceResponse($returnData, 200);
    //$response->getCacheableMetadata()->addCacheContexts(['user']);
    $cacheData = new CacheableMetadata();
    $cacheData->setCacheMaxAge(0);
    $cacheData->addCacheContexts(['user', 'url.query_args', 'url.path']);
    $response->addCacheableDependency($cacheData);
    return $response;
  }

}
