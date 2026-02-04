<?php

namespace Drupal\datetime_flatpickr\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\datetime_range\Plugin\Field\FieldWidget\DateRangeWidgetBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\datetime_flatpickr\Constants\AvailableLanguages;

/**
 * Plugin implementation for 'datetime_range_separate_inputs_flatpickr' widget.
 */
#[FieldWidget(id: 'datetime_range_separate_inputs_flatpickr', label: new TranslatableMarkup('Flatpickr datetime range picker separate inputs'), field_types: ['daterange'])]
class DateTimeRangeSeparateInputsFlatPickrWidget extends DateRangeWidgetBase {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->languageManager = $container->get('language_manager');
    return $instance;
  }

  use DateTimeFlatPickrWidgetTrait {
    defaultSettings as traitDefaultSettings;
    settingsSummary as traitSettingsSummary;
    settingsForm as traitSettingsForm;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $element['value'] = [
      '#type' => 'textfield',
      '#default_value' => NULL,
      '#required' => $element['#required'],
      '#date_timezone' => date_default_timezone_get(),
    ] + $element['value'];

    if ($this->getFieldSetting('datetime_type') == DateTimeItem::DATETIME_TYPE_DATE) {
      // A date-only field should have no timezone conversion performed, so
      // use the same timezone as for storage.
      $element['value']['#date_timezone'] = DateTimeItemInterface::STORAGE_TIMEZONE;
    }

    if ($items[$delta]->start_date) {
      $start_date = $items[$delta]->start_date;
      // The date was created and verified during field_load(), so it is safe to
      // use without further inspection.
      $start_date->setTimezone(new \DateTimeZone($element['value']['#date_timezone']));
      $element['value']['#default_value'] = $this->createDefaultValue($start_date, $element['value']['#date_timezone']);
    }

    // Add flatpickr library.
    $element['#attached']['library'][] = 'datetime_flatpickr/flatpickr-init';

    // Clone End Value item from Start Value item.
    $element['end_value'] = $element['value'];
    // Optional end date module support
    // (https://www.drupal.org/project/optional_end_date).
    if (\Drupal::moduleHandler()->moduleExists('optional_end_date')) {
      $optional_end_date = $this->getFieldSetting('optional_end_date');

      if ($element['#required'] && $optional_end_date) {
        $element['end_value']['#required'] = FALSE;
        $element['end_value']['#default_value'] = '';
      }
    }

    if ($items[$delta]->end_date) {
      /** @var \Drupal\Core\Datetime\DrupalDateTime $end_date */
      $end_date = $items[$delta]->end_date;
      $end_date->setTimezone(new \DateTimeZone($element['end_value']['#date_timezone']));
      $element['end_value']['#default_value'] = $this->createDefaultValue($end_date, $element['end_value']['#date_timezone']);
    }

    // Add flatpickr options for start date.
    $entity_type = $items->getEntity()->getEntityTypeId();
    $name = $entity_type . '-' . $items->getName();
    $element['value']['#attributes']['flatpickr-name'] = $name;

    $settings = self::processFieldSettings($this->getSettings());

    $lang_code = $this->languageManager->getCurrentLanguage()->getId();
    if (in_array($lang_code, AvailableLanguages::LANGUAGES)) {
      $form['value']['#attached']['library'][] = 'datetime_flatpickr/flatpickr_' . mb_strtolower($lang_code);
      $settings['locale'] = $lang_code;
    }

    $element['#attached']['drupalSettings']['datetimeFlatPickr'][$name] = [
      'settings' => $settings,
    ];

    // Custom End Value Input.
    $element['end_value']['#title'] = $this->t('End date');
    // Add flatpickr options for end date.
    $end_name = $name . '-end';
    // Special case same day, hide calendar, only time input.
    if ($this->getSetting('sameDay')) {
      $element['end_value']['#wrapper_attributes']['class'][] = 'same-day';
      $settings = [
         // Keep the date format, but force the time input.
        'altInput' => TRUE,
        'altFormat' => 'H:i',
        'enableTime' => TRUE,
        'noCalendar' => TRUE,
      ] + $settings;
    }
    $element['end_value']['#attributes']['flatpickr-name'] = $end_name;
    $element['#attached']['drupalSettings']['datetimeFlatPickr'][$end_name] = [
      'settings' => $settings,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    // The widget form element type has transformed the value to a
    // DrupalDateTime object at this point. We need to convert it back to the
    // storage timezone and format.
    $datetime_type = $this->getFieldSetting('datetime_type');
    if ($datetime_type === DateTimeItem::DATETIME_TYPE_DATE) {
      $storage_format = DateTimeItemInterface::DATE_STORAGE_FORMAT;
    }
    else {
      $storage_format = DateTimeItemInterface::DATETIME_STORAGE_FORMAT;
    }

    $storage_timezone = new \DateTimezone(DateTimeItemInterface::STORAGE_TIMEZONE);
    $settings = $this->getSettings();

    $values_types = ['value', 'end_value'];

    foreach ($values as &$item) {
      foreach ($values_types as $values_type) {
        if (!empty($item[$values_type])) {
          try {
            $date = DrupalDateTime::createFromFormat($settings['dateFormat'], $item[$values_type]);
          }
          catch (\Exception $exception) {
            // Fallback time conversation.
            $timestamp = strtotime($item[$values_type]);
            $date = DrupalDateTime::createFromTimestamp($timestamp);
          }

          // Adjust the date for storage.
          if ($datetime_type !== DateTimeItem::DATETIME_TYPE_DATE) {
            $date->setTimezone($storage_timezone);
          }
          $item[$values_type] = $date->format($storage_format);
        }

        if (!empty($item[$values_type]) && $item[$values_type] instanceof DrupalDateTime) {
          /** @var \Drupal\Core\Datetime\DrupalDateTime $date */
          $date = $item[$values_type];

          // Adjust the date for storage.
          if ($datetime_type !== DateTimeItem::DATETIME_TYPE_DATE) {
            $date->setTimezone($storage_timezone);
          }
          $item[$values_type] = $date->format($storage_format);
        }
      }
    }

    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'sameDay' => FALSE,
    ] + self::traitDefaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = self::traitSettingsForm($form, $form_state);
    $options = $this->getSettings();

    $element['sameDay'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Same Day'),
      '#description' => $this->t('Hide the end date, and use the start date as the end date.'),
      '#default_value' => $options['sameDay'],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = self::traitSettingsSummary();
    $options = $this->getSettings();

    if ($options['sameDay']) {
      $summary[] = $this->t('Same day: only time input for end date');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function validateStartEnd(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $dateFormat = $this->getSetting('dateFormat');

    $access = (isset($element['value']['#access']) ? $element['value']['#access'] : TRUE);
    if ($access && !empty($element['value']['#value']) && !empty($element['end_value']['#value'])) {
      $start_date = DrupalDateTime::createFromFormat($dateFormat, $element['value']['#value']);
      $end_date = DrupalDateTime::createFromFormat($dateFormat, $element['end_value']['#value']);

      // For the same day option for end date, we need to update from start date.
      if ($this->getSetting('sameDay')) {
        $new_end_date = clone $start_date;
        $new_end_date->setTime($end_date->format('H'), $end_date->format('i'));
        $end_date = $new_end_date;
        $end_date_value = $new_end_date->format($dateFormat);
        $element['end_value']['#value'] = $end_date_value;
        $form_state->setValueForElement($element['end_value'], $end_date_value);
      }

      if ($start_date->getTimestamp() > $end_date->getTimestamp()) {
        $form_state->setError($element, $this->t('The @title end date cannot be before the start date.', ['@title' => $element['#title']]));
      }
    }
  }

}
