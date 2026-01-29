<?php

namespace Drupal\Tests\reservation\Functional\Views;

/**
 * Tests reservations on nodes.
 *
 * @group reservation
 */
class NodeReservationsTest extends ReservationTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = ['history'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_new_reservations', 'test_reservation_count'];

  /**
   * Test the new reservations field plugin.
   */
  public function testNewReservations() {
    $this->drupalGet('test-new-reservations');
    $this->assertSession()->statusCodeEquals(200);
    $new_reservations = $this->cssSelect(".views-field-new-reservations a:contains('1')");
    $this->assertCount(1, $new_reservations, 'Found the number of new reservations for a certain node.');
  }

  /**
   * Test the reservation count field.
   */
  public function testReservationCount() {
    $this->drupalGet('test-reservation-count');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertCount(2, $this->cssSelect('.views-row'));
    $reservation_count_with_reservation = $this->cssSelect(".views-field-reservation-count span:contains('1')");
    $this->assertCount(1, $reservation_count_with_reservation);
    $reservation_count_without_reservation = $this->cssSelect(".views-field-reservation-count span:contains('0')");
    $this->assertCount(1, $reservation_count_without_reservation);

    // Create a content type with no reservation field, and add a node.
    $this->drupalCreateContentType(['type' => 'no_reservation', 'name' => t('No reservation page')]);
    $this->nodeUserPosted = $this->drupalCreateNode(['type' => 'no_reservation']);
    $this->drupalGet('test-reservation-count');

    // Test that the node with no reservation field is also shown.
    $this->assertSession()->statusCodeEquals(200);
    $this->assertCount(3, $this->cssSelect('.views-row'));
    $reservation_count_with_reservation = $this->cssSelect(".views-field-reservation-count span:contains('1')");
    $this->assertCount(1, $reservation_count_with_reservation);
    $reservation_count_without_reservation = $this->cssSelect(".views-field-reservation-count span:contains('0')");
    $this->assertCount(2, $reservation_count_without_reservation);
  }

}
