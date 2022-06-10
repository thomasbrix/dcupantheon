<?php

namespace Drupal\dcu_utility\Plugin\Controller;

use Drupal\Core\Site\Settings;

class GooglePlaceApiClient {
  var $apiKey;
  var $apiDetailsUrl;
  var $apiPlaceUrl;

  function __construct() {
    $this->apiKey = Settings::get('dcu_google_api', 'AIzaSyArP7FJnpPdPiNg22J6jCZPCEMVOWPfpa8');
    $this->apiDetailsUrl = "https://maps.googleapis.com/maps/api/place/details/json";
    $this->apiPlaceUrl = "https://maps.googleapis.com/maps/api/place/findplacefromtext/json";
  }

  /**
   * Fetch details data for a placeId from google place api
   *
   * @param $placeId
   *  The Google place id to request details from
   *
   * @return false
   */
  public function getGooglePlaceDetails($placeId, $params = []) {
    $apiUrl = $this->getGooglePlaceDetailsUrl($placeId, $params);
    $client = \Drupal::httpClient();
    $response = $client->get($apiUrl);
    $code = $response->getStatusCode();
    if ($code == 200) {
      $response_data = json_decode($response->getBody()->getContents());
      if (($response_data->status == 'OK')){
        return $response_data->result;
      }
    }
    return FALSE;
  }

  /**
   * Generates url to Google place details api
   *
   * @param $place_id
   *  The Google place id
   * @param array $params
   *  Optional parameters to send as query params to api.
   *  Will be merged with default required params.
   *
   * @return string
   */
  public function getGooglePlaceDetailsUrl($placeId, $params = []) {
    $parameters = [
      'place_id' => $placeId,
      'fields' => 'rating,user_ratings_total,price_level,reviews',
      'key' => $this->apiKey,
    ];
    $parameters = array_merge($parameters, $params);
    return $this->apiDetailsUrl . '?' . http_build_query($parameters);
  }




  // TODO: tbx not implemented yet. Should replace api call in: dcu_utility_google_placeid
  /**
   * @param $search_url
   * @return string
   */
  public function fetchGooglePlaceIdApiData($search_url) {
    $client = \Drupal::httpClient();
    $response = $client->get($search_url);
    $code = $response->getStatusCode();
    $result = 'ERROR';
    if ($code == 200) {
      $response_data = json_decode($response->getBody()->getContents());
      $result = $response_data->status;
      if (!empty($response_data->candidates)){
        $result = $response_data->candidates[0]->place_id;
      }
    }
    return $result;
  }


}
