<?php

namespace Drupal\Tests\reservation\Kernel\Migrate\d7;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Tests\migrate_drupal\Kernel\d7\MigrateDrupal7TestBase;

/**
 * Tests the migration of reservation form display from Drupal 7.
 *
 * @group reservation
 * @group migrate_drupal_7
 */
class MigrateReservationEntityFormDisplayTest extends MigrateDrupal7TestBase {

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
      'd7_reservation_entity_form_display',
    ]);
  }

  /**
   * Asserts various aspects of a reservation component in an entity form display.
   *
   * @param string $id
   *   The entity ID.
   * @param string $component_id
   *   The ID of the form component.
   */
  protected function assertDisplay($id, $component_id) {
    $component = EntityFormDisplay::load($id)->getComponent($component_id);
    $this->assertIsArray($component);
    $this->assertSame('reservation_default', $component['type']);
    $this->assertSame(20, $component['weight']);
  }

  /**
   * Tests the migrated display configuration.
   */
  public function testMigration() {
    $this->assertDisplay('node.page.default', 'reservation_node_page');
    $this->assertDisplay('node.article.default', 'reservation_node_article');
    $this->assertDisplay('node.book.default', 'reservation_node_book');
    $this->assertDisplay('node.blog.default', 'reservation_node_blog');
    $this->assertDisplay('node.forum.default', 'reservation_forum');
    $this->assertDisplay('node.test_content_type.default', 'reservation_node_test_content_type');
  }

}
