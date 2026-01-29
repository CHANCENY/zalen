<?php

namespace Drupal\Tests\reservation\Kernel\Migrate\d6;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Tests\migrate_drupal\Kernel\d6\MigrateDrupal6TestBase;

/**
 * Tests the migration of reservation entity displays from Drupal 6.
 *
 * @group reservation
 * @group migrate_drupal_6
 */
class MigrateReservationEntityDisplayTest extends MigrateDrupal6TestBase {

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
      'd6_node_type',
      'd6_reservation_type',
      'd6_reservation_field',
      'd6_reservation_field_instance',
      'd6_reservation_entity_display',
    ]);
  }

  /**
   * Asserts various aspects of a reservation component in an entity view display.
   *
   * @param string $id
   *   The entity ID.
   * @param string $component_id
   *   The ID of the display component.
   */
  protected function assertDisplay($id, $component_id) {
    $component = EntityViewDisplay::load($id)->getComponent($component_id);
    $this->assertIsArray($component);
    $this->assertSame('hidden', $component['label']);
    $this->assertSame('reservation_default', $component['type']);
    $this->assertSame(20, $component['weight']);
  }

  /**
   * Tests the migrated display configuration.
   */
  public function testMigration() {
    $this->assertDisplay('node.article.default', 'reservation_node_article');
    $this->assertDisplay('node.company.default', 'reservation_node_company');
    $this->assertDisplay('node.employee.default', 'reservation_node_employee');
    $this->assertDisplay('node.event.default', 'reservation_node_event');
    $this->assertDisplay('node.forum.default', 'reservation_forum');
    $this->assertDisplay('node.page.default', 'reservation_node_page');
    $this->assertDisplay('node.sponsor.default', 'reservation_node_sponsor');
    $this->assertDisplay('node.story.default', 'reservation_node_story');
    $this->assertDisplay('node.test_event.default', 'reservation_node_test_event');
    $this->assertDisplay('node.test_page.default', 'reservation_node_test_page');
    $this->assertDisplay('node.test_planet.default', 'reservation_node_test_planet');
    $this->assertDisplay('node.test_story.default', 'reservation_node_test_story');
  }

}
