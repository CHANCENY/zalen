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

  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = [];
    $properties['place_id'] = DataDefinition::create('string')->setLabel(t('Place ID'));
    $properties['name'] = DataDefinition::create('string')->setLabel(t('Business Name'));
    $properties['address'] = DataDefinition::create('string')->setLabel(t('Address'));
    $properties['map_url'] = DataDefinition::create('string')->setLabel(t('Google Maps URL'));
    return $properties;
  }

  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'place_id' => ['type' => 'varchar', 'length' => 255, 'not null' => FALSE],
        'name' => ['type' => 'varchar', 'length' => 255, 'not null' => FALSE],
        'address' => ['type' => 'varchar', 'length' => 512, 'not null' => FALSE],
        'map_url' => ['type' => 'varchar', 'length' => 512, 'not null' => FALSE],
      ],
    ];
  }

  public function isEmpty() {
    $place_id = $this->get('place_id')->getValue();
    return $place_id === NULL || $place_id === '';
  }
}
