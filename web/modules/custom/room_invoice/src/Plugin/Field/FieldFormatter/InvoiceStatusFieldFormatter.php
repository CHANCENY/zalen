<?php

/**
 * @file
 * Contains \Drupal\room_invoice\Plugin\Field\FieldFormatter\InvoiceStatusFieldFormatter.
 */

namespace Drupal\room_invoice\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Datetime\DrupalDateTime;

/** *
 * @FieldFormatter(
 *   id = "invoice_status_formatter",
 *   label = @Translation("Formatter for invoice status in invoice."),
 *   field_types = {
 *     "invoice_status"
 *   }
 * )
 */
class InvoiceStatusFieldFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {

    $element = [];

    foreach ($items as $delta => $item) {
      $element[$delta] = [
        '#type' => 'markup',
        '#markup' => $this->t('Meaning: @status. Date: @time', [
          '@status' => $item->meaning, 
          '@time' => DrupalDateTime::createFromTimestamp($item->date)->format('Y-m-d H:i:s'),
        ]),
      ];
    }

    return $element;
  }

}
