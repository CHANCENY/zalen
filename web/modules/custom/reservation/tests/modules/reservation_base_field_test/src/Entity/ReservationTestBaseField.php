<?php

namespace Drupal\reservation_base_field_test\Entity;

use Drupal\reservation\Plugin\Field\FieldType\ReservationItemInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\entity_test\Entity\EntityTest;

/**
 * Defines a test entity class for reservation as a base field.
 *
 * @ContentEntityType(
 *   id = "reservation_test_base_field",
 *   label = @Translation("Test reservation - base field"),
 *   base_table = "reservation_test_base_field",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "bundle" = "type"
 *   },
 * )
 */
class ReservationTestBaseField extends EntityTest {

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['test_reservation'] = BaseFieldDefinition::create('reservation')
      ->setLabel(t('A reservation field'))
      ->setSetting('reservation_type', 'test_reservation_type')
      ->setDefaultValue([
        'status' => ReservationItemInterface::OPEN,
      ]);

    return $fields;
  }

}
