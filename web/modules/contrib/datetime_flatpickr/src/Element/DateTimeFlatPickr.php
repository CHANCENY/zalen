<?php

namespace Drupal\datetime_flatpickr\Element;

use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\Textfield;
use Drupal\datetime_flatpickr\Plugin\Field\FieldWidget\DateTimeFlatPickrWidgetTrait;
use Drupal\datetime_flatpickr\Constants\AvailableLanguages;

/**
 * DateTime FlatPickr.
 */
#[FormElement('datetime_flatpickr')]
class DateTimeFlatPickr extends Textfield {

  use DateTimeFlatPickrWidgetTrait;

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = static::class;
    $info = parent::getInfo();
    $info['#process'][] = [$class, 'processDateTimeFlatPicker'];
    return $info;
  }

  /**
   * Process the DateTime FlatPickr element.
   *
   * @param array $element
   *   The element to process.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return array
   *   The processed element.
   */
  public static function processDateTimeFlatPicker(&$element, FormStateInterface $form_state) {
    $name = $element['#name'];
    $element['#attributes']['flatpickr-name'] = $name;
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function preRenderTextfield($element): array {
    $element = parent::preRenderTextfield($element);
    $element['#attached']['library'][] = 'datetime_flatpickr/flatpickr-init';
    $lang_code = \Drupal::languageManager()->getCurrentLanguage()->getId();

    // Apply language code mapping.
    $flatpickr_lang = AvailableLanguages::LANGUAGE_MAPPING[$lang_code] ?? $lang_code;

    $settings = self::getElementSettings($element);
    if (in_array($flatpickr_lang, AvailableLanguages::LANGUAGES)) {
      $element['#attached']['library'][] = 'datetime_flatpickr/flatpickr_' . mb_strtolower($flatpickr_lang);
      $settings['locale'] = $flatpickr_lang;
    }

    $element['#attached']['drupalSettings']['datetimeFlatPickr'][$element['#name']] = [
      'settings' => $settings,
    ];
    return $element;
  }

}
