<?php

namespace Drupal\google_place_field\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\google_place_field\GoogleApiCaller;

/**
 * @FieldFormatter(
 *   id = "google_place_user_rating_count",
 *   label = @Translation("User Rating Count"),
 *   field_types = {"google_place_business"}
 * )
 */
class GooglePlaceUserRatingCountFormatter extends FormatterBase {

  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $api = new GoogleApiCaller(\Drupal::config('google_place_field.settings'));

    static $cache = [];

    foreach ($items as $delta => $item) {
      $placeId = $item->place_id;

      if (!$placeId) {
        continue;
      }

      if (!isset($cache[$placeId])) {
        $cache[$placeId] = $api->getPlaceDetails($placeId);
      }

      $detail = $cache[$placeId];

      $elements[$delta] = [
        '#theme' => 'google_place_user_rating_count',
        '#detail' => $detail,
      ];
    }

    return $elements;
  }

}
