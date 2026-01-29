<?php

namespace Drupal\Tests\reservation\Kernel\Migrate\d7;

use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\migrate_drupal\Kernel\d7\MigrateDrupal7TestBase;

/**
 * Tests the migration of reservation fields from Drupal 7.
 *
 * @group reservation
 * @group migrate_drupal_7
 */
class MigrateReservationFieldTest extends MigrateDrupal7TestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'reservation', 'text'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->migrateReservationTypes();
    $this->executeMigration('d7_reservation_field');
  }

  /**
   * Asserts a reservation field entity.
   *
   * @param string $reservation_type
   *   The reservation type.
   */
  protected function assertEntity($reservation_type) {
    $entity = FieldStorageConfig::load('node.' . $reservation_type);
    $this->assertInstanceOf(FieldStorageConfig::class, $entity);
    $this->assertSame('node', $entity->getTargetEntityTypeId());
    $this->assertSame('reservation', $entity->getType());
    $this->assertSame($reservation_type, $entity->getSetting('reservation_type'));
  }

  /**
   * Tests the migrated reservation fields.
   */
  public function testMigration() {
    $this->assertEntity('reservation_node_page');
    $this->assertEntity('reservation_node_article');
    $this->assertEntity('reservation_node_blog');
    $this->assertEntity('reservation_node_book');
    $this->assertEntity('reservation_forum');
    $this->assertEntity('reservation_node_test_content_type');
    $this->assertEntity('reservation_node_et');
  }

}
