<?php

namespace Drupal\Tests\reservation\Kernel;

use Drupal\reservation\Entity\Reservation;
use Drupal\reservation\Plugin\Field\FieldType\ReservationItemInterface;
use Drupal\reservation\Tests\ReservationTestTrait;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\Tests\field\Kernel\FieldKernelTestBase;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the new entity API for the reservation field type.
 *
 * @group reservation
 */
class ReservationItemTest extends FieldKernelTestBase {

  use ReservationTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['reservation', 'entity_test', 'user'];

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('reservation');
    $this->installSchema('reservation', ['reservation_entity_statistics']);
    $this->installConfig(['reservation']);
  }

  /**
   * Tests using entity fields of the reservation field type.
   */
  public function testReservationItem() {
    $this->addDefaultReservationField('entity_test', 'entity_test', 'reservation');

    // Verify entity creation.
    $entity = EntityTest::create();
    $entity->name->value = $this->randomMachineName();
    $entity->save();

    // Verify entity has been created properly.
    $id = $entity->id();
    $storage = $this->container->get('entity_type.manager')->getStorage('entity_test');
    $storage->resetCache([$id]);
    $entity = $storage->load($id);
    $this->assertInstanceOf(FieldItemListInterface::class, $entity->reservation);
    $this->assertInstanceOf(ReservationItemInterface::class, $entity->reservation[0]);

    // Test sample item generation.
    /** @var \Drupal\entity_test\Entity\EntityTest $entity */
    $entity = EntityTest::create();
    $entity->reservation->generateSampleItems();
    $this->entityValidateAndSave($entity);
    $this->assertContains($entity->get('reservation')->status, [
      ReservationItemInterface::HIDDEN,
      ReservationItemInterface::CLOSED,
      ReservationItemInterface::OPEN,
    ], 'Reservation status value in defined range');

    $mainProperty = $entity->reservation[0]->mainPropertyName();
    $this->assertEquals('status', $mainProperty);
  }

  /**
   * Tests reservation author name.
   */
  public function testReservationAuthorName() {
    $this->installEntitySchema('reservation');
    $this->addDefaultReservationField('entity_test', 'entity_test', 'reservation');

    $host = EntityTest::create(['name' => $this->randomString()]);
    $host->save();

    // Create some reservations.
    $reservation = Reservation::create([
      'subject' => 'My reservation title',
      'uid' => 1,
      'name' => 'entity-test',
      'mail' => 'entity@localhost',
      'entity_type' => 'entity_test',
      'field_name' => 'reservation',
      'entity_id' => $host->id(),
      'reservation_type' => 'entity_test',
      'status' => 1,
    ]);
    $reservation->save();

    // The entity fields for name and mail have no meaning if the user is not
    // Anonymous.
    $this->assertNull($reservation->name->value);
    $this->assertNull($reservation->mail->value);

    $reservation_anonymous = Reservation::create([
      'subject' => 'Anonymous reservation title',
      'uid' => 0,
      'name' => 'barry',
      'mail' => 'test@example.com',
      'homepage' => 'https://example.com',
      'entity_type' => 'entity_test',
      'field_name' => 'reservation',
      'entity_id' => $host->id(),
      'reservation_type' => 'entity_test',
      'status' => 1,
    ]);
    $reservation_anonymous->save();

    // The entity fields for name and mail have retained their values when
    // reservation belongs to an anonymous user.
    $this->assertNotNull($reservation_anonymous->name->value);
    $this->assertNotNull($reservation_anonymous->mail->value);

    $reservation_anonymous->setOwnerId(1)
      ->save();
    // The entity fields for name and mail have no meaning if the user is not
    // Anonymous.
    $this->assertNull($reservation_anonymous->name->value);
    $this->assertNull($reservation_anonymous->mail->value);
  }

}
