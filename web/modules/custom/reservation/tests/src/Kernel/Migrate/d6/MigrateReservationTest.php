<?php

namespace Drupal\Tests\reservation\Kernel\Migrate\d6;

use Drupal\reservation\Entity\Reservation;
use Drupal\reservation\Tests\ReservationTestTrait;
use Drupal\Tests\migrate_drupal\Kernel\d6\MigrateDrupal6TestBase;
use Drupal\node\NodeInterface;

/**
 * Tests the migration of reservations from Drupal 6.
 *
 * @group reservation
 * @group migrate_drupal_6
 */
class MigrateReservationTest extends MigrateDrupal6TestBase {

  use ReservationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'reservation',
    'content_translation',
    'language',
    'menu_ui',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installEntitySchema('reservation');
    $this->installSchema('reservation', ['reservation_entity_statistics']);
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['reservation']);

    $this->migrateContent();
    $this->executeMigrations([
      'language',
      'd6_language_content_settings',
      'd6_node',
      'd6_node_translation',
      'd6_reservation_type',
      'd6_reservation_field',
      'd6_reservation_field_instance',
      'd6_reservation_entity_display',
      'd6_reservation_entity_form_display',
      'd6_reservation',
    ]);
  }

  /**
   * Tests the migrated reservations.
   */
  public function testMigration() {
    $reservation = Reservation::load(1);
    $this->assertSame('The first reservation.', $reservation->getSubject());
    $this->assertSame('The first reservation body.', $reservation->reservation_body->value);
    $this->assertSame('filtered_html', $reservation->reservation_body->format);
    $this->assertSame(NULL, $reservation->pid->target_id);
    $this->assertSame('1', $reservation->getReservationedEntityId());
    $this->assertSame('node', $reservation->getReservationedEntityTypeId());
    $this->assertSame('en', $reservation->language()->getId());
    $this->assertSame('reservation_node_story', $reservation->getTypeId());
    $this->assertSame('203.0.113.1', $reservation->getHostname());

    $node = $reservation->getReservationedEntity();
    $this->assertInstanceOf(NodeInterface::class, $node);
    $this->assertSame('1', $node->id());

    $reservation = Reservation::load(2);
    $this->assertSame('The response to the second reservation.', $reservation->subject->value);
    $this->assertSame('3', $reservation->pid->target_id);
    $this->assertSame('203.0.113.2', $reservation->getHostname());

    $node = $reservation->getReservationedEntity();
    $this->assertInstanceOf(NodeInterface::class, $node);
    $this->assertSame('1', $node->id());

    $reservation = Reservation::load(3);
    $this->assertSame('The second reservation.', $reservation->subject->value);
    $this->assertSame(NULL, $reservation->pid->target_id);
    $this->assertSame('203.0.113.3', $reservation->getHostname());

    $node = $reservation->getReservationedEntity();
    $this->assertInstanceOf(NodeInterface::class, $node);
    $this->assertSame('1', $node->id());

    // Tests that the language of the reservation is migrated from the node.
    $reservation = Reservation::load(7);
    $this->assertSame('Reservation to John Smith - EN', $reservation->subject->value);
    $this->assertSame('This is an English reservation.', $reservation->reservation_body->value);
    $this->assertSame('21', $reservation->getReservationedEntityId());
    $this->assertSame('node', $reservation->getReservationedEntityTypeId());
    $this->assertSame('en', $reservation->language()->getId());

    $node = $reservation->getReservationedEntity();
    $this->assertInstanceOf(NodeInterface::class, $node);
    $this->assertSame('21', $node->id());

    // Tests that the reservation language is correct and that the reservationed entity
    // is correctly migrated when the reservation was posted to a node translation.
    $reservation = Reservation::load(8);
    $this->assertSame('Reservation to John Smith - FR', $reservation->subject->value);
    $this->assertSame('This is a French reservation.', $reservation->reservation_body->value);
    $this->assertSame('21', $reservation->getReservationedEntityId());
    $this->assertSame('node', $reservation->getReservationedEntityTypeId());
    $this->assertSame('fr', $reservation->language()->getId());

    $node = $reservation->getReservationedEntity();
    $this->assertInstanceOf(NodeInterface::class, $node);
    $this->assertSame('21', $node->id());
  }

}
