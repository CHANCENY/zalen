<?php

namespace Drupal\Tests\reservation\Kernel;

use Drupal\reservation\ReservationInterface;
use Drupal\reservation\Entity\Reservation;
use Drupal\reservation\Plugin\Field\FieldType\ReservationItemInterface;
use Drupal\reservation_base_field_test\Entity\ReservationTestBaseField;
use Drupal\Core\Language\LanguageInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests that reservation as a base field.
 *
 * @group reservation
 */
class ReservationBaseFieldTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'reservation',
    'reservation_base_field_test',
  ];

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('reservation_test_base_field');
    $this->installEntitySchema('reservation');
    $this->installSchema('system', ['sequences']);
    $this->installEntitySchema('user');
  }

  /**
   * Tests reservation as a base field.
   */
  public function testReservationBaseField() {
    // Verify entity creation.
    $entity = ReservationTestBaseField::create([
      'name' => $this->randomMachineName(),
      'test_reservation' => ReservationItemInterface::OPEN,
    ]);
    $entity->save();

    $reservation = Reservation::create([
      'entity_id' => $entity->id(),
      'entity_type' => 'reservation_test_base_field',
      'field_name' => 'test_reservation',
      'pid' => 0,
      'uid' => 0,
      'status' => ReservationInterface::PUBLISHED,
      'subject' => $this->randomMachineName(),
      'hostname' => '127.0.0.1',
      'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
      'reservation_body' => [['value' => $this->randomMachineName()]],
    ]);
    $reservation->save();
    $this->assertEquals('test_reservation_type', $reservation->bundle());
  }

}
