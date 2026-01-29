<?php

/**
 * @file
 * Contains \Drupal\room_tariff\Plugin\Field\FieldWidget\RulesTariffFieldInputWidget.
 */

namespace Drupal\room_tariff\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * @FieldWidget(
 *   id = "tariff_rules_input_widget",
 *   module = "room_tariff",
 *   label = @Translation("Form for rules tariff field"),
 *   field_types = {
 *     "tariff_rules"
 *   }
 * )
 */
class RulesTariffFieldInputWidget extends WidgetBase {



  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

    $field_name = $this->fieldDefinition->getName();
    $filed_settings = $this->getFieldSettings();

    // We make select elements translatable for form
    array_walk($filed_settings, function (&$value, $key) {
      if (in_array($key, ['type_rule_tariff', 'subtype_rule_if_more', 'type_pattern_tariff',])) {
        array_walk($value, function (&$translate) {
          $translate = $this->t($translate);
        });
      };
    });

    // bild form
    if ($form_state->isProcessingInput()) {
      $values = $form_state->getValue(array_merge($form['#parents'], [$field_name, $delta]));
      if (isset($values)) {
        $values = $values + ['root' => 'form'];
      } else if (!in_array($field_name, $form_state->getTriggeringElement()['#parents'])) {
        //In cases when pressed the button in the form, which does not apply to our field.
        //We need to get the data for options in the pattern_tariff from rule_type selection field,
        //because it changes depending on the previous selection.
        $values = $form_state->getCompleteForm();
        foreach (array_merge($form['#parents'], [$field_name]) as $key) {
          $values = $values[$key] ?? [];
        };
        $values = $values['widget'][$delta]['rule_type']['#value'] ?? null;
        $values = !empty($values) ? ['rule_type' => $values] + ['root' => 'form_other'] : ['root' => 'form_unknown'];
      } else {
        $values = ['root' => 'add_new'];
      };
    } else if (!empty($items[$delta]->getValue())) {
      $values = $items[$delta]->getValue() + ['root' => 'bd'];
    } else {
      $values = [];
    };
    $check = [];
    foreach($items as $val){
      $check[] = $val->rule_type;
    }

    $key = array_search("minprice", $check);
    if(in_array('minprice', $check) && $key !== $delta){
      unset($filed_settings['type_rule_tariff']['minprice']);
    }
    $default_value = !in_array('minprice', $check) ? 'minprice' : 'if_large';
    // dump($check[$delta]);
    $element['rule_type'] = array(
      '#title' => $this->t('Rule'),
      '#type' => 'select',
      '#options' => $filed_settings['type_rule_tariff'],
      // '#default_value' => (isset($values['root']) && $values['root'] == 'bd') ? $items[$delta]->rule_type : key($filed_settings['type_rule_tariff']),
      '#default_value' => (isset($values['root']) && $values['root'] == 'bd') ? $items[$delta]->rule_type : $default_value,
      '#description' => $this->t('Select the type of rule for price.'),
      '#limit_validation_errors' => [array_merge($form['#parents'], [$field_name,])],
    );

    $element['subrule'] = array(
      '#type' => 'container',
      '#attributes' => ['id' => $field_name.'-sub-'.$delta,],
    );
    // array_pop($filed_settings['type_pattern_tariff']);
    $field_options = array_merge($filed_settings['type_pattern_tariff'],$filed_settings['subtype_rule_if_more']);
    $element['subrule']['pattern_tariff'] = array(
      '#title' => $this->t('For which price variation'),
      '#type' => 'select',
      // '#options' => $filed_settings['type_pattern_tariff'],
      '#options' => $field_options,
      '#default_value' => $items[$delta]->pattern_tariff,
      '#description' => $this->t('Select a price variation.'),
    );

    // if (!empty($values['rule_type'])) {
    //   switch ($values['rule_type']) {
    //     case "minprice":
    //       $element['subrule']['pattern_tariff']['#default_value'] = $values['root'] == 'bd' ? $items[$delta]->pattern_tariff : key($filed_settings['type_pattern_tariff']);
    //       break;
    //     case "if_large":
    //       $element['subrule']['pattern_tariff']['#title'] = $this->t('Time unit');
    //       $element['subrule']['pattern_tariff']['#options'] = $filed_settings['subtype_rule_if_more'];
    //       $element['subrule']['pattern_tariff']['#default_value'] = $values['root'] == 'bd' ? $items[$delta]->pattern_tariff : key($filed_settings['subtype_rule_if_more']);
    //       $element['subrule']['pattern_tariff']['#description'] = $this->t('Set a new price if the booking time exceeds the set one.');
    //       break;
    //     default:
    //   };
    // };

