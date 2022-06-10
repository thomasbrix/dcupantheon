<?php

namespace Drupal\dcu_api\Plugin\rest\resource;

use Drupal\image\Entity\ImageStyle;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides a resource to list nodes of type member_benefit
 *
 * @RestResource(
 *   id = "benefits_rest_resource",
 *   label = @Translation("List benefits rest resource"),
 *   uri_paths = {
 *     "canonical" = "/api/getbenefits"
 *   }
 * )
 */
class BenefitsResource extends ResourceBase {

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
    $date = $this->currentRequest->query->get('date');
    $entity = \Drupal::entityTypeManager()->getStorage('node');
    $query = $entity->getQuery();
    $query->condition('status', 1);
    $query->condition('type', 'member_benefit');
    if (!empty($date) && strtotime($date)) {
      $query->condition('changed', strtotime($date), '>');
    }
    $query->sort('changed', 'DESC');
    $ids = $query->execute();
    $benefitNodes = $entity->loadMultiple($ids);
    $benefits = [];
    foreach ($benefitNodes as $node) {
      $benefit['node_id'] = $node->id();
      $benefit['title'] = $node->getTitle();
      $benefit['updated_date'] = date('Y-m-d', $node->getChangedTime());
      $benefit['benefit_price'] = $node->get('field_benefit_price')->getString();
      $benefit['dcu_recommend'] = !empty($node->get('field_dcu_recommend')->getString());
      $benefit['label'] = $node->get('field_trumpet')->getString();
      $benefit['body'] = '';
      $body = $node->get('field_body')->first();
      if (!empty($body)) {
        $benefit['body'] = $body->get('value')->getString();
      }
      $benefit['logged_off_content'] = '';
      $locked_body = $node->get('field_body_locked_content')->first();
      if (!empty($locked_body)) {
        $benefit['logged_off_content'] = $locked_body->get('value')->getString();
      }
      $geoLocation = $node->get('field_geo_location')->getValue();
      $geoLocation = reset($geoLocation);
      $benefit['latitude'] = !empty($geoLocation['lat']) ? $geoLocation['lat'] : NULL;
      $benefit['longitude'] = !empty($geoLocation['lng']) ? $geoLocation['lng'] : NULL;
      $images = dcu_api_get_node_media_url($node,'field_media_image', ['mobile_api_thumbnail', '16_9_api']);
      $benefit['top_image'] = !empty($images['16_9_api']) ? $images['16_9_api'] : NULL;
      $benefit['top_image_thumbnail'] = $images['mobile_api_thumbnail'];
      $benefitTypeTerm = $node->get('field_benefit_type')->entity;
      $benefit['benefit_type'] = !empty($benefitTypeTerm) ? $benefitTypeTerm->getName() : NULL;
      $benefits[] = $benefit;
    }
    return new ResourceResponse($benefits, 200);
  }
}
