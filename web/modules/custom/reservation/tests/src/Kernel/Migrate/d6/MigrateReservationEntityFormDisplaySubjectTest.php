<?php

namespace Drupal\Tests\reservation\Kernel\Migrate\d6;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Tests\migrate_drupal\Kernel\d6\MigrateDrupal6TestBase;

/**
 * Tests the migration of reservation form's subject display from Drupal 6.
 *
 * @group reservation
 * @group migrate_drupal_6
 */
class MigrateReservationEntityFormDisplaySubjectTest extends MigrateDrupal6TestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['reservation'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['reservation']);
    $this->executeMigrations([
      'd6_reservation_type',
      'd6_reservation_entity_form_display_subject',
    ]);
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
    $this->assertSubjectVisible('reservation.reservation_node_article.default');
    $this->assertSubjectVisible('reservation.reservation_node_company.default');
    $this->assertSubjectVisible('reservation.reservation_node_employee.default');
    $this->assertSubjectVisible('reservation.reservation_node_page.default');
    $this->assertSubjectVisible('reservation.reservation_node_sponsor.default');
    $this->assertSubjectNotVisible('reservation.reservation_node_story.default');
    $this->assertSubjectVisible('reservation.reservation_node_test_event.default');
    $this->assertSubjectVisible('reservation.reservation_node_test_page.default');
    $this->assertSubjectVisible('reservation.reservation_node_test_planet.default');
    $this->assertSubjectVisible('reservation.reservation_node_test_story.default');
  }

}
