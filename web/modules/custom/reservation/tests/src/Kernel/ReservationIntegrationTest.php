<?php

namespace Drupal\Tests\reservation\Kernel;

use Drupal\reservation\Entity\Reservation;
use Drupal\reservation\Entity\ReservationType;
use Drupal\Core\Database\Database;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests integration of reservation with other components.
 *
 * @group reservation
 */
class ReservationIntegrationTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'reservation',
    'field',
    'entity_test',
    'user',
    'system',
    'dblog',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('user');
    $this->installEntitySchema('reservation');
    $this->installSchema('dblog', ['watchdog']);
    $this->installSchema('system', ['sequences']);

    // Create a new 'reservation' reservation-type.
    ReservationType::create([
      'id' => 'reservation',
      'label' => $this->randomString(),
      'target_entity_type_id' => 'entity_test',
    ])->save();
  }

  /**
   * Tests view mode setting integration.
   *
   * @see reservation_entity_view_display_presave()
   * @see ReservationDefaultFormatter::calculateDependencies()
   */
  public function testViewMode() {
    $mode = mb_strtolower($this->randomMachineName());
    // Create a new reservation view mode and a view display entity.
    EntityViewMode::create([
      'id' => "reservation.$mode",
      'targetEntityType' => 'reservation',
      'settings' => ['reservation_type' => 'reservation'],
    ])->save();
    EntityViewDisplay::create([
      'targetEntityType' => 'reservation',
      'bundle' => 'reservation',
      'mode' => $mode,
    ])->setStatus(TRUE)->save();

    // Create a reservation field attached to a host 'entity_test' entity.
    FieldStorageConfig::create([
      'entity_type' => 'entity_test',
      'type' => 'reservation',
      'field_name' => $field_name = mb_strtolower($this->randomMachineName()),
      'settings' => [
        'reservation_type' => 'reservation',
      ],
    ])->save();
    FieldConfig::create([
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'field_name' => $field_name,
    ])->save();

    $component = [
      'type' => 'reservation_default',
      'settings' => ['view_mode' => $mode, 'pager_id' => 0],
    ];
    // Create a new 'entity_test' view display on host entity that uses the
    // custom reservation display in field formatter to show the field.
    EntityViewDisplay::create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => 'default',
    ])->setComponent($field_name, $component)->setStatus(TRUE)->save();

    $host_display_id = 'entity_test.entity_test.default';
    $reservation_display_id = "reservation.reservation.$mode";

    // Disable the "reservation.reservation.$mode" display.
    EntityViewDisplay::load($reservation_display_id)->setStatus(FALSE)->save();

    /** @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface $host_display */
    $host_display = EntityViewDisplay::load($host_display_id);

    // Check that the field formatter has been disabled on host view display.
    $this->assertNull($host_display->getComponent($field_name));
    $this->assertTrue($host_display->get('hidden')[$field_name]);

    // Check that the proper warning has been logged.
    $arguments = [
      '@id' => $host_display_id,
      '@name' => $field_name,
      '@display' => EntityViewMode::load("reservation.$mode")->label(),
      '@mode' => $mode,
    ];
    $logged = Database::getConnection()->select('watchdog')
      ->fields('watchdog', ['variables'])
      ->condition('type', 'system')
      ->condition('message', "View display '@id': Reservation field formatter '@name' was disabled because it is using the reservation view display '@display' (@mode) that was just disabled.")
      ->execute()
      ->fetchField();
    $this->assertEquals(serialize($arguments), $logged);

    // Re-enable the reservation view display.
    EntityViewDisplay::load($reservation_display_id)->setStatus(TRUE)->save();
    // Re-enable the reservation field formatter on host entity view display.
    EntityViewDisplay::load($host_display_id)->setComponent($field_name, $component)->save();

    // Delete the "reservation.$mode" view mode.
    EntityViewMode::load("reservation.$mode")->delete();

    // Check that the reservation view display entity has been deleted too.
    $this->assertNull(EntityViewDisplay::load($reservation_display_id));

    /** @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display */
    $host_display = EntityViewDisplay::load($host_display_id);

    // Check that the field formatter has been disabled on host view display.
    $this->assertNull($host_display->getComponent($field_name));
    $this->assertTrue($host_display->get('hidden')[$field_name]);
  }

  /**
   * Test the default owner of reservation entities.
   */
  public function testReservationDefaultOwner() {
    $reservation = Reservation::create([
      'reservation_type' => 'reservation',
    ]);
    $this->assertEquals(0, $reservation->getOwnerId());

    $user = $this->createUser();
    $this->container->get('current_user')->setAccount($user);
    $reservation = Reservation::create([
      'reservation_type' => 'reservation',
    ]);
    $this->assertEquals($user->id(), $reservation->getOwnerId());
  }

}
