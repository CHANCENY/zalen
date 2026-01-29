<?php

namespace Drupal\Tests\reservation\Kernel;

use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\Tests\EntityViewTrait;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Tests loading and rendering orphan reservations.
 *
 * @group reservation
 */
class ReservationOrphanTest extends EntityKernelTestBase {

  use EntityViewTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['reservation', 'node'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('date_format');
    $this->installEntitySchema('reservation');
    $this->installSchema('reservation', ['reservation_entity_statistics']);
  }

  /**
   * Test loading/deleting/rendering orphaned reservations.
   *
   * @dataProvider providerTestOrphan
   */
  public function testOrphan($property) {

    DateFormat::create([
      'id' => 'fallback',
      'label' => 'Fallback',
      'pattern' => 'Y-m-d',
    ])->save();

    $reservation_storage = $this->entityTypeManager->getStorage('reservation');
    $node_storage = $this->entityTypeManager->getStorage('node');

    // Create a page node type.
    $this->entityTypeManager->getStorage('node_type')->create([
      'type' => 'page',
      'name' => 'page',
    ])->save();

    $node = $node_storage->create([
      'type' => 'page',
      'title' => 'test',
    ]);
    $node->save();

    // Create reservation field.
    $this->entityTypeManager->getStorage('field_storage_config')->create([
      'type' => 'text_long',
      'entity_type' => 'node',
      'field_name' => 'reservation',
    ])->save();

    // Add reservation field to page content.
    $this->entityTypeManager->getStorage('field_config')->create([
      'field_storage' => FieldStorageConfig::loadByName('node', 'reservation'),
      'entity_type' => 'node',
      'bundle' => 'page',
      'label' => 'Reservation',
    ])->save();

    // Make two reservations
    $reservation1 = $reservation_storage->create([
      'field_name' => 'reservation',
      'reservation_body' => 'test',
      'entity_id' => $node->id(),
      'entity_type' => 'node',
      'reservation_type' => 'default',
    ])->save();

    $reservation_storage->create([
      'field_name' => 'reservation',
      'reservation_body' => 'test',
      'entity_id' => $node->id(),
      'entity_type' => 'node',
      'reservation_type' => 'default',
      'pid' => $reservation1,
    ])->save();

    // Render the reservations.
    $renderer = \Drupal::service('renderer');
    $reservations = $reservation_storage->loadMultiple();
    foreach ($reservations as $reservation) {
      $built = $this->buildEntityView($reservation, 'full', NULL);
      $renderer->renderPlain($built);
    }

    // Make reservation 2 an orphan by setting the property to an invalid value.
    \Drupal::database()->update('reservation_field_data')
      ->fields([$property => 10])
      ->condition('cid', 2)
      ->execute();
    $reservation_storage->resetCache();
    $node_storage->resetCache();

    // Render the reservations with an orphan reservation.
    $reservations = $reservation_storage->loadMultiple();
    foreach ($reservations as $reservation) {
      $built = $this->buildEntityView($reservation, 'full', NULL);
      $renderer->renderPlain($built);
    }

    $node = $node_storage->load($node->id());
    $built = $this->buildEntityView($node, 'full', NULL);
    $renderer->renderPlain($built);
  }

  /**
   * Provides test data for testOrphan.
   */
  public function providerTestOrphan() {
    return [
      ['entity_id'],
      ['uid'],
      ['pid'],
    ];
  }

}
