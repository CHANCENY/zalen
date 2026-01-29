<?php

/**
 * @file
 * Contains \Drupal\payment_invoice\Plugin\Field\FieldFormatter\PayButtonAvailabilityFieldFormatter.
 */

namespace Drupal\payment_invoice\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * @FieldFormatter(
 *   id = "pay_key_button_field_default_formatter",
 *   label = @Translation("Formatter for custom availability payment button in reservation."),
 *   module = "payment_invoice",
 *   field_types = {
 *     "payment_button_availability"
 *   }
 * )
 */
class PayButtonAvailabilityFieldFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {

    $element = [];

    return $element;
  }

}
