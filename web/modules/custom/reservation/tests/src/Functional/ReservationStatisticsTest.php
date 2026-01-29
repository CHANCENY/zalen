<?php

namespace Drupal\Tests\reservation\Functional;

use Drupal\reservation\ReservationManagerInterface;
use Drupal\reservation\Entity\Reservation;
use Drupal\user\RoleInterface;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests reservation statistics on nodes.
 *
 * @group reservation
 */
class ReservationStatisticsTest extends ReservationTestBase {

  /**
   * A secondary user for posting reservations.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $webUser2;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  protected function setUp(): void {
    parent::setUp();

    // Create a second user to post reservations.
    $this->webUser2 = $this->drupalCreateUser([
      'post reservations',
      'create article content',
      'edit own reservations',
      'post reservations',
      'skip reservation approval',
      'access reservations',
      'access content',
    ]);
  }

  /**
   * Tests the node reservation statistics.
   */
  public function testReservationNodeReservationStatistics() {
    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');
    // Set reservations to have subject and preview disabled.
    $this->drupalLogin($this->adminUser);
    $this->setReservationPreview(DRUPAL_DISABLED);
    $this->setReservationForm(TRUE);
    $this->setReservationSubject(FALSE);
    $this->setReservationSettings('default_mode', ReservationManagerInterface::RESERVATION_MODE_THREADED, 'Reservation paging changed.');
    $this->drupalLogout();

    // Checks the initial values of node reservation statistics with no reservation.
    $node = $node_storage->load($this->node->id());
    $this->assertEquals($this->node->getCreatedTime(), $node->get('reservation')->last_reservation_timestamp, 'The initial value of node last_reservation_timestamp is the node created date.');
    $this->assertNull($node->get('reservation')->last_reservation_name, 'The initial value of node last_reservation_name is NULL.');
    $this->assertEquals($this->webUser->id(), $node->get('reservation')->last_reservation_uid, 'The initial value of node last_reservation_uid is the node uid.');
    $this->assertEquals(0, $node->get('reservation')->reservation_count, 'The initial value of node reservation_count is zero.');

    // Post reservation #1 as web_user2.
    $this->drupalLogin($this->webUser2);
    $reservation_text = $this->randomMachineName();
    $this->postReservation($this->node, $reservation_text);

    // Checks the new values of node reservation statistics with reservation #1.
    // The node cache needs to be reset before reload.
    $node_storage->resetCache([$this->node->id()]);
    $node = $node_storage->load($this->node->id());
    $this->assertSame('', $node->get('reservation')->last_reservation_name, 'The value of node last_reservation_name should be an empty string.');
    $this->assertEquals($this->webUser2->id(), $node->get('reservation')->last_reservation_uid, 'The value of node last_reservation_uid is the reservation #1 uid.');
    $this->assertEquals(1, $node->get('reservation')->reservation_count, 'The value of node reservation_count is 1.');

    // Prepare for anonymous reservation submission (reservation approval enabled).
    $this->drupalLogin($this->adminUser);
    user_role_change_permissions(RoleInterface::ANONYMOUS_ID, [
      'access reservations' => TRUE,
      'post reservations' => TRUE,
      'skip reservation approval' => FALSE,
    ]);
    // Ensure that the poster can leave some contact info.
    $this->setReservationAnonymous('1');
    $this->drupalLogout();

    // Post reservation #2 as anonymous (reservation approval enabled).
    $this->drupalGet('reservation/reply/node/' . $this->node->id() . '/reservation');
    $anonymous_reservation = $this->postReservation($this->node, $this->randomMachineName(), '', TRUE);

    // Checks the new values of node reservation statistics with reservation #2 and
    // ensure they haven't changed since the reservation has not been moderated.
    // The node needs to be reloaded with the cache reset.
    $node_storage->resetCache([$this->node->id()]);
    $node = $node_storage->load($this->node->id());
    $this->assertSame('', $node->get('reservation')->last_reservation_name, 'The value of node last_reservation_name should be an empty string.');
    $this->assertEquals($this->webUser2->id(), $node->get('reservation')->last_reservation_uid, 'The value of node last_reservation_uid is still the reservation #1 uid.');
    $this->assertEquals(1, $node->get('reservation')->reservation_count, 'The value of node reservation_count is still 1.');

    // Prepare for anonymous reservation submission (no approval required).
    $this->drupalLogin($this->adminUser);
    user_role_change_permissions(RoleInterface::ANONYMOUS_ID, [
      'access reservations' => TRUE,
      'post reservations' => TRUE,
      'skip reservation approval' => TRUE,
    ]);
    $this->drupalLogout();

    // Post reservation #3 as anonymous.
    $this->drupalGet('reservation/reply/node/' . $this->node->id() . '/reservation');
    $anonymous_reservation = $this->postReservation($this->node, $this->randomMachineName(), '', ['name' => $this->randomMachineName()]);
    $reservation_loaded = Reservation::load($anonymous_reservation->id());

    // Checks the new values of node reservation statistics with reservation #3.
    // The node needs to be reloaded with the cache reset.
    $node_storage->resetCache([$this->node->id()]);
    $node = $node_storage->load($this->node->id());
    $this->assertEquals($reservation_loaded->getAuthorName(), $node->get('reservation')->last_reservation_name, 'The value of node last_reservation_name is the name of the anonymous user.');
    $this->assertEquals(0, $node->get('reservation')->last_reservation_uid, 'The value of node last_reservation_uid is zero.');
    $this->assertEquals(2, $node->get('reservation')->reservation_count, 'The value of node reservation_count is 2.');
  }

}
