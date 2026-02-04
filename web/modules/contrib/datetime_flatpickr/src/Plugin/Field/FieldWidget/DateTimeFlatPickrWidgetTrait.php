<?php

namespace Drupal\datetime_flatpickr\Plugin\Field\FieldWidget;

use Drupal\Component\Datetime\DateTimePlus;
use Drupal\Core\Datetime\DateHelper;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\Checkboxes;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\Component\Utility\Html;
use Drupal\datetime_flatpickr\Constants\AvailableLanguages;

/**
 * Trait of Plugin implementation of the various Flatpickr datetime widgets.
 *
 * @var Trait
 * @see \Drupal\datetime_flatpickr\Plugin\Field\FieldWidget\DateTimeFlatPickrWidget
 * @see \Drupal\datetime_flatpickr\Plugin\Field\FieldWidget\DateTimeRangeFlatPickrWidget
 */
trait DateTimeFlatPickrWidgetTrait {

  /**
   * {@inheritdoc}
   */
  protected function createDefaultValue($date, $timezone) {
    // The date was created and verified during field_load(), so it is safe to
    // use without further inspection.
    if ($this->getFieldSetting('datetime_type') === DateTimeItem::DATETIME_TYPE_DATE) {
      $date->setDefaultDateTime();
    }
    $date->setTimezone(new \DateTimeZone($timezone));
    $date->render();
    return $date->render();
  }

