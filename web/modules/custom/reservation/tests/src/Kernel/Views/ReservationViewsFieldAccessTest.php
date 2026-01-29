<?php

namespace Drupal\Tests\reservation\Kernel\Views;

use Drupal\reservation\Entity\Reservation;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\user\Entity\User;
use Drupal\Tests\views\Kernel\Handler\FieldFieldAccessTestBase;

/**
 * Tests base field access in Views for the reservation entity.
 *
 * @group reservation
 */
class ReservationViewsFieldAccessTest extends FieldFieldAccessTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['reservation', 'entity_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE): void {
    parent::setUp($import_test_views);

    $this->installEntitySchema('reservation');
    $this->installEntitySchema('entity_test');
  }

  /**
   * Check access for reservation fields.
   */
  public function testReservationFields() {
    $user = User::create([
      'name' => 'test user',
    ]);
    $user->save();

    $host = EntityTest::create(['name' => $this->randomString()]);
    $host->save();

    $reservation = Reservation::create([
      'subject' => 'My reservation title',
      'uid' => $user->id(),
      'entity_type' => 'entity_test',
      'field_name' => 'reservation',
      'entity_id' => $host->id(),
      'reservation_type' => 'entity_test',
    ]);
    $reservation->save();

    $reservation_anonymous = Reservation::create([
      'subject' => 'Anonymous reservation title',
      'uid' => 0,
      'name' => 'anonymous',
      'mail' => 'test@example.com',
      'homepage' => 'https://example.com',
      'entity_type' => 'entity_test',
      'field_name' => 'reservation',
      'entity_id' => $host->id(),
      'reservation_type' => 'entity_test',
      'created' => 123456,
      'status' => 1,
    ]);
    $reservation_anonymous->save();

    // @todo Expand the test coverage in https://www.drupal.org/node/2464635

    $this->assertFieldAccess('reservation', 'cid', $reservation->id());
    $this->assertFieldAccess('reservation', 'cid', $reservation_anonymous->id());
    $this->assertFieldAccess('reservation', 'uuid', $reservation->uuid());
    $this->assertFieldAccess('reservation', 'subject', 'My reservation title');
    $this->assertFieldAccess('reservation', 'subject', 'Anonymous reservation title');
    $this->assertFieldAccess('reservation', 'name', 'anonymous');
    $this->assertFieldAccess('reservation', 'mail', 'test@example.com');
    $this->assertFieldAccess('reservation', 'homepage', 'https://example.com');
    $this->assertFieldAccess('reservation', 'uid', $user->getAccountName());
    // $this->assertFieldAccess('reservation', 'created', \Drupal::service('date.formatter')->format(123456));
    // $this->assertFieldAccess('reservation', 'changed', \Drupal::service('date.formatter')->format(REQUEST_TIME));
    $this->assertFieldAccess('reservation', 'status', 'On');
  }

}
