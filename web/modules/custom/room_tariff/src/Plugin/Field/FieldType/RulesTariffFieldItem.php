<?php

/**
 * @file
 * Contains Drupal\room_tariff\Plugin\Field\FieldType\RulesTariffFieldItem.
 */

namespace Drupal\room_tariff\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * @FieldType(
 *   id = "tariff_rules",
 *   label = @Translation("Rules tariff"),
 *   module = "room_tariff",
 *   description = @Translation("Rules for tariff field."),
 *   category = @Translation("Price"),
 *   default_widget = "tariff_rules_input_widget",
 *   default_formatter = "tariff_rules_default_formatter",
 * )
 */
class RulesTariffFieldItem extends FieldItemBase {
  
  /**
   * {@inheritdoc}
   *
   * We declare the fields for the table where the values of our field will be stored.
   * @see https://www.drupal.org/node/159605
   */
  public static function schema (FieldStorageDefinitionInterface $field_definition) {
    return array(
      'columns' => array(

        'rule_type' => array(
          'type' => 'char',
          'length' => 8,
          'not null' => TRUE,
          'description' => 'Rule type',
        ),

        'pattern_tariff' => array(
          'type' => 'char',
          'length' => 8,
          'not null' => FALSE,
          'default' => null,
          'description' => 'Pattern of tariff field',
        ),

        'span_time' => array(
          'type' => 'int',
          'size' => 'big',
          'unsigned' => TRUE,
          'not null' => FALSE,
          'default' => null,
          'description' => 'Period in seconds',
        ),

        'price' => array(
          'type' => 'int',
          'unsigned' => TRUE,
          'size' => 'normal',
          'not null' => FALSE,
          'default' => 0,
          'description' => 'Price in cents',
        ),

      ),
    );
  }

  /**
   * {@inheritdoc}
   * 
   * This tells Drupal if the field is missing. If NULL then the data will not be saved to the database.
   */
  public function isEmpty() {
    $value = $this->get('price')->getValue();
    if (($value === NULL || $value === '') && $this->get('rule_type')->getValue() == 'minprice') {
      $value = $this->get('span_time')->getValue() ?: null;
    };
    return $value === NULL || $value === '';
  }

  /**
   * {@inheritdoc}
   *
   * This tells Drupal how to store the values for this field. (For example integer, string, or any.)
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['rule_type'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Rule of tariff'))
      ->setDescription(new TranslatableMarkup('The type rule of tarif'))->setRequired(TRUE);
    $properties['pattern_tariff'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Type of tariff field or subtype rule'))
      ->setDescription(new TranslatableMarkup('The type of tarif field'));
    $properties['span_time'] = DataDefinition::create('timestamp')
      ->setLabel(new TranslatableMarkup('Time period'));
    $properties['price'] = DataDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Price'));
    return $properties;
  }

  /**
   * {@inheritdoc}
   * 
   */
  public static function defaultStorageSettings() {
    $defaultStorageSettings = [
      'type_rule_tariff' => [
        'minprice' => 'Min time order',
        'if_large' => 'Price, if more than',
      ],
      'subtype_rule_if_more' => [
        'hours' => 'Hours',
        'days' => 'Days',
      ],
      'type_pattern_tariff' => [
        'per_hour' => 'In an hour',
        'inan_day' => 'In an day',
        'i_person' => 'Per person',
      ],
    ] + parent::defaultStorageSettings();
    return $defaultStorageSettings;
  }


}