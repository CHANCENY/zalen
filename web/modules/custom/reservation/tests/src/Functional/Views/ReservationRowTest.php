<?php

namespace Drupal\Tests\reservation\Functional\Views;

/**
 * Tests the reservation row plugin.
 *
 * @group reservation
 */
class ReservationRowTest extends ReservationTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_reservation_row'];

  /**
   * Test reservation row.
   */
  public function testReservationRow() {
    $this->drupalGet('test-reservation-row');

    $result = $this->xpath('//article[contains(@class, "reservation")]');
    $this->assertCount(1, $result, 'One rendered reservation found.');
  }

}
