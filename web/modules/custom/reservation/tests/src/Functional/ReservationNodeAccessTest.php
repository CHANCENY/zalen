<?php

namespace Drupal\Tests\reservation\Functional;

use Drupal\reservation\ReservationManagerInterface;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests reservations with node access.
 *
 * Verifies there is no PostgreSQL error when viewing a node with threaded
 * reservations (a reservation and a reply), if a node access module is in use.
 *
 * @group reservation
 */
class ReservationNodeAccessTest extends ReservationTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = ['node_access_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'classy';

  protected function setUp(): void {
    parent::setUp();

    node_access_rebuild();

    // Re-create user.
    $this->webUser = $this->drupalCreateUser([
      'access reservations',
      'post reservations',
      'create article content',
      'edit own reservations',
      'node test view',
      'skip reservation approval',
    ]);

    // Set the author of the created node to the web_user uid.
    $this->node->setOwnerId($this->webUser->id())->save();
  }

  /**
   * Test that threaded reservations can be viewed.
   */
  public function testThreadedReservationView() {
    // Set reservations to have subject required and preview disabled.
    $this->drupalLogin($this->adminUser);
    $this->setReservationPreview(DRUPAL_DISABLED);
    $this->setReservationForm(TRUE);
    $this->setReservationSubject(TRUE);
    $this->setReservationSettings('default_mode', ReservationManagerInterface::RESERVATION_MODE_THREADED, 'Reservation paging changed.');
    $this->drupalLogout();

    // Post reservation.
    $this->drupalLogin($this->webUser);
    $reservation_text = $this->randomMachineName();
    $reservation_subject = $this->randomMachineName();
    $reservation = $this->postReservation($this->node, $reservation_text, $reservation_subject);
    $this->assertTrue($this->reservationExists($reservation), 'Reservation found.');

    // Check reservation display.
    $this->drupalGet('node/' . $this->node->id());
    $this->assertSession()->pageTextContains($reservation_subject);
    $this->assertSession()->pageTextContains($reservation_text);

    // Reply to reservation, creating second reservation.
    $this->drupalGet('reservation/reply/node/' . $this->node->id() . '/reservation/' . $reservation->id());
    $reply_text = $this->randomMachineName();
    $reply_subject = $this->randomMachineName();
    $reply = $this->postReservation(NULL, $reply_text, $reply_subject, TRUE);
    $this->assertTrue($this->reservationExists($reply, TRUE), 'Reply found.');

    // Go to the node page and verify reservation and reply are visible.
    $this->drupalGet('node/' . $this->node->id());
    $this->assertSession()->pageTextContains($reservation_text);
    $this->assertSession()->pageTextContains($reservation_subject);
    $this->assertSession()->pageTextContains($reply_text);
    $this->assertSession()->pageTextContains($reply_subject);
  }

}
