<?php

namespace Drupal\opentime\Plugin\Field\FieldWidget;

use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Datetime\DateFormatterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'opening_hours_widget' widget.
 *
 * @FieldWidget(
 *   id = "opening_hours_widget",
 *   label = @Translation("Opening Hours Widget"),
 *   field_types = {
 *     "opening_hours"
 *   }
 * )
 */
class OpeningHoursWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'time_format' => 'H:i',
      'minute_increment' => 15,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $value = $items[$delta]->getValue();

    $element['days'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Days of the Week'),
      '#options' => [
        'monday' => $this->t('Monday'),
        'tuesday' => $this->t('Tuesday'),
        'wednesday' => $this->t('Wednesday'),
        'thursday' => $this->t('Thursday'),
        'friday' => $this->t('Friday'),
        'saturday' => $this->t('Saturday'),
        'sunday' => $this->t('Sunday'),
      ],
      
      '#default_value' => isset($items[$delta]->days) ? json_decode($items[$delta]->days, TRUE) : [],
      
    ];

    $element['start_time'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Opening Time'),
      '#default_value' => $value['start_time'] ?? '',
      '#attributes' => [
        'class' => ['timepicker'],
      ],
    ];

    $element['end_time'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Closing Time'),
      '#default_value' => $value['end_time'] ?? '',
      '#attributes' => [
        'class' => ['timepicker'],
      ],
    ];

    $element['#theme_wrappers'] = [];
    //$element['#theme_wrappers'] = ['opening_hours_widget'];
    $element['#tree'] = TRUE;  
          
    $element['#attached']['library'][] = 'opentime/opentime_timepicker';
    
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = [];

    $elements['time_format'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Time Format'),
      '#default_value' => $this->getSetting('time_format'),
      '#description' => $this->t('Specify the time format, e.g., H:i for 24-hour format.'),
    ];

    $elements['minute_increment'] = [
      '#type' => 'number',
      '#title' => $this->t('Minute Increment'),
      '#default_value' => $this->getSetting('minute_increment'),
      '#description' => $this->t('Set the minute increment for the timepicker, e.g., 15 for 15-minute intervals.'),
      '#min' => 1,
      '#step' => 1,
    ];

    return $elements;
  }

  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    foreach ($values as &$value) {
      if (isset($value['days']) && is_array($value['days'])) {
        // Filter out unchecked days (values that are 0)
        $days_selected = array_filter($value['days'], function($day) {
          return $day !== 0;
        });
        // Store as JSON string if not empty
        if (!empty($days_selected)) {
          $value['days'] = json_encode(array_values($days_selected));
        } else {
          $value['days'] = NULL;
        }
      } else {
        $value['days'] = NULL;
      }
      // Ensure 'start_time' and 'end_time' are strings
      $value['start_time'] = isset($value['start_time']) ? (string) $value['start_time'] : '';
      $value['end_time'] = isset($value['end_time']) ? (string) $value['end_time'] : '';
    }
    return $values;
  }
    
}
