<?php

/**
 * @file
 * Contains Drupal\room_invoice\Plugin\Field\FieldWidget\InvoiceStatusInputWidget.
 */

namespace Drupal\room_invoice\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * @FieldWidget(
 *   id = "invoice_status_input",
 *   module = "room_invoice",
 *   label = @Translation("Widget for invoice status in invoice."),
 *   field_types = {
 *     "invoice_status"
 *   }
 * )
 */
class InvoiceStatusInputWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element['date'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Invoice status timestamp'),
      '#placeholder' => $this->t('int timestamp invoice status'),
      '#default_value' => isset($items[$delta]->date) ? $items[$delta]->date : NULL,
      '#required' => TRUE,
    ];
    $element['meaning'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Invoice status'),
      '#placeholder' => $this->t('text invoice status'),
      '#default_value' => isset($items[$delta]->meaning) ? $items[$delta]->meaning : NULL,
      '#maxlength' => 32,
      '#required' => TRUE,
    ];
    return ['value' => $element];
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    foreach ($values as &$item) {
      if (isset($item['value']['meaning']) && isset($item['value']['date'])) {
        $item['meaning'] = strval($item['value']['meaning']);
        $item['date'] = intval($item['value']['date']);
        unset($item['value']);
      };
    }
    return $values;
  }


}
