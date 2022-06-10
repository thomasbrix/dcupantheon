<?php
/**
 * @file
 * Contains \Drupal\dcu_utility\Plugin\QueueWorker\GooglePlaceImportQueueWorker.
 */

namespace Drupal\dcu_utility\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\dcu_utility\Plugin\Controller\GooglePlaceApiClient;
use Drupal\node\Entity\Node;

/**
 * Processes google place data import for campsites.
 * Queue is filled by dcu_utility cron.
 *
 * @QueueWorker(
 *   id = "dcu_utility_google_place_import",
 *   title = @Translation("DCU Utility: Google place data import Queue worker"),
 *   cron = {"time" = 120}
 * )
 */
class GooglePlaceImportQueueWorker extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($item) {
    $nid = $item->nid;
    $campsite = Node::load($nid);
    $googlePlaceId = $campsite->get('field_google_place_id')->getString();
    if (empty($googlePlaceId)) {
      return;
    }
    $googleClient = new GooglePlaceApiClient();
    if (!$googlePlaceData = $googleClient->getGooglePlaceDetails($googlePlaceId)) {
      return;
    }
    if (!empty($googlePlaceData->rating) && is_numeric($googlePlaceData->rating)) {
      $campsite->set('field_google_rating', $googlePlaceData->rating);
    }
    if (!empty($googlePlaceData->user_ratings_total) && is_numeric($googlePlaceData->user_ratings_total)) {
      $campsite->set('field_google_ratings_total', $googlePlaceData->user_ratings_total);
    }
    if (!empty($googlePlaceData->price_level) && is_numeric($googlePlaceData->price_level)) {
      $campsite->set('field_google_price_level', $googlePlaceData->price_level);
    }
    $campsite->save();
  }
}
