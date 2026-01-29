<?php

/**
 * @file
 * Contains Drupal\payment_invoice\Plugin\Field\FieldWidget\PayButtonAvailabilityInputWidget.
 */

namespace Drupal\payment_invoice\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * @FieldWidget(
 *   id = "pay_key_button_field_default_input_widget",
 *   module = "payment_invoice",
 *   label = @Translation("Widget for custom availability payment button in reservation."),
 *   field_types = {
 *     "payment_button_availability"
 *   }
 * )
 */
class PayButtonAvailabilityInputWidget extends WidgetBase {

  /** 
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

    $field_settings = $this->getFieldSettings();
    $element += [
      '#type' => 'select',
      '#options' => $field_settings['list_availability'],
      '#default_value' => isset($items[$delta]->value) ? $items[$delta]->value/60 : '',
      '#size' => 1,
      '#element_validate' => [[$this, 'buttonAvailabilityValidation'],],
    ];

    return ['value' => $element];
  }

  /**
   * {@inheritdoc}
   */
  public function buttonAvailabilityValidation($element, FormStateInterface $form_state) {
    $value = $element['#value'];
    if (!array_key_exists($value, $this->getFieldSettings()['list_availability'])) {
      $form_state->setError($element, $this->t('The value of the field is not defined, please select the correct value'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {

    foreach ($values as &$item) {
      if (isset($item['value']) && is_numeric($item['value'])) {
        $date = $item['value'] * 60;
      } else if (isset($item['value']['object']) && is_numeric($item['value']['object'])) {
        $date = $item['value']['object'] * 60;
      } else {
        $date = NULL;
      }
      $item['value'] = $date;
    }

    return $values;
  }


}
