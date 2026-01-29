<?php

namespace Drupal\room_custom_setting;

/**
 * @file
 * In this file we use widget hooks to extend their functionality.
 *
 * We use drupal hooks and add remove button for fields remove.
 */

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Language\Language;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Render\Element;

class RemoveButton {

  //
  // button delete in multi-value fields
  //

  public static function  create_delete_button(&$element, &$form_state, $context) {

  //get a field widget
  $items = $context['items'];
  $fieldDefinition = $items->getFieldDefinition();
  $storage = $fieldDefinition->getFieldStorageDefinition();
  $type = $storage->getType();
  //shows -1 multifield 0 for single-digit fields, +(some digit) how many fields
  $cardinality = $storage->getCardinality();

  if ($cardinality == 1) {
    return;
  };
  $is_cardinality_unlimited = $cardinality == FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED;

  $field_parents = isset($element['#field_parents']) ? $element['#field_parents'] : [];

  $field_name = $fieldDefinition->getName();

  $language = isset($element['#language']) ? $element['#language'] : Language::LANGCODE_NOT_SPECIFIED;

  //$delta digits 0 1... = array of elements
  $delta = $element['#delta'];

  // Get parent which will we use into Remove Button Element.
  $parents = array_merge($field_parents, [
    $field_name,
    $language,
    $delta,
  ]);
  $remove_btn_name = implode('_', $parents) . '_remove_button';

  $callback = $is_cardinality_unlimited
  ? 'Drupal\room_custom_setting\RemoveButton::multiple_fields_remove_button_js'
  : 'Drupal\room_custom_setting\RemoveButton::multiple_fields_remove_button_clear_value_js';

  $element['remove_button'] = [
    '#delta' => $delta,
    '#name' => $remove_btn_name,
    '#type' => 'submit',
    '#value' => t('Remove'),
    '#validate' => [],
    '#attributes' => [
      'class' => [
        'multiple-fields-remove-button',
      ],
    ],
    '#submit' => ['Drupal\room_custom_setting\RemoveButton::multiple_fields_remove_button_fixed_submit_handler'],
    '#limit_validation_errors' => [],
    '#ajax' => [
      'callback' => $callback,
    ],
    '#weight' => 1000,
  ];
  if ($is_cardinality_unlimited) {
    $element['remove_button']['#submit'] = ['Drupal\room_custom_setting\RemoveButton::multiple_fields_remove_button_submit_handler'];
    $element['remove_button']['#ajax']['effect'] = 'fade';
  };
}


/**
 * Submit callback to clear field values for fixed cardinality fields.
 *
 * @param array $form
 *   The complete form structure.
 * @param FormStateInterface $form_state
 *   The current state of the form.
 */
public static function multiple_fields_remove_button_fixed_submit_handler(array $form, FormStateInterface $form_state) {
  $formValues = $form_state->getValues();
  $formInputs = $form_state->getUserInput();
  $button = $form_state->getTriggeringElement();
  $delta = $button['#delta'];
  // Where in the form we'll find the parent element.
  $address = array_slice($button['#array_parents'], 0, -2);

  // Go one level up in the form, to the widgets container.
  $parent_element = NestedArray::getValue($form, $address);
  $field_name = $parent_element['#field_name'];
  $parents = $parent_element['#field_parents'];
  $field_state = WidgetBase::getWidgetState($parents, $field_name, $form_state);

  // Shift up the following values.
  $cardinality = $parent_element['#cardinality'];
  $i = $delta;
  while ($i < $cardinality - 1) {
    $old_element_address = array_merge($address, [$i + 1]);
    $new_element_address = array_merge($address, [$i]);

    $moving_element = NestedArray::getValue($form, $old_element_address);
    $keys = array_keys($old_element_address, 'widget', TRUE);
    foreach ($keys as $key) {
      unset($old_element_address[$key]);
    }
    $moving_element_value = NestedArray::getValue($formValues, $old_element_address);
    $moving_element_input = NestedArray::getValue($formInputs, $old_element_address);

    $keys = array_keys($new_element_address, 'widget', TRUE);
    foreach ($keys as $key) {
      unset($new_element_address[$key]);
    }
    // Tell the element where it's being moved to.
    $moving_element['#parents'] = $new_element_address;

    // Move the element around.
    NestedArray::setValue($formValues, $moving_element['#parents'], $moving_element_value, TRUE);
    NestedArray::setValue($formInputs, $moving_element['#parents'], $moving_element_input);

    // Save new element values.
    foreach ($formValues as $key => $value) {
      $form_state->setValue($key, $value);
    }
    $form_state->setUserInput($formInputs);
    $i++;
  }

  // Set the last value to be blank.
  $old_element_address = array_merge($address, [$cardinality - 1]);
  $moving_element = NestedArray::getValue($form, $old_element_address);
  NestedArray::setValue($formInputs, $moving_element['#parents'], [
    'target_id' => '',
    '_weight' => $cardinality - 1,
  ]);

  // Re-set the weights.

  // Fix the weights. Field UI lets the weights be in a range of
  // (-1 * item_count) to (item_count). This means that when we remove one,
  // the range shrinks; weights outside of that range then get set to
  // the first item in the select by the browser, floating them to the top.
  // We use a brute force method because we lost weights on both ends
  // and if the user has moved things around, we have to cascade because
  // if I have items weight weights 3 and 4, and I change 4 to 3 but leave
  // the 3, the order of the two 3s now is undefined and may not match what
  // the user had selected.
  $address = array_slice($button['#array_parents'], 0, -2);
  $keys = array_keys($address, 'widget', TRUE);
  foreach ($keys as $key) {
    unset($address[$key]);
  }
  $input = NestedArray::getValue($formInputs, $address);

  if ($input && is_array($input)) {
    // Sort by weight.
    uasort($input, '_field_multiple_value_form_sort_helper');

    // Reweight everything in the correct order.
    $weight = -1 * $field_state['items_count'];
    foreach ($input as $key => $item) {
      if ($item) {
        $input[$key]['_weight'] = $weight++;
      }
    }
    NestedArray::setValue($formInputs, $address, $input);
    $form_state->setUserInput($formInputs);
  }

  $form_state->setRebuild();
}
/**
 * Submit callback to remove an item from the field UI multiple wrapper.
 *
 * When a remove button is submitted, we need to find the item that it
 * referenced and delete it. Since field UI has the deltas as a straight
 * unbroken array key, we have to renumber everything down. Since we do this
 * we *also* need to move all the deltas around in the $form_state['values']
 * and $form_state['input'] so that user changed values follow. This is a bit
 * of a complicated process.
 *
 * @param array $form
 *   The complete form structure.
 * @param FormStateInterface $form_state
 *   The current state of the form.
 */
public static function multiple_fields_remove_button_submit_handler(array $form, FormStateInterface $form_state) {
  $formValues = $form_state->getValues();
  $formInputs = $form_state->getUserInput();
  $button = $form_state->getTriggeringElement();
  $delta = $button['#delta'];
  // Where in the form we'll find the parent element.
  $address = array_slice($button['#array_parents'], 0, -2);

  // Go one level up in the form, to the widgets container.
  $parent_element = NestedArray::getValue($form, $address);
  $field_name = $parent_element['#field_name'];
  $parents = $parent_element['#field_parents'];
  $field_state = WidgetBase::getWidgetState($parents, $field_name, $form_state);

  // Go ahead and renumber everything from our delta to the last
  // item down one. This will overwrite the item being removed.
  for ($i = $delta; $i <= $field_state['items_count']; $i++) {
    $old_element_address = array_merge($address, [$i + 1]);
    $new_element_address = array_merge($address, [$i]);

    $moving_element = NestedArray::getValue($form, $old_element_address);
    $keys = array_keys($old_element_address, 'widget', TRUE);
    foreach ($keys as $key) {
      unset($old_element_address[$key]);
    }
    $moving_element_value = NestedArray::getValue($formValues, $old_element_address);
    $moving_element_input = NestedArray::getValue($formInputs, $old_element_address);

    $keys = array_keys($new_element_address, 'widget', TRUE);
    foreach ($keys as $key) {
      unset($new_element_address[$key]);
    }
    // Tell the element where it's being moved to.
    $moving_element['#parents'] = $new_element_address;

    // Delete default value for the last deleted element.
    if ($field_state['items_count'] == 0) {
      $struct_key = NestedArray::getValue($formInputs, $new_element_address);
      if (is_null($moving_element_value)) {
        foreach ($struct_key as &$key) {
          $key = '';
        }
        $moving_element_value = $struct_key;
      }
      if (is_null($moving_element_input)) {
        $moving_element_input = $moving_element_value;
      }
    }

    // Move the element around.
    NestedArray::setValue($formValues, $moving_element['#parents'], $moving_element_value, TRUE);
    NestedArray::setValue($formInputs, $moving_element['#parents'], $moving_element_input);

    // Save new element values.
    foreach ($formValues as $key => $value) {
      $form_state->setValue($key, $value);
    }
    $form_state->setUserInput($formInputs);

    // Move the entity in our saved state.
    if (isset($field_state['original_deltas'][$i + 1])) {
      $field_state['original_deltas'][$i] = $field_state['original_deltas'][$i + 1];
    }
    else {
      unset($field_state['original_deltas'][$i]);
    }
  }

  // Replace the deleted entity with an empty one. This helps to ensure that
  // trying to add a new entity won't resurrect a deleted entity
  // from the trash bin.
  // $count = count($field_state['entity']);
  // Then remove the last item. But we must not go negative.
  if ($field_state['items_count'] > 0) {
    $field_state['items_count']--;
  }

  // Fix the weights. Field UI lets the weights be in a range of
  // (-1 * item_count) to (item_count). This means that when we remove one,
  // the range shrinks; weights outside of that range then get set to
  // the first item in the select by the browser, floating them to the top.
  // We use a brute force method because we lost weights on both ends
  // and if the user has moved things around, we have to cascade because
  // if I have items weight weights 3 and 4, and I change 4 to 3 but leave
  // the 3, the order of the two 3s now is undefined and may not match what
  // the user had selected.
  $address = array_slice($button['#array_parents'], 0, -2);
  $keys = array_keys($address, 'widget', TRUE);
  foreach ($keys as $key) {
    unset($address[$key]);
  }
  $input = NestedArray::getValue($formInputs, $address);

  if ($input && is_array($input)) {
    // Sort by weight.
    uasort($input, '_field_multiple_value_form_sort_helper');

    // Reweight everything in the correct order.
    $weight = -1 * $field_state['items_count'];
    foreach ($input as $key => $item) {
      if ($item) {
        $input[$key]['_weight'] = $weight++;
      }
    }
    NestedArray::setValue($formInputs, $address, $input);
    $form_state->setUserInput($formInputs);
  }

  $element_id = isset($form[$field_name]['#id']) ? $form[$field_name]['#id'] : '';
  if (!$element_id) {
    $element_id = $parent_element['#id'];
  }
  $field_state['wrapper_id'] = $element_id;
  WidgetBase::setWidgetState($parents, $field_name, $form_state, $field_state);

  $form_state->setRebuild();
}


/**
 * Ajax callback remove field when remove click is trigger.
 *
 * In this callback we will replace field items. Main job
 * to delete field item we will done into submit handler.
 *
 * @param array $form
 *   The complete form structure.
 * @param FormStateInterface $form_state
 *   The current state of the form.
 *
 * @return array
 *   Array element.
 */
public static function multiple_fields_remove_button_js(array $form, FormStateInterface &$form_state) {
  $button = $form_state->getTriggeringElement();
  $address = array_slice($button['#array_parents'], 0, -2);
  // Go one level up in the form, to the widgets container.
  $parent_element = NestedArray::getValue($form, $address);
  $field_name = $parent_element['#field_name'];
  $parents = $parent_element['#field_parents'];
  $widget_state = WidgetBase::getWidgetState($parents, $field_name, $form_state);
  // Go one level up in the form, to the widgets container.
  $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -3));
  $element['#id'] = $widget_state['wrapper_id'];
  $element['#prefix'] = '<div class="ajax-new-content">' . (isset($element['#prefix']) ? $element['#prefix'] : '');
  $element['#suffix'] = (isset($element['#suffix']) ? $element['#suffix'] : '') . '</div>';

  return $element;
}

