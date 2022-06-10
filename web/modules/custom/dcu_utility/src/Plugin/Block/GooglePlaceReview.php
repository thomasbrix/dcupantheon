<?php

namespace Drupal\dcu_utility\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\dcu_utility\Plugin\Controller\GooglePlaceApiClient;
use Drupal\node\NodeInterface;

/**
 * Provides a 'GooglePlaceReview' block.
 *
 * @Block(
 *  id = "google_place_review",
 *  admin_label = @Translation("Google place review"),
 * )
 */
class GooglePlaceReview extends BlockBase {

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
    $build['#theme'] = 'google_place_review';
    $build['#content']['stars'] = FALSE;
    $node = \Drupal::routeMatch()->getParameter('node');
    if (!$node instanceof NodeInterface) {
      return $build;
    }
    $googlePlaceId = $node->get('field_google_place_id')->getString();
    if ($reviewData = $this->getGooglePlaceReview($googlePlaceId)) {
      $build['#content']['stars'] = $reviewData;
      $build['#content']['googleplaceid'] = $googlePlaceId;
    }
    return $build;
  }

  private function getGooglePlaceReview($placeId) {
    $googleClient = new GooglePlaceApiClient();
    return $googleClient->getGooglePlaceDetails($placeId);
  }

}
