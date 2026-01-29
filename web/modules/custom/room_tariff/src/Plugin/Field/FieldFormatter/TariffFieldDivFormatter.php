<?php

/**
 * @file
 * Contains \Drupal\room_tariff\Plugin\Field\FieldFormatter\TariffFieldDivFormatter.
 */

namespace Drupal\room_tariff\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;

/** *
 * @FieldFormatter(
 *   id = "tariff_field_div_formatter",
 *   label = @Translation("Div element tariff field default"),
 *   field_types = {
 *     "room_tariff"
 *   }
 * )
 */
class TariffFieldDivFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];

    foreach ($items as $delta => $item) {

      $value = '';
      foreach ($item->getValue() as $v) {
        if (!is_array($v) && !is_object($v)) {$value .= ' '.(string)$v;};
      };

      $element[$delta] = [
        '#type' => 'markup',
        '#markup' => 'Item ' . ($delta+1) . ' is: ' . $value,
      ];
    }

    return $element;
  }

}