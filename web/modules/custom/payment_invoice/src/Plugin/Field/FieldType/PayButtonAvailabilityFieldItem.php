<?php

/**
 * @file
 * Contains Drupal\payment_invoice\Plugin\Field\FieldType\PayButtonAvailabilityFieldItem.
 */

namespace Drupal\payment_invoice\Plugin\Field\FieldType;

use Drupal\payment_invoice\Plugin\Field\FieldType\PayButtonBaseFieldItem;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * @FieldType(
 *   id = "payment_button_availability",
 *   label = @Translation("Payment button availability"),
 *   module = "payment_invoice",
 *   description = @Translation("Custom availability payment button for reservation."),
 *   category = @Translation("Price"),
 *   default_widget = "pay_key_button_field_default_input_widget",
 *   default_formatter = "pay_key_button_field_default_formatter",
 *   cardinality = 1,
 * )
 */
class PayButtonAvailabilityFieldItem extends PayButtonBaseFieldItem {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['value'] = DataDefinition::create('timestamp')
      ->setLabel(new TranslatableMarkup('Timestamp interval confirmation value'));//->setRequired(TRUE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    $defaultFieldSettings = [
      'list_availability' => [
        '0' => 'Instantly',
        '60' => 'Within 1 hour',
        '120' => 'Within 2 hours',
        '360' => 'Within 6 hours',
        '720' => 'Within 12 hours',
        '1440' => 'Within 24 hours',
        '2880' => 'Within 48 hours',
      ],
    ] + parent::defaultFieldSettings();
    return $defaultFieldSettings;
  }

}
