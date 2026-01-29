<?php

namespace Drupal\Tests\reservation\Kernel;

use Drupal\reservation\Entity\ReservationType;
use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Tests that reservation fields cannot be added to entities with non-integer IDs.
 *
 * @group reservation
 */
class ReservationStringIdEntitiesTest extends KernelTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = [
    'reservation',
    'user',
    'field',
    'field_ui',
    'entity_test',
    'text',
  ];

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('reservation');
    $this->installEntitySchema('entity_test_string_id');
    $this->installSchema('reservation', ['reservation_entity_statistics']);
    // Create the reservation body field storage.
    $this->installConfig(['field']);
  }

  /**
   * Tests that reservation fields cannot be added entities with non-integer IDs.
   */
  public function testReservationFieldNonStringId() {
    $this->expectException(\UnexpectedValueException::class);
    $bundle = ReservationType::create([
      'id' => 'foo',
      'label' => 'foo',
      'description' => '',
      'target_entity_type_id' => 'entity_test_string_id',
    ]);
    $bundle->save();
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'foo',
      'entity_type' => 'entity_test_string_id',
      'settings' => [
        'reservation_type' => 'entity_test_string_id',
      ],
      'type' => 'reservation',
    ]);
    $field_storage->save();
  }

}