    $element['subrule']['datarule'] = array(
      '#type' => 'container',
    );
    $allowed_values['time'] = $values['rule_type'] ?? $element['rule_type']['#default_value'];
    if (!empty($allowed_values['time'])) {
      $element['subrule']['datarule']['span_time'] = array(
        '#type' => 'number',
        '#size' => 30,
        '#min' => 2,
        '#step' => 1,
      );
      switch ($allowed_values['time']) {
        case 'minprice':
          $title = $items[$delta]->pattern_tariff == 'i_person' ? 'Minimum Person' : 'Minimum Time';
          $element['subrule']['datarule']['span_time']['#title'] = $this->t($title);
          // $element['subrule']['datarule']['span_time']['#description'] = $this->t('Select a minimum time for base prices.');
          $element['subrule']['datarule']['span_time']['#default_value'] =
            (isset($values['root']) && $values['root'] == 'bd' && !empty($items[$delta]->span_time)) ? $items[$delta]->span_time : NULL;
          if (!empty($element['subrule']['datarule']['span_time']['#default_value'])) {
            if ($items[$delta]->pattern_tariff == 'inan_day') {
              $element['subrule']['datarule']['span_time']['#default_value'] /= 86400;
            }elseif($items[$delta]->pattern_tariff == 'i_person'){
              $element['subrule']['datarule']['span_time']['#default_value'];
            } else {
              $element['subrule']['datarule']['span_time']['#default_value'] /= 3600;
            }
          };
          break;
        case 'if_large':
          $title = $items[$delta]->pattern_tariff == 'i_person' ? 'Minimum Person' : 'Minimum Time';
          $element['subrule']['datarule']['span_time']['#title'] = $this->t($title);
          // $element['subrule']['datarule']['span_time']['#description'] = $this->t('Select a minimum time for base prices.');
          $element['subrule']['datarule']['span_time']['#default_value'] =
            (isset($values['root']) && $values['root'] == 'bd' && !empty($items[$delta]->span_time)) ? $items[$delta]->span_time : NULL;
          if (!empty($element['subrule']['datarule']['span_time']['#default_value'])) {
            if ($items[$delta]->pattern_tariff == 'days') {
              $element['subrule']['datarule']['span_time']['#default_value'] /= 86400;//24 hours * 3600 seconds
            }elseif($items[$delta]->pattern_tariff == 'i_person'){
              $element['subrule']['datarule']['span_time']['#default_value'];
            } else {
              $element['subrule']['datarule']['span_time']['#default_value'] /= 3600;
            }
          };
          break;
        default:
      };
    };

    // if (!empty($values['rule_type'])) {
    //   switch ($values['rule_type']) {
    //     case 'minprice':
    //       break;
    //     case 'if_large':
          $element['subrule']['datarule']['price'] = array(
            '#title' => $this->t('Price'),
            '#type' => 'number',
            '#size' => 30,
            '#min' => 0.01,
            '#step' => 0.01,
            '#default_value' => (isset($values['root'], $items[$delta]->price) && $values['root'] == 'bd') ? number_format($items[$delta]->price / 100, 2, '.', '') : NULL,
          );
      //     break;
      //   default:
      // };
    // };

    // Add Ajax
    $element['rule_type']['#ajax'] = [
      'callback' => [$this, 'changeAjaxSubrulePattern'],
      'wrapper' => $field_name.'-sub-'.$delta,
      'event' => 'change',
      'progress' => [
        'type' => 'throbber',
        'message' => $this->t('Updating form..'),
      ],
    ];

