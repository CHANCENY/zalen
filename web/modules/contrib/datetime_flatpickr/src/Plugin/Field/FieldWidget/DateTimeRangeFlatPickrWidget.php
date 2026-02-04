<?php

namespace Drupal\datetime_flatpickr\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\datetime_range\Plugin\Field\FieldType\DateRangeItem;
use Drupal\datetime_flatpickr\Constants\AvailableLanguages;

/**
 * Plugin implementation of the 'datetime_flatpickr' widget.
 */
#[FieldWidget(id: 'datetime_range_flatpickr', label: new TranslatableMarkup('Flatpickr datetime range picker'), field_types: ['daterange'])]
class DateTimeRangeFlatPickrWidget extends DateTimeFlatPickrWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element['value'] = $element + [
      '#type' => 'textfield',
      '#default_value' => NULL,
      '#required' => $element['#required'],
      '#date_timezone' => date_default_timezone_get(),
    ];

    if ($this->getFieldSetting('datetime_type') == DateTimeItem::DATETIME_TYPE_DATE) {
      // A date-only field should have no timezone conversion performed, so
      // use the same timezone as for storage.
      $element['value']['#date_timezone'] = DateTimeItemInterface::STORAGE_TIMEZONE;
    }

    if ($items[$delta]->start_date) {
      /** @var \Drupal\Core\Datetime\DrupalDateTime $start_date */
      $start_date = $items[$delta]->start_date;
      $start_date->setTimezone(new \DateTimeZone($element['value']['#date_timezone']));
      $date_start = $this->createDefaultValue($start_date, $element['value']['#date_timezone']);
      $element['value']['#default_value'] = $date_start;
      $default_date[] = $date_start;
    }
    else {
      $default_date[] = '';
    }

    if ($items[$delta]->end_date) {
      /** @var \Drupal\Core\Datetime\DrupalDateTime $end_date */
      $end_date = $items[$delta]->end_date;
      $end_date->setTimezone(new \DateTimeZone($element['value']['#date_timezone']));
      $date_end = $this->createDefaultValue($end_date, $element['value']['#date_timezone']);
      $element['value']['#default_value'] .= ' - ' . $date_end;
      $default_date[] = $date_end;
    }

    $entity_type = $items->getEntity()->getEntityTypeId();
    $name = $entity_type . '-' . $items->getName() . '-' . $delta;

    $settings = self::processFieldSettings($this->getSettings());

    $lang_code = $this->languageManager->getCurrentLanguage()->getId();
    if (in_array($lang_code, AvailableLanguages::LANGUAGES)) {
      $form['value']['#attached']['library'][] = 'datetime_flatpickr/flatpickr_' . mb_strtolower($lang_code);
      $settings['locale'] = $lang_code;
    }

    if (isset($default_date)) {
      $settings['defaultDate'] = $default_date;
    }

    $element['value']['#attributes']['flatpickr-name'] = $name;
    $element['value']['#attached']['library'][] = 'datetime_flatpickr/flatpickr-init';
    $element['value']['#attached']['drupalSettings']['datetimeFlatPickr'][$name] = [
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
    $user_timezone = new \DateTimeZone(date_default_timezone_get());
    $settings = $this->getSettings();

    foreach ($values as &$item) {
      if (!empty($item['value'])) {
        $start_date = $end_date = [];
        $split_date = explode(' ', $item['value']);
        if (count($split_date) < 3) {
          // Input value is a single date.
          $start_date = $end_date = $item['value'];
        }
        else {
          // Input value is a date range.
          while (count($split_date) > 1) {
            $start_date[] = array_shift($split_date);
            array_unshift($end_date, array_pop($split_date));
          }
          $start_date = implode(' ', $start_date);
          $end_date = implode(' ', $end_date);
        }

        try {
          $date_start = DrupalDateTime::createFromFormat($settings['dateFormat'], $start_date);
        }
        catch (\Exception $exception) {
          // Fallback time conversation.
          $timestamp = strtotime($start_date);
          $date_start = DrupalDateTime::createFromTimestamp($timestamp);
        }

        // Adjust the date for storage.
        if ($datetime_type !== DateTimeItem::DATETIME_TYPE_DATE) {
          $date_start->setTimezone($storage_timezone);
        }
        $item['value'] = $date_start->format($storage_format);

        try {
          $date_end = DrupalDateTime::createFromFormat($settings['dateFormat'], $end_date);
        }
        catch (\Exception $exception) {
          // Fallback time conversation.
          $timestamp = strtotime($end_date);
          $date_end = DrupalDateTime::createFromTimestamp($timestamp);
        }
        // Adjust the date for storage.
        if ($datetime_type !== DateTimeItem::DATETIME_TYPE_DATE) {
          $date_end->setTimezone($storage_timezone);
        }
        $item['end_value'] = $date_end->format($storage_format);

      }

      if (!empty($item['value']) && $item['value'] instanceof DrupalDateTime) {
        /** @var \Drupal\Core\Datetime\DrupalDateTime $date_start */
        $date_start = $item['value'];

        if ($datetime_type === DateRangeItem::DATETIME_TYPE_ALLDAY) {
          // All day fields start at midnight on the starting date, but are
          // stored like datetime fields, so we need to adjust the time.
          // This function is called twice, so to prevent a double conversion
          // we need to explicitly set the timezone.
          $date_start->setTimeZone($user_timezone)->setTime(0, 0, 0);
        }

        // Adjust the date for storage.
        $item['value'] = $date_start->setTimezone($storage_timezone)->format($storage_format);
      }

      if (!empty($item['end_value']) && $item['end_value'] instanceof DrupalDateTime) {
        /** @var \Drupal\Core\Datetime\DrupalDateTime $date_end */
        $date_end = $item['end_value'];

        if ($datetime_type === DateRangeItem::DATETIME_TYPE_ALLDAY) {
          // All day fields start at midnight on the starting date, but are
          // stored like datetime fields, so we need to adjust the time.
          // This function is called twice, so to prevent a double conversion
          // we need to explicitly set the timezone.
          $date_end->setTimeZone($user_timezone)->setTime(23, 59, 59);
        }

        // Adjust the date for storage.
        $item['end_value'] = $date_end->setTimezone($storage_timezone)->format($storage_format);
      }
    }

    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public static function fieldSettingsFinalNullCleanType(array &$settings) {
    $new = parent::fieldSettingsFinalNullCleanType($settings);
    $new['mode'] = 'range';

    return $new;
  }

}
