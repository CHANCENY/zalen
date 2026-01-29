<?php

namespace Drupal\Tests\reservation\Functional;

/**
 * Tests reservation links altering.
 *
 * @group reservation
 */
class ReservationLinksAlterTest extends ReservationTestBase {

  protected static $modules = ['reservation_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  protected function setUp(): void {
    parent::setUp();

    // Enable reservation_test.module's hook_reservation_links_alter() implementation.
    $this->container->get('state')->set('reservation_test_links_alter_enabled', TRUE);
  }

  /**
   * Tests reservation links altering.
   */
  public function testReservationLinksAlter() {
    $this->drupalLogin($this->webUser);
    $reservation_text = $this->randomMachineName();
    $subject = $this->randomMachineName();
    $this->postReservation($this->node, $reservation_text, $subject);

    $this->drupalGet('node/' . $this->node->id());

    $this->assertSession()->linkExists('Report');
  }

}
