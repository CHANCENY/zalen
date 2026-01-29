<?php

namespace Drupal\Tests\reservation\Functional;

use Drupal\reservation\Entity\Reservation;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;


/**
 * Tests that reservations behave correctly when the node is changed.
 *
 * @group reservation
 */
class ReservationNodeChangesTest extends ReservationTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that reservations are deleted with the node.
   */
  public function testNodeDeletion() {
    $this->drupalLogin($this->webUser);
    $reservation = $this->postReservation($this->node, $this->randomMachineName(), $this->randomMachineName());
    $this->assertInstanceOf(Reservation::class, $reservation);
    $this->node->delete();
    $this->assertNull(Reservation::load($reservation->id()), 'The reservation could not be loaded after the node was deleted.');
    // Make sure the reservation field storage and all its fields are deleted when
    // the node type is deleted.
    $this->assertNotNull(FieldStorageConfig::load('node.reservation'), 'Reservation field storage exists');
    $this->assertNotNull(FieldConfig::load('node.article.reservation'), 'Reservation field exists');
    // Delete the node type.
    $this->node->get('type')->entity->delete();
    $this->assertNull(FieldStorageConfig::load('node.reservation'), 'Reservation field storage deleted');
    $this->assertNull(FieldConfig::load('node.article.reservation'), 'Reservation field deleted');
  }

}
