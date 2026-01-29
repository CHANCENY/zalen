<?php

/**
 * @file
 * Contains Drupal\payment_invoice\Plugin\Field\FieldType\PayButtonBaseFieldItem.
 */

namespace Drupal\payment_invoice\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Base class for PayButton field types.
 */
class PayButtonBaseFieldItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   * @see https://www.drupal.org/node/159605
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return array(
      'columns' => array(
        'value' => array(
          'type' => 'int',
          'unsigned' => TRUE,
          'description' => 'The timestamp for PayButton value.',
          'not null' => FALSE,
        ),
      ),
      'indexes' => array(
        'value' => ['value'],
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['value'] = DataDefinition::create('timestamp')
      ->setLabel(new TranslatableMarkup('Timestamp value for PayButton'));

    return $properties;
  }

  /** {@inheritdoc} */
  public function isEmpty() {
    $value = $this->get('value')->getValue();
    return $value === NULL || $value === '';
  }

}
