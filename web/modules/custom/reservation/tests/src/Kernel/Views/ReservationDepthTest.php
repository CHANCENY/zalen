<?php

namespace Drupal\Tests\reservation\Kernel\Views;

use Drupal\reservation\ReservationManagerInterface;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\views\Views;

/**
 * Tests the depth of the reservation field handler.
 *
 * @group reservation
 */
class ReservationDepthTest extends ReservationViewsKernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['entity_test'];

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_reservation'];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE): void {
    parent::setUp($import_test_views);

    $this->installEntitySchema('entity_test');
  }

  /**
   * Test the reservation depth.
   */
  public function testReservationDepth() {
    $this->enableModules(['field']);
    $this->installConfig(['field']);

    // Create a reservation field storage.
    $field_storage_reservation = FieldStorageConfig::create([
      'field_name' => 'reservation',
      'type' => 'reservation',
      'entity_type' => 'entity_test',
    ]);
    $field_storage_reservation->save();

    // Create a reservation field which allows threading.
    $field_reservation = FieldConfig::create([
      'field_name' => 'reservation',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'settings' => [
        'default_mode' => ReservationManagerInterface::RESERVATION_MODE_THREADED,
      ],
    ]);
    $field_reservation->save();

    // Create a test entity.
    $host = EntityTest::create(['name' => $this->randomString()]);
    $host->save();

    // Create the thread of reservations.
    $reservation1 = $this->reservationStorage->create([
      'uid' => $this->adminUser->id(),
      'entity_type' => 'entity_test',
      'entity_id' => $host->id(),
      'reservation_type' => 'entity_test',
      'field_name' => $field_storage_reservation->getName(),
      'status' => 1,
    ]);
    $reservation1->save();

    $reservation2 = $this->reservationStorage->create([
      'uid' => $this->adminUser->id(),
      'entity_type' => 'entity_test',
      'entity_id' => $host->id(),
      'reservation_type' => 'entity_test',
      'field_name' => $field_storage_reservation->getName(),
      'status' => 1,
      'pid' => $reservation1->id(),
    ]);
    $reservation2->save();

    $reservation3 = $this->reservationStorage->create([
      'uid' => $this->adminUser->id(),
      'entity_type' => 'entity_test',
      'entity_id' => $host->id(),
      'reservation_type' => 'entity_test',
      'field_name' => $field_storage_reservation->getName(),
      'status' => 1,
      'pid' => $reservation2->id(),
    ]);
    $reservation3->save();

    $view = Views::getView('test_reservation');
    $view->setDisplay();

    $view->displayHandlers->get('default')->overrideOption('fields', [
      'thread' => [
        'table' => 'reservation_field_data',
        'field' => 'thread',
        'id' => 'thread',
        'plugin_id' => 'reservation_depth',
        'entity_type' => 'reservation',
      ],
    ]);
    $view->save();

    $view->preview();

    // Check if the depth of the first reservation is 0.
    $reservation1_depth = $view->style_plugin->getField(0, 'thread');
    $this->assertEquals(0, (string) $reservation1_depth, "The depth of the first reservation is 0.");

    // Check if the depth of the first reservation is 1.
    $reservation2_depth = $view->style_plugin->getField(1, 'thread');
    $this->assertEquals(1, (string) $reservation2_depth, "The depth of the second reservation is 1.");

    // Check if the depth of the first reservation is 2.
    $reservation3_depth = $view->style_plugin->getField(2, 'thread');
    $this->assertEquals(2, (string) $reservation3_depth, "The depth of the third reservation is 2.");
  }

}
