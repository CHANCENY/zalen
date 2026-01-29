<?php

namespace Drupal\Tests\reservation\Functional;

use Drupal\user\RoleInterface;
use  Drupal\Tests\BrowserTestBase;

/**
 * Tests reservation block functionality.
 *
 * @group reservation
 */
class ReservationBlockTest extends ReservationTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = ['block', 'views'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  protected function setUp(): void {
    parent::setUp();
    // Update admin user to have the 'administer blocks' permission.
    $this->adminUser = $this->drupalCreateUser([
      'administer content types',
      'administer reservations',
      'skip reservation approval',
      'post reservations',
      'access reservations',
      'access content',
      'administer blocks',
     ]);
  }

  /**
   * Tests the recent reservations block.
   */
  public function testRecentReservationBlock() {
    $this->drupalLogin($this->adminUser);
    $this->drupalPlaceBlock('views_block:reservations_recent-block_1');

    // Add some test reservations, with and without subjects. Because the 10 newest
    // reservations should be shown by the block, we create 11 to test that behavior
    // below.
    $request_time = \Drupal::time()->getRequestTime();
    $timestamp = $request_time;
    for ($i = 0; $i < 11; ++$i) {
      $subject = ($i % 2) ? $this->randomMachineName() : '';
      $reservations[$i] = $this->postReservation($this->node, $this->randomMachineName(), $subject);
      $reservations[$i]->created->value = $timestamp--;
      $reservations[$i]->save();
    }

    // Test that a user without the 'access reservations' permission cannot see the
    // block.
    $this->drupalLogout();
    user_role_revoke_permissions(RoleInterface::ANONYMOUS_ID, ['access reservations']);
    $this->drupalGet('');
    $this->assertSession()->pageTextNotContains('Recent reservations');
    user_role_grant_permissions(RoleInterface::ANONYMOUS_ID, ['access reservations']);

    // Test that a user with the 'access reservations' permission can see the
    // block.
    $this->drupalLogin($this->webUser);
    $this->drupalGet('');
    $this->assertSession()->pageTextContains('Recent reservations');

    // Test the only the 10 latest reservations are shown and in the proper order.
    $this->assertSession()->pageTextNotContains($reservations[10]->getSubject());
    for ($i = 0; $i < 10; $i++) {
      $this->assertSession()->pageTextContains($reservations[$i]->getSubject());
      if ($i > 1) {
        $previous_position = $position;
        $position = strpos($this->getSession()->getPage()->getContent(), $reservations[$i]->getSubject());
        $this->assertGreaterThan($previous_position, $position, sprintf('Reservation %d does not appear after reservation %d', 10 - $i, 11 - $i));
      }
      $position = strpos($this->getSession()->getPage()->getContent(), $reservations[$i]->getSubject());
    }

    // Test that links to reservations work when reservations are across pages.
    $this->setReservationsPerPage(1);

    for ($i = 0; $i < 10; $i++) {
      $this->clickLink($reservations[$i]->getSubject());
      $this->assertSession()->pageTextContains($reservations[$i]->getSubject());
      $this->assertSession()->responseContains('<link rel="canonical"');
    }
  }

}
