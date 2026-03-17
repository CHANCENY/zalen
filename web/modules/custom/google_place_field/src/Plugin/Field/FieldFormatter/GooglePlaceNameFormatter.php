<?php

namespace Drupal\google_place_field\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\google_place_field\GoogleApiCaller;
use Drupal\google_place_field\Plugin\Field\FieldType\GoogleBusinessItem;

/**
 * @FieldFormatter(
 *   id = "google_place_name",
 *   label = @Translation("Google Place Name"),
 *   field_types = {"google_place_business"}
 * )
 */
class GooglePlaceNameFormatter extends FormatterBase {

  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $googleApiCaller = new GoogleApiCaller(\Drupal::config('google_place_field.settings'));

    /**@var GoogleBusinessItem $item **/
    foreach ($items as $delta => $item) {
      $placeId = $item->getValue()['place_id'] ?? null;
      if (empty($placeId)) {
        $elements[$delta] = [
          '#markup' => "",
        ];;
      }
      else {

        // Array is in $detail
        $detail = $googleApiCaller->getPlaceDetails($placeId);
        // lets use twig template here
        $elements[$delta] = [
          '#markup' => "",
        ];
      }

    }

    return $elements;
  }

}
