<?php

namespace Drupal\opentime\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'opening_hours' field type.
 *
 * @FieldType(
 *   id = "opening_hours",
 *   label = @Translation("Opening Hours"),
 *   description = @Translation("Field for specifying opening hours with days of the week."),
 *   default_widget = "opening_hours_widget",
 *   default_formatter = "opening_hours_formatter",
 * )
 */
class OpeningHoursItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $columns = [
      'days' => [
        'type' => 'varchar',
        'length' => 255,
        'not null' => FALSE,
      ],
      'start_time' => [
        'type' => 'varchar',
        'length' => 10,
        'not null' => FALSE,
      ],
      'end_time' => [
        'type' => 'varchar',
        'length' => 10,
        'not null' => FALSE,
      ],
    ];

    return [
      'columns' => $columns,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = [];

    $properties['days'] = DataDefinition::create('string')
      ->setLabel(t('Days'))
      ->setDescription(t('Serialized array of selected days of the week.'));

    $properties['start_time'] = DataDefinition::create('string')
      ->setLabel(t('Start Time'))
      ->setDescription(t('Opening time in HH:MM format.'));

    $properties['end_time'] = DataDefinition::create('string')
      ->setLabel(t('End Time'))
      ->setDescription(t('Closing time in HH:MM format.'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $days = $this->get('days')->getValue();
    $start_time = $this->get('start_time')->getValue();
    $end_time = $this->get('end_time')->getValue();

    if (!empty($days)) {
        $days_decoded = json_decode($days, TRUE);
    } else {
        $days_decoded = [];
    }

    // Controleer of 'days' leeg is.
    $days_empty = empty($days_decoded);

    return $days_empty && empty($start_time) && empty($end_time);
  }
      
}
