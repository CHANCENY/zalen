<?php

namespace Drupal\google_place_field\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * @FieldFormatter(
 *   id = "google_place_formatter",
 *   label = @Translation("Google Place"),
 *   field_types = {"google_place_business"}
 * )
 */
class GooglePlaceFormatter extends FormatterBase {

  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    foreach ($items as $delta => $item) {
      $elements[$delta] = [
        '#markup' => '<div><strong>' . $item->name . '</strong><br>' . $item->address . '</div>',
      ];
    }

    return $elements;
  }

}
