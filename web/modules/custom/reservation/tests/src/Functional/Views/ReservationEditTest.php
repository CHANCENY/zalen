<?php

namespace Drupal\Tests\reservation\Functional\Views;

use Drupal\Tests\reservation\Functional\ReservationTestBase as ReservationBrowserTestBase;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests reservation edit functionality.
 *
 * @group reservation
 */
class ReservationEditTest extends ReservationBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $profile = 'standard';

  /**
   * Tests reservation label in admin view.
   */
  public function testReservationEdit() {
    $this->drupalLogin($this->adminUser);
    // Post a reservation to node.
    $node_reservation = $this->postReservation($this->node, $this->randomMachineName(), $this->randomMachineName(), TRUE);
    $this->drupalGet('admin/content/reservation');
    $this->assertSession()->pageTextContains($this->adminUser->label());
    $this->drupalGet($node_reservation->toUrl('edit-form'));
    $edit = [
      'reservation_body[0][value]' => $this->randomMachineName(),
    ];
    $this->submitForm($edit, 'Save');
    $this->drupalGet('admin/content/reservation');
    $this->assertSession()->pageTextContains($this->adminUser->label());
  }

}
