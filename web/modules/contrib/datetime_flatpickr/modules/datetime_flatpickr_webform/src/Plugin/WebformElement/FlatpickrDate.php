<?php

namespace Drupal\datetime_flatpickr_webform\Plugin\WebformElement;

use Drupal\webform\WebformSubmissionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\datetime_flatpickr\Plugin\Field\FieldWidget\DateTimeFlatPickrWidgetTrait;
use Drupal\webform\Plugin\WebformElementBase;

/**
 * Provides a 'flatpickr_date' element.
 *
 * @WebformElement(
 *   id = "flatpickr_date",
 *   api = "https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Render!Element!Date.php/class/Date",
 *   label = @Translation("Flatpickr Date"),
 *   description = @Translation("Provides a form element for date selection using Flatpickr."),
 *   category = @Translation("Date/time elements"),
 * )
 */
class FlatpickrDate extends WebformElementBase {

  use DateTimeFlatPickrWidgetTrait;

  /**
   * {@inheritdoc}
   */
  protected function defineDefaultProperties() {
    $defaults = parent::defineDefaultProperties() + self::getDefaultSettings() + $this->defineDefaultMultipleProperties();
    $defaults['dateFormat'] = 'Y-m-d';
    return $defaults;
  }

  /**
   * Define default multiple properties used by most elements.
   *
   * @return array
   *   An associative array containing default multiple properties.
   */
  protected function defineDefaultMultipleProperties() {
    return [
      'multiple' => FALSE,
      'multiple__header_label' => '',
      'multiple__min_items' => NULL,
      'multiple__empty_items' => 1,
      'multiple__add_more' => TRUE,
      'multiple__add_more_items' => 1,
      'multiple__add_more_button_label' => (string) $this->t('Add'),
      'multiple__add_more_input' => TRUE,
      'multiple__add_more_input_label' => (string) $this->t('more items'),
      'multiple__item_label' => (string) $this->t('item'),
      'multiple__no_items_message' => '<p>' . $this->t('No items entered. Please add items below.') . '</p>',
      'multiple__sorting' => TRUE,
      'multiple__operations' => TRUE,
      'multiple__add' => TRUE,
      'multiple__remove' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function prepare(array &$element, ?WebformSubmissionInterface $webform_submission = NULL) {
    parent::prepare($element, $webform_submission);
    $this->applyFlatpickrSettings($element);
  }

  protected function applyFlatpickrSettings(array &$element) {
    $element['#type'] = 'datetime_flatpickr';
    if (!isset($element['#dateFormat'])) {
      $element['#dateFormat'] = 'Y-m-d';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['flatpickr_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Flatpickr settings'),
      '#open' => TRUE,
    ];

    $flatpickr_form = static::getSettingsForm(self::getDefaultSettings());

    $form['flatpickr_settings'] += $flatpickr_form;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function setDefaultValue(array &$element) {
    parent::setDefaultValue($element);
  }

}
