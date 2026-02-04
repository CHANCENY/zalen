<?php

namespace Drupal\datetime_flatpickr_bef\Plugin\better_exposed_filters\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\datetime_flatpickr\Plugin\Field\FieldWidget\DateTimeFlatPickrWidgetTrait;
use Drupal\better_exposed_filters\Plugin\better_exposed_filters\filter\FilterWidgetBase;

/**
 * Date picker widget implementation.
 *
 * @BetterExposedFiltersFilterWidget(
 *   id = "bef_flatpickr",
 *   label = @Translation("Date Picker with Flatpickr"),
 * )
 */
class FlatpickrDateBef extends FilterWidgetBase {

  use LoggerChannelTrait;
  use DateTimeFlatPickrWidgetTrait;

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(mixed $handler = NULL, array $options = []): bool {
    /** @var \Drupal\views\Plugin\views\filter\FilterPluginBase $handler */
    $is_applicable = FALSE;

    if ((is_a($handler, 'Drupal\views\Plugin\views\filter\Date') || !empty($handler->date_handler)) && !$handler->isAGroup()) {
      $is_applicable = TRUE;
    }

    return $is_applicable;
  }

  /**
   * {@inheritdoc}
   *
   * Store the Flatpickr sub-form values into $this->configuration.
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);

    $flatpickr_values = $form_state->getValue('flatpickr_settings', []);

    foreach ($flatpickr_values as $key => $value) {
      $this->configuration[$key] = $value;
    }
  }

  /**
   * {@inheritdoc}
   *
   * Merge the trait's default Flatpickr settings into the parent defaults.
   */
  public function defaultConfiguration():array {
    $defaults = parent::defaultConfiguration();

    $defaults += static::getDefaultSettings();
    $defaults['dateFormat'] = 'Y-m-d';

    return $defaults;
  }


  /**
   * {@inheritdoc}
   *
   * Build a form that includes the trait’s settingsForm elements
   * inside a "details" wrapper named 'flatpickr_settings'.
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state):array {
    $form = parent::buildConfigurationForm($form, $form_state);

    $options = $this->configuration;

    $form['flatpickr_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Flatpickr settings'),
      '#open' => TRUE,
    ];

    $flatpickr_form = static::getSettingsForm($options);

    $form['flatpickr_settings'] += $flatpickr_form;

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * Alter the exposed form elements to use our Flatpickr-based type.
   */
  public function exposedFormAlter(array &$form, FormStateInterface $form_state): void {
    $field_id = $this->getExposedFilterFieldId();
    $wrapper_id = $field_id . '_wrapper';

    if (!isset($form[$field_id]) && isset($form[$wrapper_id])) {
      $element = &$form[$wrapper_id][$field_id];
    }
    else {
      $element = &$form[$field_id];
    }

    parent::exposedFormAlter($form, $form_state);

    $is_double_date = isset($element['min'], $element['max'])
      && isset($element['min']['#type'])
      && isset($element['max']['#type']);

    if (!$is_double_date) {
      $this->applyFlatpickrSettings($element);
    }
    elseif ($is_double_date) {
      $this->applyFlatpickrSettings($element['min']);
      $this->applyFlatpickrSettings($element['max']);
    }
    else {
      $this->applyFlatpickrSettings($element);
    }
  }

  /**
   * Helper to apply the trait’s config to an element.
   */
  protected function applyFlatpickrSettings(array &$element) {
    $element['#type'] = 'datetime_flatpickr';

    foreach ($this->configuration as $key => $value) {
      $element['#' . $key] = $value;
    }
  }

}
