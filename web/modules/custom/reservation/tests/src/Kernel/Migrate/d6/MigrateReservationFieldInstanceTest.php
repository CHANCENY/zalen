<?php

namespace Drupal\Tests\reservation\Kernel\Migrate\d6;

use Drupal\field\Entity\FieldConfig;
use Drupal\Tests\migrate_drupal\Kernel\d6\MigrateDrupal6TestBase;

/**
 * Tests the migration of reservation field instances from Drupal 6.
 *
 * @group reservation
 * @group migrate_drupal_6
 */
class MigrateReservationFieldInstanceTest extends MigrateDrupal6TestBase {

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
    $this->migrateContentTypes();
    $this->executeMigrations([
      'd6_reservation_type',
      'd6_reservation_field',
      'd6_reservation_field_instance',
    ]);
  }

  /**
   * Asserts a reservation field instance entity.
   *
   * @param string $bundle
   *   The bundle ID.
   * @param string $field_name
   *   The field name.
   * @param int $default_value
   *   The field's default_value setting.
   * @param int $default_mode
   *   The field's default_mode setting.
   * @param int $per_page
   *   The field's per_page setting.
   * @param bool $anonymous
   *   The field's anonymous setting.
   * @param int $form_location
   *   The field's form_location setting.
   * @param bool $preview
   *   The field's preview setting.
   */
  protected function assertEntity($bundle, $field_name, $default_value, $default_mode, $per_page, $anonymous, $form_location, $preview) {
    $entity = FieldConfig::load("node.$bundle.$field_name");
    $this->assertInstanceOf(FieldConfig::class, $entity);
    $this->assertSame('node', $entity->getTargetEntityTypeId());
    $this->assertSame('Reservations', $entity->label());
    $this->assertTrue($entity->isRequired());
    $this->assertSame($bundle, $entity->getTargetBundle());
    $this->assertSame($field_name, $entity->getFieldStorageDefinition()->getName());
    $this->assertSame($default_value, $entity->get('default_value')[0]['status']);
    $this->assertSame($default_mode, $entity->getSetting('default_mode'));
    $this->assertSame($per_page, $entity->getSetting('per_page'));
    $this->assertSame($anonymous, $entity->getSetting('anonymous'));
    $this->assertSame($form_location, $entity->getSetting('form_location'));
    $this->assertSame($preview, $entity->getSetting('preview'));
  }

  /**
   * Test the migrated field instance values.
   */
  public function testMigration() {
    $this->assertEntity('article', 'reservation_node_article', 2, 1, 50, 0, FALSE, 1);
    $this->assertEntity('company', 'reservation_node_company', 2, 1, 50, 0, FALSE, 1);
    $this->assertEntity('employee', 'reservation_node_employee', 2, 1, 50, 0, FALSE, 1);
    $this->assertEntity('event', 'reservation_node_event', 2, 1, 50, 0, FALSE, 1);
    $this->assertEntity('forum', 'reservation_forum', 2, 1, 50, 0, FALSE, 1);
    $this->assertEntity('page', 'reservation_node_page', 0, 1, 50, 0, FALSE, 1);
    $this->assertEntity('sponsor', 'reservation_node_sponsor', 2, 1, 50, 0, FALSE, 1);
    $this->assertEntity('story', 'reservation_node_story', 2, 0, 70, 1, FALSE, 0);
    $this->assertEntity('test_event', 'reservation_node_test_event', 2, 1, 50, 0, FALSE, 1);
    $this->assertEntity('test_page', 'reservation_node_test_page', 2, 1, 50, 0, FALSE, 1);
    $this->assertEntity('test_planet', 'reservation_node_test_planet', 2, 1, 50, 0, FALSE, 1);
    $this->assertEntity('test_story', 'reservation_node_test_story', 2, 1, 50, 0, FALSE, 1);
  }

}
