<?php

namespace Drupal\google_place_field\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\google_place_field\GoogleApiCaller;
use Drupal\node\Entity\Node;

/**
 * Provides a Google Place Rating Block.
 *
 * @Block(
 *   id = "google_place_rating_block",
 *   admin_label = @Translation("Google Place Rating"),
 * )
 */
class GooglePlaceRatingBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $placeId = $this->determineEntityGooglePlaceId();
    $googleApiCaller = new GoogleApiCaller(\Drupal::config('google_place_field.settings'));
    $placeDetails = $googleApiCaller->getPlaceDetails($placeId);
    return [
      '#theme' => 'google_place_rating',
      '#rating' => $placeDetails,
      '#attached' => [
        'library' => [
          'google_place_field/google_place_rating',
        ],
      ],
    ];
  }

  private function determineEntityGooglePlaceId() {
    $node = \Drupal::routeMatch()->getParameter('node');
    $googlePlaceId = "";
    if ($node instanceof Node) {

      if ($node->bundle() === 'zaal') {
        $location = $node->get('field_bedrijf_zaal')->entity;
        if ($location instanceof Node && $location->hasField('field_google_place_id')) {
          $googlePlaceId = $location->get('field_google_place_id')->place_id;
        }
      }
      elseif ($node->bundle() === 'bedrijf' && $node->hasField('field_google_place_id')) {
        $googlePlaceId = $node->get('field_google_place_id')->place_id;
      }

    }
    return $googlePlaceId;
  }

}
