<?php

namespace Drupal\Tests\reservation\Kernel\Migrate\d7;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Tests\migrate_drupal\Kernel\d7\MigrateDrupal7TestBase;

/**
 * Tests the migration of reservation form's subject display from Drupal 7.
 *
 * @group reservation
 * @group migrate_drupal_7
 */
class MigrateReservationEntityFormDisplaySubjectTest extends MigrateDrupal7TestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'reservation', 'text', 'menu_ui'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->migrateReservationTypes();
    $this->executeMigration('d7_reservation_entity_form_display_subject');
  }

  /**
   * Asserts that the reservation subject field is visible for a node type.
   *
   * @param string $id
   *   The entity form display ID.
   */
  protected function assertSubjectVisible($id) {
    $component = EntityFormDisplay::load($id)->getComponent('subject');
    $this->assertIsArray($component);
    $this->assertSame('string_textfield', $component['type']);
    $this->assertSame(10, $component['weight']);
  }

  /**
   * Asserts that the reservation subject field is not visible for a node type.
   *
   * @param string $id
   *   The entity form display ID.
   */
  protected function assertSubjectNotVisible($id) {
    $component = EntityFormDisplay::load($id)->getComponent('subject');
    $this->assertNull($component);
  }

  /**
   * Tests the migrated display configuration.
   */
  public function testMigration() {
    $this->assertSubjectVisible('reservation.reservation_node_page.default');
    $this->assertSubjectVisible('reservation.reservation_node_article.default');
    $this->assertSubjectVisible('reservation.reservation_node_book.default');
    $this->assertSubjectVisible('reservation.reservation_node_blog.default');
    $this->assertSubjectVisible('reservation.reservation_forum.default');
    $this->assertSubjectNotVisible('reservation.reservation_node_test_content_type.default');
  }

}
