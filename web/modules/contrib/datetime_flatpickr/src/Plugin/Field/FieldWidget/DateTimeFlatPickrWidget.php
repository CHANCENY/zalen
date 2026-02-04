<?php

namespace Drupal\datetime_flatpickr\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\datetime\Plugin\Field\FieldWidget\DateTimeWidgetBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\datetime_flatpickr\Constants\AvailableLanguages;

/**
 * Plugin implementation of the 'datetime_flatpickr' widget.
 */
#[FieldWidget(id: 'datetime_flatpickr', label: new TranslatableMarkup('Flatpickr datetime picker'), field_types: ['datetime'])]
class DateTimeFlatPickrWidget extends DateTimeWidgetBase {

  use DateTimeFlatPickrWidgetTrait;

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

    if ($items[$delta]->date) {
      $date = $items[$delta]->date;
      // The date was created and verified during field_load(), so it is safe to
      // use without further inspection.
      $date->setTimezone(new \DateTimeZone($element['value']['#date_timezone']));
      $element['value']['#default_value'] = $this->createDefaultValue($date, $element['value']['#date_timezone']);
    }

    $entity_type = $items->getEntity()->getEntityTypeId();
    $name = $entity_type . '-' . $items->getName() . '-' . $delta;
    $element['value']['#attributes']['flatpickr-name'] = $name;
    $element['value']['#attached']['library'][] = 'datetime_flatpickr/flatpickr-init';

    $settings = self::processFieldSettings($this->getSettings());

    $lang_code = $this->languageManager->getCurrentLanguage()->getId();
    if (in_array($lang_code, AvailableLanguages::LANGUAGES)) {
      $form['value']['#attached']['library'][] = 'datetime_flatpickr/flatpickr_' . mb_strtolower($lang_code);
      $settings['locale'] = $lang_code;
    }

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
    $settings = $this->getSettings();

    foreach ($values as &$item) {
      if (!empty($item['value'])) {
        try {
          $date = DrupalDateTime::createFromFormat($settings['dateFormat'], $item['value']);
        }
        catch (\Exception $exception) {
          // Fallback time conversation.
          $timestamp = strtotime($item['value']);
          $date = DrupalDateTime::createFromTimestamp($timestamp);
        }

        // Adjust the date for storage.
        if ($datetime_type !== DateTimeItem::DATETIME_TYPE_DATE) {
          $date->setTimezone($storage_timezone);
        }
        $item['value'] = $date->format($storage_format);
      }

      if (!empty($item['value']) && $item['value'] instanceof DrupalDateTime) {
        /** @var \Drupal\Core\Datetime\DrupalDateTime $date */
        $date = $item['value'];

        // Adjust the date for storage.
        if ($datetime_type !== DateTimeItem::DATETIME_TYPE_DATE) {
          $date->setTimezone($storage_timezone);
        }
        $item['value'] = $date->format($storage_format);
      }
    }

    return $values;
  }

}
