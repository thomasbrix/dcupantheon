<?php

namespace Drupal\dcu_api\Plugin\rest\resource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\image\Entity\ImageStyle;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides a resource to list nodes of type campsites
 *
 * @RestResource(
 *   id = "campsites_rest_resource",
 *   label = @Translation("List Campsites rest resource"),
 *   uri_paths = {
 *     "canonical" = "/api/getcampsites"
 *   }
 * )
 */
class CampsitesResource extends ResourceBase {

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

    ini_set('memory_limit', '1048M');
    ini_set('max_execution_time', 600);

    $date = $this->currentRequest->query->get('date');
    $membershiptype = $this->currentRequest->query->get('membershiptype');
    $applanguage = $this->currentRequest->query->get('language');
    $host = \Drupal::request()->getSchemeAndHttpHost();

    $languageManager = \Drupal::service('language_manager');
    if (!empty($applanguage) && $applanguage == 'en') {
      $prefLanguage = $languageManager->getLanguage('en');
      $originalLanguage = $languageManager->getConfigOverrideLanguage();
      $languageManager->setConfigOverrideLanguage($prefLanguage);
    }
    else {
      $prefLanguage = $languageManager->getLanguage('da');
    }
    $entity = \Drupal::entityTypeManager()->getStorage('node');
    $query = $entity->getQuery();
    $query->condition('status', 1);


    if (isset($membershiptype) && strtolower($membershiptype) == 'deal') {
      $bundleTypes= ['dcu_campsite'];
    }
    else {
      $bundleTypes = ['dcu_campsite', 'campsites'];
      $query->condition('field_advantage_campsite', 1, '=');
    }
    $query->condition('type', $bundleTypes, 'in');
    if (!empty($date) && strtotime($date)) {
      $query->condition('changed', strtotime($date), '>');
    }
    if (!empty($applanguage)) {
      $query->condition('langcode', $prefLanguage->getId());
    }