/**
 * Ajax callback to empty individual values of limited-cardinality fields.
 *
 * @param array $form
 *   The complete form structure.
 * @param FormStateInterface $form_state
 *   The current state of the form.
 *
 * @return \Drupal\Core\Ajax\AjaxResponse
 *   An Ajax response.
 */
public static function multiple_fields_remove_button_clear_value_js(array $form, FormStateInterface &$form_state) {
  $button = $form_state->getTriggeringElement();
  // Get the container element.
  $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -3));
  return $element;
}
}


//////
////////   A versatile way to add a wrapper to a button
//////
///////**
////// * Implements hook_element_info_alter().
////// */
//////function room_custom_setting_element_info_alter(array &$info) {
//////  if (isset($info['container'])) {
//////    $info['container']['#process'][] = 'multiple_fields_remove_button_process_container';
//////  }
//////};
///////**
////// * Add correct wrapper to the element.
////// *
////// * @param array $element
////// *  Reference to the Form API form element we're operating on.
////// *
////// * @return array
////// *   Array element.
////// */
//////function multiple_fields_remove_button_process_container(&$element) {
//////  if (isset($element['widget']['add_more']['#ajax']['wrapper'])) {
//////    $children = Element::children($element['widget']);
//////    $wrapperId = $element['widget']['add_more']['#ajax']['wrapper'];
//////
//////    foreach ($children as $child) {
//////      if (isset($element['widget'][$child]['remove_button'])) {
//////        $element['widget'][$child]['remove_button']['#ajax']['wrapper'] = $wrapperId;
//////      }
//////    }
//////  }
//////  else if (isset($element['widget'][0]['remove_button'])) {
//////    $children = Element::children($element['widget']);
//////    foreach ($children as $child) {
//////      if (isset($element['widget'][$child]['remove_button'])) {
//////        $element['widget'][$child]['remove_button']['#ajax']['wrapper'] = $element['#id'];
//////      }
//////    }
//////  }
//////  return $element;
//////};

