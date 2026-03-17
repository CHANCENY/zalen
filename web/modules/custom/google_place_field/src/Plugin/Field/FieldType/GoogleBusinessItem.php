<?php

namespace Drupal\google_place_field\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * @FieldType(
 *   id = "google_place_business",
 *   label = @Translation("Google Place / Business"),
 *   description = @Translation("Stores a Google Place selected via autocomplete."),
 *   default_widget = "google_place_autocomplete",
 *   default_formatter = "string"
 * )
 */
class GoogleBusinessItem extends FieldItemBase {

  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition): array {
    $properties = [];

    $properties['place_id'] = DataDefinition::create('string')
      ->setLabel(t('Place ID'))
      ->setSetting('max_length', 255);

    $properties['name'] = DataDefinition::create('string')
      ->setLabel(t('Business Name'))
      ->addConstraint('Length', ['max' => 255]);

    $properties['address'] = DataDefinition::create('string')
      ->setLabel(t('Address'))
      ->setSetting('max_length', 512);

    $properties['map_url'] = DataDefinition::create('string')
      ->setLabel(t('Google Maps URL'))
      ->setSetting('max_length', 512);

    return $properties;
  }

  public static function schema(FieldStorageDefinitionInterface $field_definition): array {
    return [
      'columns' => [
        'place_id' => [
          'type' => 'varchar',
          'length' => 555,
          'not null' => FALSE,
        ],
        'name' => [
          'type' => 'varchar',
          'length' => 555,
          'not null' => FALSE,
        ],
        // Use TEXT for longer values (recommended)
        'address' => [
          'type' => 'text',
          'size' => 'big',
          'not null' => FALSE,
        ],
        'map_url' => [
          'type' => 'text',
          'size' => 'big',
          'not null' => FALSE,
        ],
      ],
    ];
  }

  public function isEmpty(): bool {
    return empty($this->get('place_id')->getValue());
  }

}
