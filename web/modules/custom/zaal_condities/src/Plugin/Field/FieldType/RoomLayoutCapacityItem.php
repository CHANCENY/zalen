<?php

namespace Drupal\zaal_condities\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * @FieldType(
 *   id = "room_layout_capacity",
 *   label = @Translation("Room layout with capacity"),
 *   default_widget = "room_layout_capacity_widget",
 *   default_formatter = "room_layout_capacity_formatter"
 * )
 */
/*class RoomLayoutCapacityItem extends FieldItemBase {

  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['layout'] = DataDefinition::create('integer')->setLabel(t('Layout term ID'));
    $properties['capacity'] = DataDefinition::create('integer')->setLabel(t('Capacity'));
    return $properties;
  }

  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'layout' => ['type' => 'int', 'unsigned' => TRUE],
        'capacity' => ['type' => 'int', 'unsigned' => TRUE],
      ],
    ];
  }

  public function isEmpty() {
    return empty($this->layout) || empty($this->capacity);
  }
}*/

class RoomLayoutCapacityItem extends FieldItemBase {

  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    return [
      'value' => DataDefinition::create('string')
        ->setLabel(t('Layouts JSON')),
    ];
  }

  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'value' => [
          'type' => 'text',
          'size' => 'big',
        ],
      ],
    ];
  }

  public function isEmpty() {
    return empty($this->value);
  }

}