    return $element;
  }

  /**
   * Special handling to create form elements for multiple values.
   *
   * Removed the added generic features for multiple fields:
   * - Number of widgets;
   * - AHAH 'add more' button;
   * - Table display and drag-n-drop value reordering.
   * N.B. This is never called with Annotation: multiple_values = "FALSE".
   *
   * {@inheritdoc}
   */
  protected function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $form_state) {

    $elements = parent::formMultipleElements($items, $form, $form_state);
    $field_cardinality = $this->fieldDefinition->getFieldStorageDefinition()->getCardinality();
    if ($field_cardinality == FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED) {

      $delta = $elements['#max_delta'];
      $wrapper_id = $elements['add_more']['#ajax']['wrapper'];

      if ($delta >= 1) {

        $field_name = $this->fieldDefinition->getName();
        $parents = $form['#parents'];
        $language = $items->getLangcode() ? $items->getLangcode() : \Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED;

        $element['remove_item'] = [
          '#type' => 'submit',
          '#value' => $this->t('Remove'),
          '#submit' => array([$this, 'removeCallback']),
          '#limit_validation_errors' => [array_merge($parents, [$field_name])],
          '#attributes' => [
            'class' => [
              'multiple-fields-remove-button',
            ],
          ],
          '#ajax' => [
            'callback' => array($this, 'removeAjaxCallback'),
            'wrapper' => $wrapper_id,
            'effect' => 'fade',
            'method' => 'replace',
          ],
        ];

        for ($i=1; $i<=$delta; $i++) {
          $elements[$i] += $element;
          $elements[$i]['remove_item']['#delta'] = $i;
          $field_parents = isset($elements[$i]['#field_parents']) ? $elements[$i]['#field_parents'] : [];
          $all_parents = array_merge($field_parents, [$field_name, $language, $i,]);
          $elements[$i]['remove_item']['#name'] = implode('_', $all_parents) . '_remove_button';
        };

      };

    }

    return $elements;
  }


  /**
   * We change the form element when the type is selected in the select.
   * @return array Returning the modified element.
   */
  public function changeAjaxSubrulePattern (array $form, FormStateInterface $form_state) {

    $selected_item = $form_state->getTriggeringElement();
    $path_to_element = $selected_item['#array_parents'];
    $path_to_element = array_slice($path_to_element, 0, -1);
    $new_value =& $form;
    foreach ($path_to_element as $k) {
      if (is_array($new_value) && array_key_exists($k, $new_value)) {
        $new_value =& $new_value[$k];
      };
    };

    return $new_value['subrule'];
  }


  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {

    foreach ($values as &$item) {

      if (!empty($item)) {
        $item = array_merge($item, $item['subrule'], $item['subrule']['datarule']);
        // We're changing the type because if an empty value comes from a form,
        // it throws constraint - a "must be a number" error.
        if (isset($item['span_time'])) {
          $item['span_time'] = intval($item['span_time']);
        };
        if (isset($item['price'])) {
          $item['price'] = $item['price'] === '' ? null : intval(str_replace(',','.',$item['price'])*100);
        };
        unset($item['subrule'], $item['datarule']);
      };

    };

    // Make sure we only process once, after validation.
    if ($form_state->isValidationComplete()) {
      //$filed_settings = $this->getFieldSettings();

      //The widget form element type has the value at this moment.
      //We need to convert it back to the storage timestamp.
      //We need to convert the monetary unit to cents
      foreach ($values as &$item) {
        if (isset($item['span_time'])) {
          switch ($item['pattern_tariff']) {
            case 'hours';
            case 'per_hour';
              $item['span_time'] = $item['span_time'] * 3600;
            break;
            case 'days';
            case 'inan_day';
              $item['span_time'] = $item['span_time'] * 86400;
            break;
          }
        };
      };

      return $values;

    } else {
      return parent::massageFormValues($values, $form, $form_state);
    };

  }


  /**
   * Submit handler for the "remove one" button.
   * Decrements the max counter and causes a form rebuild.
   */
  public function removeCallback(array &$form, FormStateInterface $form_state) {

    $triggeringing_button = $form_state->getTriggeringElement();
    $field_name = $this->fieldDefinition->getName();

    $form_fields_address = array_slice($triggeringing_button['#parents'], 0, -2);
    $formValues = $form_state->getValue($form_fields_address);;

    $originalFormInputs = $form_state->getUserInput();
    $formInputs =& $originalFormInputs;
    foreach ($form_fields_address as $v) {
      if (array_key_exists($v, $formInputs)) {
        $formInputs =& $formInputs[$v];
      };
    };
    $delta = array_slice($triggeringing_button["#parents"],-2,1)[0];

    $address_widget_on_element = array_slice($triggeringing_button['#array_parents'], 0, -2);
    // Go one level up in the form, to the widgets container.
    $widget_parent = $form;
    foreach ($address_widget_on_element as $v) {
      $widget_parent = $widget_parent[$v];
    };
    $parents_on_widget = $widget_parent['#field_parents'];
    $field_state = WidgetBase::getWidgetState($parents_on_widget, $field_name, $form_state);
    // Go ahead and renumber everything from our delta to the last item down one. This will overwrite the item being removed.
    for ($i = $delta; $i < $field_state['items_count']; $i++) {

      $formValues[$i] = $formValues[$i+1];
      if (array_key_exists('_weight', $formValues[$i])) {$formValues[$i]['_weight'] = strval($formValues[$i]['_weight']-1);};
      $formInputs[$i] = $formInputs[$i+1];
      if (array_key_exists('_weight', $formInputs[$i])) {$formInputs[$i]['_weight'] = strval($formInputs[$i]['_weight']-1);};

      // Move the entity in our saved state.
      if (isset($field_state['original_deltas'][$i + 1])) {
        $field_state['original_deltas'][$i] = $field_state['original_deltas'][$i + 1];
      } else {
        unset($field_state['original_deltas'][$i]);
      };
    };

    if (isset($field_state['wrapper_id'])) {
      $element_id = isset($form[$field_name]['#id']) ? $form[$field_name]['#id'] : '';
      if (!$element_id) {$element_id = $widget_parent['#id'];};
      $field_state['wrapper_id'] = $element_id;
    }

    // Delete default value for the last deleted element.
    if ($field_state['items_count'] == $i) {
      unset($formValues[$i], $formInputs[$i]);
    };
    // Save new element values.
    $form_state->setValue($field_name, $formValues);
    $form_state->setUserInput($originalFormInputs);

    // Replace the deleted entity with an empty one. This helps to ensure that trying to add a new entity
    // won't resurrect a deleted entity from the trash bin. $count = count($field_state['entity']);
    // Then remove the last item. But we must not go negative.
    if ($field_state['items_count'] > 0) {
      $field_state['items_count']--;
    };

    WidgetBase::setWidgetState($parents_on_widget, $field_name, $form_state, $field_state);

    $form_state->setRebuild();
  }
  /**
   * Callback for both ajax-enabled buttons.
   * Selects and returns the fieldset with the names in it.
   */
  public function removeAjaxCallback(array &$form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();
    $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -3));
    $element = $element['widget'];
    return $element;
  }


}
