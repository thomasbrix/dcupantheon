<?php

namespace Drupal\dcu_utility\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\dcu_utility\Plugin\Controller\GooglePlaceApiClient;
use Drupal\node\NodeInterface;

/**
 * Provides a 'GooglePlaceLatestReviews' block.
 * Fetches and shows reviews for a placeid on entity for current route
 * or from id from block configuration.
 *
 * @Block(
 *  id = "google_place_latest_reviews",
 *  admin_label = @Translation("Google place latest reviews"),
 * )
 */
class GooglePlaceLatestReviews extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['google_place_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Google Place Id'),
      '#default_value' => $this->configuration['google_place_id'],
      '#maxlength' => 255,
      '#size' => 150,
      '#weight' => '0',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['google_place_id'] = $form_state->getValue('google_place_id');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    // block is prepared to be used as standalone block with configurable placeid.
    // In that case, use $this->configuration['google_place_id'] instead of campsite placeid.
    $build = [];
    $build['#theme'] = 'google_place_latest_reviews';
    $build['#content']['googledata'] = FALSE;
    $node = \Drupal::routeMatch()->getParameter('node');
    if (!$node instanceof NodeInterface) {
      return $build;
    }
    $googlePlaceId = $node->get('field_google_place_id')->getString();
    $params = [
      'fields' => 'reviews',
      'language' => 'da',
    ];
    $googleClient = new GooglePlaceApiClient();
    $reviewData = $googleClient->getGooglePlaceDetails($googlePlaceId, $params);
    if ($reviewData) {
      $build['#content']['googledata'] = $reviewData;
    }
    return $build;
  }
}