    $query->sort('changed', 'DESC');
    $ids = $query->execute();
    $campsiteNodes = $entity->loadMultiple($ids);
    $campsites = [];
    foreach ($campsiteNodes as $node) {
      if ($prefLanguage->getId() != $node->language()->getId()) {
        // Only serve campsites in prefered language (da or en)
        continue;
      }
      $camp = [];
      $camp['node_id'] = $node->id();
      $camp['title'] = $node->getTitle();
      $camp['updated_date'] = date('Y-m-d', $node->getChangedTime());
      $camp['category'] = $node->bundle() == 'dcu_campsite' ? 'dcu_campsites' : $node->bundle();
      $address = $node->get('field_address')->first();
      $camp['address'] = [
        'address_1' => $address->address_line1,
        'address_2' => $address->address_line2,
        'postal_code' => $address->postal_code,
        'city' => $address->locality,
        'country' => $address->country_code,
      ];
      $camp['phone'] = !$node->get('field_phone')->isEmpty() ? $node->get('field_phone')->first()->getString() : NULL;
      $camp['email'] = !$node->get('field_email')->isEmpty() ? $node->get('field_email')->first()->getString() : NULL;
      $website = $node->get('field_www')->first();
      $camp['website_title'] = !empty($website) ? $website->title : NULL;
      $camp['website_url'] = !empty($website) ? $website->uri : NULL;
      $camp['rating'] = !empty($node->get('field_number_of_stars')->first()) ? $node->get('field_number_of_stars')->first()->value : NULL;
      $geoLocation = $node->get('field_geo_location')->first();
      $camp['latitude'] = !empty($geoLocation->lat) ? $geoLocation->lat : NULL;
      $camp['longitude'] = !empty($geoLocation->lng) ? $geoLocation->lng : NULL;
      $desc = $node->get('field_description')->first();
      $camp['brief_description'] = !empty($desc) ? strip_tags($desc->value) : NULL;
      $regionTerm = $node->get('field_country_region')->entity;
      $camp['country_region'] = !empty($regionTerm) ? $regionTerm->getName() : NULL;

      $camp['season_period_from'] =  $node->hasField('field_season_period') ? $node->get('field_season_period')->value : NULL;
      $camp['season_period_to'] =  $node->hasField('field_season_period') ? $node->get('field_season_period')->end_value : '';
      $camp['children_from'] =  $node->hasField('field_children_age_from') ? $node->get('field_children_age_from')->getString() : NULL;
      $camp['children_to'] =  $node->hasField('field_children_age_to') ? $node->get('field_children_age_to')->getString() : NULL;
      $camp['youngsters_from'] = $node->hasField('field_youngster_age_from') ? $node->get('field_youngster_age_from')->getString() : NULL;
      $camp['youngsters_to'] =  $node->hasField('field_youngster_age_to') ? $node->get('field_youngster_age_to')->getString() : NULL;
      $camp['discount'] = $node->hasField('field_discount_description') ? [$node->get('field_discount_description')->getString()] : NULL;

      $camp['price'] = $this->getCampsitePrices($node, $prefLanguage->getId());

      $camp['credit_card'] = NULL;
      if ($node->hasField('field_acceptable_card_types')) {
        $creditcards = $node->get('field_acceptable_card_types');
        if (!empty($creditcards)) {
          foreach ($creditcards as $creditcard) {
            $camp['credit_card'][] = [
              'name' => $creditcard->getValue(),
            ];
          }
        }
      }
      $camp['facilities'] = NULL;
      $facilities = $node->hasField('field_facilities') ? $node->get('field_facilities') : NULL;
      if (!empty($facilities)) {
        foreach ($facilities as $facility) {
          $facilityTerm = $facility->entity;
          // Retrieve the translated taxonomy term in specified language with fallback to default language if translation not exists.
          $facilityTermTrans = \Drupal::service('entity.repository')->getTranslationFromContext($facilityTerm, $prefLanguage->getId());
          $camp['facilities'][] = [
            'name' => $facilityTermTrans->getName(),
            'icon' => $facilityTermTrans->get('field_icon')->getString(),
            'font' => $host . '/themes/custom/dcu/assets/fonts/camplogo.woff',
          ];
        }
      }
      $imageField = $node->bundle() == 'dcu_campsite' ? 'field_media_image' : 'field_image';
      $images = dcu_api_get_node_media_url($node, $imageField, ['mobile_api_thumbnail', '16_9_api']);
      $camp['top_image'] = !empty($images['16_9_api']) ? $images['16_9_api'] : NULL;
      $camp['top_image_thumbnail'] = !empty($images['mobile_api_thumbnail']) ? $images['mobile_api_thumbnail'] : NULL;
      $camp['show_website_url'] = 1;
      $camp['fax'] = NULL;
      $campsites[] = $camp;
    }
    if ($prefLanguage->getId() != 'da') {
      $languageManager->setConfigOverrideLanguage($originalLanguage);
    }
    $response = new ResourceResponse($campsites, 200);
    $cacheData = new CacheableMetadata();
    $cacheData->setCacheContexts(['url']);
    $response->addCacheableDependency($cacheData);
    return $response;
  }


  protected function getCampsitePrices($node, $lang = 'da') {
    if (empty($node) || !($node->bundle() == 'campsites' || $node->bundle() == 'dcu_campsite')) {
      return [];
    }
    $campPrices = [];
    $priceFields = array(
      'field_price_adult',
      'field_price_car',
      'field_price_caravan',
      'field_price_child',
      'field_price_dog',
      'field_price_horse',
      'field_price_motorcaravan',
      'field_price_motorcycle',
      'field_price_power_outlet',
      'field_price_tent',
      'field_price_young',
      'field_price_pitch_fee',
      'field_price_environmental_fee',
    );
    foreach ($priceFields as $fieldName){
      if (!$node->hasField($fieldName) || empty($node->get($fieldName)->getString())) {
        continue;
      }
      $campPrices[] = [
        "price_type" => t($node->getFieldDefinition($fieldName)->label(), [], ['langcode' => $lang]),
        "price" => $node->get($fieldName)->getString(),
        "extra" => NULL,
        "description" => NULL,
      ];
    }
    $fieldPriceCurrencyTerm = $node->hasField('field_price_currency') ? $node->get('field_price_currency')->entity : "";
    $fieldPriceCurrency = !empty($fieldPriceCurrencyTerm) ? $fieldPriceCurrencyTerm->getName() : "";
    $fieldPriceComments = $node->hasField('field_price_comments') ? $node->get('field_price_comments')->getString() : "";
    $fieldPriceListYear = $node->hasField('field_price_list_year') ? $node->get('field_price_list_year')->getString() : "";
    $prices = [
      'year' => $fieldPriceListYear,
      'currency' => $fieldPriceCurrency,
      'comments' => $fieldPriceComments,
      'prices' => $campPrices
    ];
    return $prices;
  }
}
