<?php

namespace Drupal\room_tariff\Plugin\Field\FieldType;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Form\FormStateInterface;

/**
 * Represents a configurable entity datetime field.
 */
class TariffFieldItemList extends FieldItemList {


  /**
   * Defines the default value as now.
   */
  const DEFAULT_VALUE_NOW = 'now';

  /**
   * Defines the default value as relative.
   */
  const DEFAULT_VALUE_CUSTOM = 'relative';

  /**
   * {@inheritdoc}
   */
  public function defaultValuesForm(array &$form, FormStateInterface $form_state) {

    $field_cardinality = $this->getFieldDefinition()->getFieldStorageDefinition()->getCardinality();
    if ($field_cardinality != 1 && empty($this->getFieldDefinition()->getDefaultValueCallback())) {
      $default_value = $this->getFieldDefinition()->getDefaultValueLiteral();

      $element = [
        '#parents' => ['default_value_input'],

        'default_begin_date_type' => [
          '#type' => 'select',
          '#title' => $this->t('Default date begin for the interval'),
          '#description' => $this->t('Set a default value for begin this date.'),
          '#default_value' => isset($default_value[0]['default_begin_date_type']) ? $default_value[0]['default_begin_date_type'] : '',
          '#options' => [
            static::DEFAULT_VALUE_NOW => $this->t('Current date'),
            static::DEFAULT_VALUE_CUSTOM => $this->t('Relative date'),
          ],
          '#empty_value' => '',
        ],
        'default_begin_date' => [
          '#type' => 'textfield',
          '#title' => $this->t('Relative default value'),
          '#description' => $this->t("Describe a time by reference to the current day, like '+90 days' (90 days from the day the field is created) or '+1 Saturday' (the next Saturday). See <a href=\"http://php.net/manual/function.strtotime.php\">strtotime</a> for more details."),
          '#default_value' => (isset($default_value[0]['default_begin_date']) && $default_value[0]['default_begin_date'] == static::DEFAULT_VALUE_CUSTOM) ? $default_value[0]['default_date'] : '',
          '#states' => [
            'visible' => [
              ':input[id="edit-default-value-input-default-begin-date-type"]' => ['value' => static::DEFAULT_VALUE_CUSTOM],
            ],
          ],
        ],

        'default_end_date_type' => [
          '#type' => 'select',
          '#title' => $this->t('Default date end for the interval'),
          '#description' => $this->t('Set a default value for end this date.'),
          '#default_value' => isset($default_value[0]['default_end_date_type']) ? $default_value[0]['default_end_date_type'] : '',
          '#options' => [
            static::DEFAULT_VALUE_NOW => $this->t('Current date'),
            static::DEFAULT_VALUE_CUSTOM => $this->t('Relative date'),
          ],
          '#empty_value' => '',
        ],
        'default_end_date' => [
          '#type' => 'textfield',
          '#title' => $this->t('Relative default value'),
          '#description' => $this->t("Describe a time by reference to the current day, like '+90 days' (90 days from the day the field is created) or '+1 Saturday' (the next Saturday). See <a href=\"http://php.net/manual/function.strtotime.php\">strtotime</a> for more details."),
          '#default_value' => (isset($default_value[0]['default_end_date']) && $default_value[0]['default_end_date'] == static::DEFAULT_VALUE_CUSTOM) ? $default_value[0]['default_date'] : '',
          '#states' => [
            'visible' => [
              ':input[id="edit-default-value-input-default-end-date-type"]' => ['value' => static::DEFAULT_VALUE_CUSTOM],
            ],
          ],
        ],

      ];

      return $element;
    } else if (empty($this->getFieldDefinition()->getDefaultValueCallback())) {
      if ($widget = $this->defaultValueWidget($form_state)) {
        // Place the input in a separate place in the submitted values tree.
        $element = array(
          '#parents' => array('default_value_input',),
        );
        $element += $widget->form($this, $element, $form_state);
        return $element;
      } else {
        return [
          '#markup' => $this->t('No widget available for: %type.', ['%type' => $this->getFieldDefinition()->getType(),]),
        ];
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultValuesFormValidate(array $element, array &$form, FormStateInterface $form_state) {
    $field_cardinality = $this->getFieldDefinition()->getFieldStorageDefinition()->getCardinality();
    if ($field_cardinality == 1) {
      return parent::defaultValuesFormValidate($element, $form, $form_state);
    };
    if ($form_state->getValue(['default_value_input', 'default_begin_date_type']) == static::DEFAULT_VALUE_CUSTOM) {
      $is_strtotime = @strtotime($form_state->getValue(['default_value_input', 'default_begin_date']));
      if (!$is_strtotime) {
        $form_state->setErrorByName('default_value_input][default_begin_date', $this->t('The relative date value entered is invalid.'));
      }
    };
    if ($form_state->getValue(['default_value_input', 'default_end_date_type']) == static::DEFAULT_VALUE_CUSTOM) {
      $is_strtotime = @strtotime($form_state->getValue(['default_value_input', 'default_end_date']));
      if (!$is_strtotime) {
        $form_state->setErrorByName('default_value_input][default_end_date', $this->t('The relative date value entered is invalid.'));
      }
    };
  }

  /**
   * {@inheritdoc}
   */
  public function defaultValuesFormSubmit(array $element, array &$form, FormStateInterface $form_state) {
    $field_cardinality = $this->getFieldDefinition()->getFieldStorageDefinition()->getCardinality();
    if ($field_cardinality == 1) {
      return parent::defaultValuesFormSubmit($element, $form, $form_state);
    };
    if ($form_state->getValue(['default_value_input', 'default_begin_date_type']) ||
      $form_state->getValue(['default_value_input', 'default_end_date_type'])) {
      if ($form_state->getValue(['default_value_input', 'default_begin_date_type']) == static::DEFAULT_VALUE_NOW) {
        $form_state->setValueForElement($element['default_begin_date'], static::DEFAULT_VALUE_NOW);
      };
      if ($form_state->getValue(['default_value_input', 'default_end_date_type']) == static::DEFAULT_VALUE_NOW) {
        $form_state->setValueForElement($element['default_end_date'], static::DEFAULT_VALUE_NOW);
      };
      return [$form_state->getValue('default_value_input')];
    };
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public static function processDefaultValue($default_value, FieldableEntityInterface $entity, FieldDefinitionInterface $definition) {

    $default_value = parent::processDefaultValue($default_value, $entity, $definition);

    if (isset($default_value[0]['default_begin_date_type'])) {
      $date = new DrupalDateTime($default_value[0]['default_begin_date'], date_default_timezone_get());
      $default_value[0]['begin'] = $date->getTimestamp();
    };
    if (isset($default_value[0]['default_end_date_type'])) {
      $date = new DrupalDateTime($default_value[0]['default_end_date'], date_default_timezone_get());
      $default_value[0]['end'] = $date->getTimestamp();
      //$test = new DrupalDateTime('next hour', date_default_timezone_get());
    };
    return $default_value;
  }

  /**
   * {@inheritdoc}
   *
   * We will change the clearing of empty entity elements.
   * Because we have a modified form and when working with its widget and calling isEmpty() it always returns true.
   * If this is not done, then when working with the form, this will cause a error check in constraint, since the data will be empty.
   */
  public function filterEmptyItems() {
    //$test = 0;
    $this->filter(function ($item) {
      return !$item->isEmpty() || !$item->isEmptyForm();
    });
    return $this;
  }
  /**
   * {@inheritdoc}
   *
   * We will change the check for empty elements in the list.
   * Because we have a modified form and when working with its widget and calling isEmpty() it always returns true.
   */
  public function isEmpty() {
    /** @var \Drupal\room_tariff\Plugin\Field\FieldType\TariffFieldItem $item */
    foreach ($this->list as $item) {
      if ($item instanceof \Drupal\Core\TypedData\ComplexDataInterface ||
        $item instanceof \Drupal\Core\TypedData\ListInterface) {
        if (!$item->isEmpty()) {
          return FALSE;
        } else if (!$item->isEmptyForm()) {
          if (!empty($pattern = $item->getValue()['pattern']) && in_array($pattern, ['per_hour','inan_day','i_person'])) {
            return FALSE;
          };
        }
      }
      // Other items are treated as empty if they have no value only.
      elseif ($item->getValue() !== NULL) {
        return FALSE;
      }
    }
    return TRUE;
  }

  //public function getConstraints() {
  //  $constraints = parent::getConstraints();
  //  $constraint_manager = $this->getTypedDataManager()->getValidationConstraintManager();
  //  $constraints[] = $constraint_manager->create('TariffUnique', []);
  //  return $constraints;
  //}

}
