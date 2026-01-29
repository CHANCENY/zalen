<?php

namespace Drupal\Tests\reservation\Functional\Views;

/**
 * Tests reservation operations.
 *
 * @group reservation
 */
class ReservationOperationsTest extends ReservationTestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_reservation_operations'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'classy';

  /**
   * Test the operations field plugin.
   */
  public function testReservationOperations() {
    $admin_account = $this->drupalCreateUser(['administer reservations']);
    $this->drupalLogin($admin_account);
    $this->drupalGet('test-reservation-operations');
    $this->assertSession()->statusCodeEquals(200);
    $operation = $this->cssSelect('.views-field-operations li.edit a');
    $this->assertCount(1, $operation, 'Found edit operation for reservation.');
    $operation = $this->cssSelect('.views-field-operations li.delete a');
    $this->assertCount(1, $operation, 'Found delete operation for reservation.');
  }

}
