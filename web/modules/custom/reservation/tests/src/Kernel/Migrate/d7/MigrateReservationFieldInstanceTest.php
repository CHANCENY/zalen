<?php

namespace Drupal\Tests\reservation\Kernel\Migrate\d7;

use Drupal\field\Entity\FieldConfig;
use Drupal\Tests\migrate_drupal\Kernel\d7\MigrateDrupal7TestBase;

/**
 * Tests the migration of reservation field instances from Drupal 7.
 *
 * @group reservation
 * @group migrate_drupal_7
 */
class MigrateReservationFieldInstanceTest extends MigrateDrupal7TestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'reservation', 'text', 'menu_ui'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->migrateContentTypes();
    $this->migrateReservationTypes();
    $this->executeMigrations([
      'd7_reservation_field',
      'd7_reservation_field_instance',
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
   * Tests the migrated fields.
   */
  public function testMigration() {
    $this->assertEntity('page', 'reservation_node_page', 0, 1, 50, 0, TRUE, 1);
    $this->assertEntity('article', 'reservation_node_article', 2, 1, 50, 0, TRUE, 1);
    $this->assertEntity('blog', 'reservation_node_blog', 2, 1, 50, 0, TRUE, 1);
    $this->assertEntity('book', 'reservation_node_book', 2, 1, 50, 0, TRUE, 1);
    $this->assertEntity('forum', 'reservation_forum', 2, 1, 50, 0, TRUE, 1);
    $this->assertEntity('test_content_type', 'reservation_node_test_content_type', 2, 1, 30, 0, TRUE, 1);
    $this->assertEntity('et', 'reservation_node_et', 2, 1, 50, 0, FALSE, 1);
  }

}
