<?php

namespace Drupal\Tests\reservation\Kernel\Migrate;

use Drupal\reservation\Entity\ReservationType;
use Drupal\Tests\migrate_drupal\Kernel\MigrateDrupalTestBase;
use Drupal\migrate_drupal\Tests\StubTestTrait;
use Drupal\node\Entity\NodeType;

/**
 * Test stub creation for reservation entities.
 *
 * @group reservation
 */
class MigrateReservationStubTest extends MigrateDrupalTestBase {

  use StubTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['reservation', 'node'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('reservation');
    $this->installEntitySchema('node');
    $this->installSchema('system', ['sequences']);

    // Make sure uid 0 is created (default uid for reservations is 0).
    $storage = \Drupal::entityTypeManager()->getStorage('user');
    // Insert a row for the anonymous user.
    $storage
      ->create([
        'uid' => 0,
        'status' => 0,
        'name' => '',
      ])
      ->save();
    // Need at least one node type and reservation type present.
    NodeType::create([
      'type' => 'testnodetype',
      'name' => 'Test node type',
    ])->save();
    ReservationType::create([
      'id' => 'testreservationtype',
      'label' => 'Test reservation type',
      'target_entity_type_id' => 'node',
    ])->save();
  }

  /**
   * Tests creation of reservation stubs.
   */
  public function testStub() {
    $this->performStubTest('reservation');
  }

}