  /**
   * Provide the default settings.
   */
  public static function getDefaultSettings() {
    return [
      'use_system_format' => FALSE,
      'system_date_format' => '',
      // Exactly the same as date format, but for the altInput field.
      'altFormat' => 'F j, Y',
      // Show the user a readable date (as per altFormat), but return something
      // totally different to the server.
      'altInput' => FALSE,
      // Allows the user to enter a date directly into the input field.
      // By default, direct entry is disabled.
      'allowInput' => FALSE,
      // A string of characters which are used to define how the date will be
      // displayed in the input box. The supported characters are defined in
      // the table below.
      'dateFormat' => 'Y-m-d H:i',
      // Sets the initial selected date(s). If you're using mode: "multiple"
      // or a range calendar supply an Array of Date objects or an Array of
      // date strings which follow your dateFormat. Otherwise, you can supply
      // a single Date object or a date string.
      'defaultDate' => '',
      // Initial value of the hour element.
      'defaultHour' => '',
      // Initial value of the minute element.
      'defaultMinute' => '',
      // Enables time picker.
      'enableTime' => FALSE,
      // Enables seconds in the time picker.
      'enableSeconds' => FALSE,
      // Displays the calendar inline.
      'inline' => FALSE,
      // The maximum date that a user can pick to (inclusive).
      'maxDate' => '',
      // The minimum date that a user can start picking from (inclusive).
      'minDate' => '',
      // The maximum time that a user can pick to (inclusive).
      'maxTime' => ['hour' => '', 'min' => ''],
      // The minimum time that a user can start picking from (inclusive).
      'minTime' => ['hour' => '', 'min' => ''],
      // Adjusts the step for the minute input (incl. scrolling)
      'minuteIncrement' => 5,
      // Where the calendar is rendered relative to the input.
      // "auto", "above" or "below".
      'position' => 'auto',
      // Displays time picker in 24 hour mode without AM/PM
      // selection when enabled.
      'time_24hr' => FALSE,
      // Enables display of week numbers in calendar.
      'weekNumbers' => FALSE,
      // The days of the week for disabling from calendar.
      'disabledWeekDays' => [],
      // The dates that are disabled from calendar.
      'disabledDates' => [],
      // Mode can be single date, date range or multiple dates.
      'mode' => 'single',
      // The date to jump to when the calendar is opened.
      'jumpToDate' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return self::getDefaultSettings() + parent::defaultSettings();
  }

  /**
   * Returns the parent form selector path depending on where it is used.
   */
  protected function getParentSelector() {
    if (isset($this->fieldDefinition) && $this->fieldDefinition->getName()) {
      return 'fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings]';
    }
    if (isset($this->handler) && !empty($this->handler->options['id'])) {
      return 'exposed_form_options[bef][filter][' . $this->handler->options['id'] . '][configuration][flatpickr_settings]';
    }
    return '';
  }

  /**
   * Provide the default settings form.
   */
  public function getSettingsForm($options) {
    $parent_selector = $this->getParentSelector();

    $element['altInput'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Alternative input'),
      '#description' => $this->t('Show the user a readable date (as per altFormat), but return something totally different to the server.'),
      '#default_value' => $options['altInput'],
    ];
    $element['altFormat'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Alternative format'),
      '#description' => $this->t('Exactly the same as date format, but for the altInput field.'),
      '#default_value' => $options['altFormat'],
      '#states' => [
        'visible' => [
          'input[name="' . $parent_selector . '[altInput]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $element['use_system_format'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use system date format'),
      '#description' => $this->t('Use a date format defined in Regional Settings.'),
      '#default_value' => $options['use_system_format'],
      '#states' => [
        'visible' => [
          ':input[name="' . $parent_selector . '[altInput]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $date_formats = \Drupal::entityTypeManager()
      ->getStorage('date_format')
      ->loadMultiple();

    $format_options = [];
    foreach ($date_formats as $format) {
      $format_options[$format->id()] = $format->label() . ' (' . $format->getPattern() . ')';
    }

    $element['system_date_format'] = [
      '#type' => 'select',
      '#title' => $this->t('System date format'),
      '#description' => $this->t('Select which system date format to use.'),
      '#options' => $format_options,
      '#default_value' => $options['system_date_format'],
      '#states' => [
        'visible' => [
          ':input[name="' . $parent_selector . '[use_system_format]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $element['allowInput'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow Input'),
      '#description' => $this->t('Allows the user to enter a date directly into the input field. By default, direct entry is disabled.'),
      '#default_value' => $options['allowInput'],
    ];
    $element['dateFormat'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Date format'),
      '#description' => $this->t("A string of characters which are used to define how the date will be handled in the input box. To show a different date format to the user, please use the Alternative input above."),
      '#default_value' => $options['dateFormat'],
    ];
    $element['enableTime'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enables time picker'),
      '#default_value' => $options['enableTime'],
    ];
    $element['enableSeconds'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enables seconds'),
      '#description' => $this->t('Enables seconds in the time picker.'),
      '#default_value' => $options['enableSeconds'],
    ];
    $element['inline'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Inline calendar'),
      '#description' => $this->t('Displays the calendar inline.'),
      '#default_value' => $options['inline'],
    ];
    $element['minDate'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Minimum date'),
      '#description' => $this->t('The minimum date that a user can start picking from (inclusive).'),
      '#default_value' => $options['minDate'],
    ];
    $element['maxDate'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Maximum date'),
      '#description' => $this->t('The maximum date that a user can pick to (inclusive).'),
      '#default_value' => $options['maxDate'],
    ];
    $element['minTime'] = [
      '#type' => 'details',
      '#title' => $this->t('Minimum time'),
      '#description' => $this->t('The minimum time that a user can start picking from (inclusive).'),
      '#open' => FALSE,
      'hour' => [
        '#type' => 'textfield',
        '#title' => $this->t('Hour'),
        '#default_value' => $options['minTime']['hour'],
        '#element_validate' => [
          [$this, 'fieldSettingsHourElementValidate'],
        ],
      ],
      'min' => [
        '#type' => 'textfield',
        '#title' => $this->t('Minute'),
        '#default_value' => $options['minTime']['min'],
        '#element_validate' => [
          [$this, 'fieldSettingsMinElementValidate'],
        ],
      ],
    ];
    $element['maxTime'] = [
      '#type' => 'details',
      '#title' => $this->t('Maximum time'),
      '#description' => $this->t('The maximum time that a user can pick to (inclusive).'),
      '#open' => FALSE,
      'hour' => [
        '#type' => 'textfield',
        '#title' => $this->t('Hour'),
        '#default_value' => $options['maxTime']['hour'],
        '#element_validate' => [
          [$this, 'fieldSettingsHourElementValidate'],
        ],
      ],
      'min' => [
        '#type' => 'textfield',
        '#title' => $this->t('Minute'),
        '#default_value' => $options['maxTime']['min'],
        '#element_validate' => [
          [$this, 'fieldSettingsMinElementValidate'],
        ],
      ],
    ];
    $element['minuteIncrement'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Minute increment'),
      '#description' => $this->t('Adjusts the step for the minute input (incl. scrolling).'),
      '#default_value' => $options['minuteIncrement'],
    ];
    $element['position'] = [
      '#type' => 'select',
      '#title' => $this->t('Position'),
      '#default_value' => $options['position'],
      '#description' => $this->t('Where the calendar is rendered relative to the input.'),
      '#options' => [
        'auto' => $this->t('Auto'),
        'above' => $this->t('Above'),
        'below' => $this->t('Below'),
      ],
    ];
    $element['time_24hr'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Time 24h'),
      '#description' => $this->t('Displays time picker in 24 hour mode without AM/PM selection when enabled.'),
      '#default_value' => $options['time_24hr'],
    ];
    $element['weekNumbers'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show week numbers'),
      '#description' => $this->t('Enables display of week numbers in calendar.'),
      '#default_value' => $options['weekNumbers'],
    ];
    $element['disabledWeekDays'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Disabled week days'),
      '#description' => $this->t('Select the days of the week for disabling from calendar.'),
      '#options' => DateHelper::weekDays(TRUE),
      '#default_value' => Checkboxes::getCheckedCheckboxes($options['disabledWeekDays']),
      '#element_validate' => [
        [$this, 'fieldSettingsWeekDaysValidate'],
      ],
    ];

    $element['disabledDates'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Disabled dates'),
      '#description' => $this->t('Enter dates to be disabled, one per line. Format: YYYY-MM-DD'),
      '#default_value' => $options['disabledDates'],
      '#element_validate' => [
        [$this, 'fieldSettingsDatesValidate'],
      ],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);
    $options = $this->getSettings();

    return $element + $this->getSettingsForm($options);
  }

  /**
   * disabledWeekDays validation.
   */
  public function fieldSettingsWeekDaysValidate($element, FormStateInterface $form_state) {
    $values = $element['#value'];
    $form_state->setValueForElement($element, $values);
  }

  /**
   * disabledDates validation.
   */
  public function fieldSettingsDatesValidate($element, FormStateInterface $form_state) {
    $values = $element['#value'];
    if (!empty($values)) {
      $dates = array_filter(array_map('trim', explode("\n", $values)));
      $invalid_dates = [];
      foreach ($dates as $date) {
        try {
          $date_obj = new DateTimePlus($date, NULL, 'Y-m-d', ['validate_format' => TRUE]);
          if ($date_obj->format('Y-m-d') !== $date) {
            $invalid_dates[] = $date;
          }
        }
        catch (\Exception $e) {
          $invalid_dates[] = $date;
        }
      }
      if (!empty($invalid_dates)) {
        $t_args = [
          '@invalid_dates' => implode(', ', $invalid_dates),
        ];
        $form_state->setError($element, $this->t('The following dates are not valid or not in the correct format (YYYY-MM-DD): @invalid_dates', $t_args));
      }
    }
  }

  /**
   * Hour element validation.
   */
  public function fieldSettingsHourElementValidate(&$element, FormStateInterface $form_state) {
    $setting = &$form_state->getValue($element['#parents']);
    if (isset($setting)) {
      // For two-tiered array.
      $limits = [0, 23];
      // Validate int hours and minutes settings.
      if ($setting !== '') {
        if (!is_numeric($setting) || intval($setting) != $setting || $setting < $limits[0] || $setting > $limits[1]) {
          $t_args = [
            '%name' => $element['#title'],
            '@start' => $limits[0],
            '@end' => $limits[1],
          ];
          $form_state->setError($element, $this->t('%name must be an integer between @start and @end.', $t_args));
        }
      }
    }
  }

  /**
   * Minute element validation.
   */
  public function fieldSettingsMinElementValidate(&$element, FormStateInterface $form_state) {
    $setting = &$form_state->getValue($element['#parents']);
    if (isset($setting)) {
      // For two-tiered array.
      $limits = [0, 59];
      // Validate int hours and minutes settings.
      if ($setting !== '') {
        if (!is_numeric($setting) || intval($setting) != $setting || $setting < $limits[0] || $setting > $limits[1]) {
          $t_args = [
            '%name' => $element['#title'],
            '@start' => $limits[0],
            '@end' => $limits[1],
          ];
          $form_state->setError($element, $this->t('%name must be an integer between @start and @end.', $t_args));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $options = $this->getSettings();

    if ($options['altInput']) {
      if ($options['use_system_format']) {
        $summary[] = $this->t('Alternative input format: @alt_input', ['@alt_input' => $options['system_date_format']]);
      }
      else {
        $summary[] = $this->t('Alternative input format: @alt_input', ['@alt_input' => $options['altFormat']]);
      }
    }
    $summary[] = $this->t('Date format: @date_format', ['@date_format' => $options['dateFormat']]);

    if ($options['allowInput']) {
      $summary[] = $this->t('Allow direct date input');
    }

    if ($options['enableTime']) {
      $summary[] = $this->t('Enabled time picker.');
    }

    if ($options['enableSeconds']) {
      $summary[] = $this->t('Enabled seconds.');
    }

    if ($options['inline']) {
      $summary[] = $this->t('Inline calendar.');
    }

    if ($options['minDate']) {
      $summary[] = $this->t('Min date: @min_date', ['@min_date' => $options['minDate']]);
    }

    if ($options['maxDate']) {
      $summary[] = $this->t('Max date: @max_date', ['@max_date' => $options['maxDate']]);
    }

    if ($options['minTime']['hour'] || $options['minTime']['min']) {
      $summary[] = $this->t('Min time: @hour:@min', [
        '@hour' => $options['minTime']['hour'],
        '@min' => $options['minTime']['min'],
      ]);
    }

    if ($options['maxTime']['hour'] || $options['maxTime']['min']) {
      $summary[] = $this->t('Max time: @hour:@min', [
        '@hour' => $options['maxTime']['hour'],
        '@min' => $options['maxTime']['min'],
      ]);
    }

    $summary[] = $this->t('Minute increment: @inc', ['@inc' => $options['minuteIncrement']]);
    $summary[] = $this->t('Position: @pos', ['@pos' => $options['position']]);

    if ($options['time_24hr']) {
      $summary[] = $this->t('Use 24h time');
    }

    if ($options['weekNumbers']) {
      $summary[] = $this->t('Show week numbers');
    }

    if ($options['disabledWeekDays']) {
      $disabledWeekDays = implode(', ', array_intersect_key(DateHelper::weekDays(TRUE), array_flip(Checkboxes::getCheckedCheckboxes($options['disabledWeekDays']))));
      $summary[] = $this->t('Disabled week days: @disabledWeekDays', ['@disabledWeekDays' => $disabledWeekDays]);
    }

    return $summary;
  }

  /**
   * Get the settings from an element.
   *
   * @param array $element
   *   The form element.
   *
   * @return array
   *   The settings array.
   */
  public static function getElementSettings(array $element) {
    $settings = self::getDefaultSettings();

    foreach ($element as $key => $value) {
      if (strpos($key, '#') === 0) {
        $clean_key = substr($key, 1);
        if (array_key_exists($clean_key, $settings)) {
          $settings[$clean_key] = $value;
        }
      }
    }
    return self::processFieldSettings($settings, NULL);
  }

  /**
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *    The Field items.
   * @return string|NULL
   */
  public function getLangcodeFromItems(FieldItemListInterface $items) {
    $lang_code = $items->getLangcode();
    if (in_array($lang_code, AvailableLanguages::LANGUAGES)) {
      return mb_strtolower($lang_code);
    }
    return NULL;
  }

  /**
   * Function of typification options Timepicker.
   *
   * @param array $settings
   *   Settings for JS Timepicker.
   *
   * @return array
   *   return array of changed settings after typefications of all parameters.
   */
  public static function processFieldSettings(array $settings) {
    $options = $settings ?? [];

    if (!empty($options)) {
      $groups = [
        'boolean' => [
          'altInput',
          'allowInput',
          'enableTime',
          'enableSeconds',
          'inline',
          'time_24hr',
          'weekNumbers',
        ],
        'int' => [
          'minTime',
          'maxTime',
          'minuteIncrement',
        ],
        'no_filtering' => [],
      ];
      // Callback for the array_walk().
      $filter = function (&$item, $key, $groups) {
        if (in_array($key, $groups['boolean'], TRUE)) {
          if ($item !== NULL) {
            $item = (bool) $item;
          }
        }
        elseif (in_array($key, $groups['int'], TRUE)) {
          if ($item !== NULL) {
            if (is_array($item)) {
              foreach ($item as $sub_key => $sub_item) {
                if ($item !== NULL) {
                  $item[$sub_key] = (int) $sub_item;
                }
              }
            }
            else {
              $item = (int) $item;
            }
          }
        }
        elseif (is_array($item) || in_array($key, $groups['no_filtering'], TRUE)) {
          // Do nothing.
        }
        else {
          // @todo Use filter_xss_admin() instead?
          $item = Html::escape($item);
        }
      };
      // Filter user submitted settings since plugin builds output by just
      // concatenation of strings so it's possible, for example,
      // to insert html into labels.
      array_walk($options, $filter, $groups);

      $options['dateFormat'] = str_replace('s', 'S', $options['dateFormat']);
      if (isset($options['altFormat'])) {
        $options['altFormat'] = str_replace('s', 'S', $options['altFormat']);
      }
    }

    if (!empty($options['altInput']) && !empty($options['use_system_format']) && !empty($options['system_date_format'])) {
      $date_format = \Drupal::entityTypeManager()
        ->getStorage('date_format')
        ->load($options['system_date_format']);

      if ($date_format) {
        $format = $date_format->getPattern();
        // Convert PHP date format to Flatpickr format.
        $flatpickr_format = self::convertPhpToFlatpickrFormat($format);
        $options['altFormat'] = $flatpickr_format;
      }
    }

    return static::fieldSettingsFinalNullCleanType($options);
  }

  /**
   * Converts PHP date format to Flatpickr format.
   */
  private static function convertPhpToFlatpickrFormat($php_format) {
    $convert = [
      'd' => 'd',    // Day of the month, 2 digits
      'D' => 'D',    // Day name short
      'j' => 'j',    // Day of the month
      'l' => 'l',    // Day name long
      'N' => '',     // ISO-8601 day of week
      'S' => 'S',    // Ordinal suffix
      'w' => 'w',    // Day of week
      'z' => 'z',    // Day of year
      'W' => 'W',    // ISO-8601 week number
      'F' => 'F',    // Month name long
      'm' => 'm',    // Month number
      'M' => 'M',    // Month name short
      'n' => 'n',    // Month number no leading zero
      't' => '',     // Days in month
      'L' => '',     // Leap year
      'o' => 'Y',    // ISO-8601 year
      'Y' => 'Y',    // Full year
      'y' => 'y',    // Two digit year
      'a' => 'K',    // am/pm
      'A' => 'K',    // AM/PM
      'B' => '',     // Swatch internet time
      'g' => 'h',    // 12-hour format no leading zero
      'G' => 'H',    // 24-hour format no leading zero
      'h' => 'h',    // 12-hour format
      'H' => 'H',    // 24-hour format
      'i' => 'i',    // Minutes
      's' => 'S',    // Seconds
      'u' => '',     // Microseconds
      'e' => '',     // Timezone identifier
      'I' => '',     // Daylight saving
      'O' => '',     // Difference to GMT
      'P' => '',     // Difference to GMT with colon
      'T' => '',     // Timezone abbreviation
      'Z' => '',     // Timezone offset seconds
      'c' => '',     // ISO 8601
      'r' => '',     // RFC 2822
      'U' => 'U'     // Seconds since unix epoch
    ];

    $flatpickr_format = '';
    $length = strlen($php_format);

    for ($i = 0; $i < $length; $i++) {
      $char = $php_format[$i];
      if ($char === '\\') {
        $i++;
        $flatpickr_format .= $php_format[$i];
      }
      elseif (isset($convert[$char])) {
        $flatpickr_format .= $convert[$char];
      }
      else {
        $flatpickr_format .= $char;
      }
    }

    return $flatpickr_format;
  }

  /**
   * Method deleting Null parameters before send to JS.
   *
   * @param array $settings
   *   Non-filter parameters.
   *
   * @return array
   *   Returned filtering Parameters for send to JS.
   */
  public static function fieldSettingsFinalNullCleanType(array &$settings) {
    // Convert boolean settings to boolean.
    $boolean = [
      'altInput',
      'allowInput',
      'enableTime',
      'enableSeconds',
      'inline',
      'time_24hr',
      'weekNumbers',
    ];
    foreach ($boolean as $key) {
      if (!empty($settings[$key])) {
        $new[$key] = (bool) $settings[$key];
      }
    }

    if (isset($settings['minTime'])) {
      if ('' !== $settings['minTime']['hour'] && '' !== $settings['minTime']['min']) {
        $new['minTime'] = $settings['minTime']['hour'] . ':' . $settings['minTime']['min'];
      }
    }

    if (isset($settings['maxTime'])) {
      if (!empty($settings['maxTime']['hour']) && !empty($settings['maxTime']['min'])) {
        $new['maxTime'] = $settings['maxTime']['hour'] . ':' . $settings['maxTime']['min'];
      }
    }

    $format_array = [
      'altFormat',
      'dateFormat',
      'minDate',
      'maxDate',
      'minuteIncrement',
      'position',
      'locale',
      'disabledDates',
      'mode',
      'jumpToDate',
    ];

    foreach ($format_array as $key) {
      if (!empty($settings[$key])) {
        $new[$key] = $settings[$key];
      }
    }

    // Special case for Disabled week days, we need an array of numbers.
    $new['disabledWeekDays'] = Checkboxes::getCheckedCheckboxes($settings['disabledWeekDays']);

    if (isset($settings['altInput']) && !$settings['altInput']) {
      unset($new['altInput'], $new['altFormat']);
    }

    return $new;
  }

}
