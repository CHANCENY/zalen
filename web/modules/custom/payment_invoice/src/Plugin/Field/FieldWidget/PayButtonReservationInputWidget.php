<?php

/**
 * @file
 * Contains Drupal\payment_invoice\Plugin\Field\FieldWidget\PayButtonReservationInputWidget.
 */

namespace Drupal\payment_invoice\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * @FieldWidget(
 *   id = "payment_button_field_default_input_widget",
 *   module = "payment_invoice",
 *   label = @Translation("Widget for custom payment button in reservation."),
 *   field_types = {
 *     "payment_button_reservation"
 *   }
 * )
 */
class PayButtonReservationInputWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

    return ['value' => $element];
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state) {

    // check if there is an availability interval in the settings.
    $data_settings = $this->getFieldSettings();
    if ($data_settings) {
      // Let's check if the field from settings exists in the parent node.
      /** @var \Drupal\reservation\Entity\Reservation $data_reservation */
      $data_reservation = $items->getEntity();
      /** @var \Drupal\node\Entity\Node $data_node */
      $data_node = $data_reservation->getReservationedEntity();
      // Checking the node is needed to know what exactly - we are making an order on the node,
      //and not in the settings set the default value.
      if ($data_node) {
        $data_node_field = $data_node->hasField($data_settings['parent_field_availability']);
        $data_node_field = $data_node_field ? $data_node->getFields()[$data_settings['parent_field_availability']]->getValue() : NULL;
      };
    };

    //Let's check the value of the interval field of the order availability button.
    if (!empty($data_node_field) && is_array($data_node_field)) {
      $data_node_field = $data_node_field[0]['value'];
      //If the value is "0" (instantly) then we need to make the payment button available.
      if (intval($data_node_field, 10) == 0) {
        $data_values = TRUE;
      } else {
        $data_values = FALSE;
      };
    };

    //Let's add a value for the payment button in order.
    // Iinterval is instantly.
    if (!empty($data_values)) {
      $field_name = $this->fieldDefinition->getName();
      // Extract the values from $form_state->getValues().
      $path = array_merge($form['#parents'], [$field_name]);
      $data_values = $form_state->getValues();
      foreach ($path as $v) {
        if (is_array($data_values) && (isset($data_values[$v]) || array_key_exists($v, $data_values))) {
          $data_values = $data_values[$v];
        } else {
          $data_values = NULL;
        };
      };
      // We will write down the current date only if it is a new reservation, and not an edited one.
      if ($data_values == NULL) {
        $data_values = (new DrupalDateTime())->getTimestamp();
        $form_state->setValue([implode(',',$path)], [['value'=>strval($data_values)]]);
      };
    };

    parent::extractFormValues($items, $form, $form_state);


  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {

    foreach ($values as &$item) {
      if (isset($item['value'])) {
        $item['value'] = is_numeric($item['value']) ? intval($item['value'], 10) : NULL;
      } else if (isset($item['value']['object'])) {
        $item['value']['object'] = is_numeric($item['value']['object']) ? intval($item['value']['object'], 10) : NULL;
      };
    };

    return $values;
  }


}
