<?php

namespace Drupal\Tests\reservation\Kernel\Migrate\d6;

use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\migrate_drupal\Kernel\d6\MigrateDrupal6TestBase;

/**
 * Tests the migration of reservation fields from Drupal 6.
 *
 * @group reservation
 * @group migrate_drupal_6
 */
class MigrateReservationFieldTest extends MigrateDrupal6TestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['reservation', 'menu_ui'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['reservation']);
    $this->executeMigrations([
      'd6_reservation_type',
      'd6_reservation_field',
    ]);
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
    $this->assertEntity('reservation_node_article');
    $this->assertEntity('reservation_node_company');
    $this->assertEntity('reservation_node_employee');
    $this->assertEntity('reservation_node_event');
    $this->assertEntity('reservation_forum');
    $this->assertEntity('reservation_node_page');
    $this->assertEntity('reservation_node_sponsor');
    $this->assertEntity('reservation_node_story');
    $this->assertEntity('reservation_node_test_event');
    $this->assertEntity('reservation_node_test_page');
    $this->assertEntity('reservation_node_test_planet');
    $this->assertEntity('reservation_node_test_story');
  }

}
