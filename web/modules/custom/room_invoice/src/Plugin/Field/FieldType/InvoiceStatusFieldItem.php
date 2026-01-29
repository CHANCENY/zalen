<?php

/**
 * @file
 * Contains Drupal\room_invoice\Plugin\Field\FieldType\InvoiceStatusFieldItem.
 */

namespace Drupal\room_invoice\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines the 'InvoiceStatus' field type.
 *
 * @FieldType(
 *   id = "invoice_status",
 *   label = @Translation("Invoice status"),
 *   no_ui = TRUE,
 *   description = @Translation("An field containing a timestamp and status values."),
 *   default_widget = "invoice_status_input",
 *   default_formatter = "invoice_status_formatter",
 * )
 */
class InvoiceStatusFieldItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   *
   * @see https://www.drupal.org/node/159605
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return array(
      'columns' => array(
        'date' => array(
          'type' => 'int',
          'description' => 'The timestamp for Invoice status value.',
          'not null' => TRUE,
        ),
        'meaning' => array(//sense
          'type' => 'varchar',
          'length' => 32,
          'description' => 'The Invoice status value.',
          'not null' => TRUE,
        ),
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['date'] = DataDefinition::create('timestamp')->setLabel(new TranslatableMarkup('Timestamp value for Invoice status'))->setDescription(new TranslatableMarkup('order status creation timestamp.'))
      ->setRequired(TRUE);
    $properties['meaning'] = DataDefinition::create('string')->setLabel(new TranslatableMarkup('Invoice status'))->setDescription(new TranslatableMarkup('Order status that comes from the payment provider.'))
      ->setRequired(TRUE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $date = $this->get('date')->getValue();
    $status = $this->get('meaning')->getValue();
    return ($date === NULL || $date === '') || ($status === NULL || $status === '');
  }


}
