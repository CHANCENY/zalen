<?php

namespace Drupal\google_place_field\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\google_place_field\GoogleApiCaller;

/**
 * @FieldFormatter(
 *   id = "google_place_display_name",
 *   label = @Translation("Display Name"),
 *   field_types = {"google_place_business"}
 * )
 */
class GooglePlaceDisplayNameFormatter extends FormatterBase {

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
        '#theme' => 'google_place_display_name',
        '#detail' => $detail,
      ];
    }

    return $elements;
  }

}
