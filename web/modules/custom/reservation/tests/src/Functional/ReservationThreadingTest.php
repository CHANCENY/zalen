<?php

namespace Drupal\Tests\reservation\Functional;

use Drupal\reservation\ReservationManagerInterface;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests to make sure the reservation number increments properly.
 *
 * @group reservation
 */
class ReservationThreadingTest extends ReservationTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'classy';

  /**
   * Tests the reservation threading.
   */
  public function testReservationThreading() {
    // Set reservations to have a subject with preview disabled.
    $this->drupalLogin($this->adminUser);
    $this->setReservationPreview(DRUPAL_DISABLED);
    $this->setReservationForm(TRUE);
    $this->setReservationSubject(TRUE);
    $this->setReservationSettings('default_mode', ReservationManagerInterface::RESERVATION_MODE_THREADED, 'Reservation paging changed.');
    $this->drupalLogout();

    // Create a node.
    $this->drupalLogin($this->webUser);
    $this->node = $this->drupalCreateNode(['type' => 'article', 'promote' => 1, 'uid' => $this->webUser->id()]);

    // Post reservation #1.
    $this->drupalLogin($this->webUser);
    $subject_text = $this->randomMachineName();
    $reservation_text = $this->randomMachineName();

    $reservation1 = $this->postReservation($this->node, $reservation_text, $subject_text, TRUE);
    // Confirm that the reservation was created and has the correct threading.
    $this->assertTrue($this->reservationExists($reservation1), 'Reservation #1. Reservation found.');
    $this->assertEquals('01/', $reservation1->getThread());
    // Confirm that there is no reference to a parent reservation.
    $this->assertNoParentLink($reservation1->id());

    // Post reservation #2 following the reservation #1 to test if it correctly jumps
    // out the indentation in case there is a thread above.
    $subject_text = $this->randomMachineName();
    $reservation_text = $this->randomMachineName();
    $this->postReservation($this->node, $reservation_text, $subject_text, TRUE);

    // Reply to reservation #1 creating reservation #1_3.
    $this->drupalGet('reservation/reply/node/' . $this->node->id() . '/reservation/' . $reservation1->id());
    $reservation1_3 = $this->postReservation(NULL, $this->randomMachineName(), '', TRUE);

    // Confirm that the reservation was created and has the correct threading.
    $this->assertTrue($this->reservationExists($reservation1_3, TRUE), 'Reservation #1_3. Reply found.');
    $this->assertEquals('01.00/', $reservation1_3->getThread());
    // Confirm that there is a link to the parent reservation.
    $this->assertParentLink($reservation1_3->id(), $reservation1->id());

    // Reply to reservation #1_3 creating reservation #1_3_4.
    $this->drupalGet('reservation/reply/node/' . $this->node->id() . '/reservation/' . $reservation1_3->id());
    $reservation1_3_4 = $this->postReservation(NULL, $this->randomMachineName(), $this->randomMachineName(), TRUE);

    // Confirm that the reservation was created and has the correct threading.
    $this->assertTrue($this->reservationExists($reservation1_3_4, TRUE), 'Reservation #1_3_4. Second reply found.');
    $this->assertEquals('01.00.00/', $reservation1_3_4->getThread());
    // Confirm that there is a link to the parent reservation.
    $this->assertParentLink($reservation1_3_4->id(), $reservation1_3->id());

    // Reply to reservation #1 creating reservation #1_5.
    $this->drupalGet('reservation/reply/node/' . $this->node->id() . '/reservation/' . $reservation1->id());

    $reservation1_5 = $this->postReservation(NULL, $this->randomMachineName(), '', TRUE);

    // Confirm that the reservation was created and has the correct threading.
    $this->assertTrue($this->reservationExists($reservation1_5), 'Reservation #1_5. Third reply found.');
    $this->assertEquals('01.01/', $reservation1_5->getThread());
    // Confirm that there is a link to the parent reservation.
    $this->assertParentLink($reservation1_5->id(), $reservation1->id());

    // Post reservation #3 overall reservation #5.
    $this->drupalLogin($this->webUser);
    $subject_text = $this->randomMachineName();
    $reservation_text = $this->randomMachineName();

    $reservation5 = $this->postReservation($this->node, $reservation_text, $subject_text, TRUE);
    // Confirm that the reservation was created and has the correct threading.
    $this->assertTrue($this->reservationExists($reservation5), 'Reservation #5. Second reservation found.');
    $this->assertEquals('03/', $reservation5->getThread());
    // Confirm that there is no link to a parent reservation.
    $this->assertNoParentLink($reservation5->id());

    // Reply to reservation #5 creating reservation #5_6.
    $this->drupalGet('reservation/reply/node/' . $this->node->id() . '/reservation/' . $reservation5->id());
    $reservation5_6 = $this->postReservation(NULL, $this->randomMachineName(), '', TRUE);

    // Confirm that the reservation was created and has the correct threading.
    $this->assertTrue($this->reservationExists($reservation5_6, TRUE), 'Reservation #6. Reply found.');
    $this->assertEquals('03.00/', $reservation5_6->getThread());
    // Confirm that there is a link to the parent reservation.
    $this->assertParentLink($reservation5_6->id(), $reservation5->id());

    // Reply to reservation #5_6 creating reservation #5_6_7.
    $this->drupalGet('reservation/reply/node/' . $this->node->id() . '/reservation/' . $reservation5_6->id());
    $reservation5_6_7 = $this->postReservation(NULL, $this->randomMachineName(), $this->randomMachineName(), TRUE);

    // Confirm that the reservation was created and has the correct threading.
    $this->assertTrue($this->reservationExists($reservation5_6_7, TRUE), 'Reservation #5_6_7. Second reply found.');
    $this->assertEquals('03.00.00/', $reservation5_6_7->getThread());
    // Confirm that there is a link to the parent reservation.
    $this->assertParentLink($reservation5_6_7->id(), $reservation5_6->id());

    // Reply to reservation #5 creating reservation #5_8.
    $this->drupalGet('reservation/reply/node/' . $this->node->id() . '/reservation/' . $reservation5->id());
    $reservation5_8 = $this->postReservation(NULL, $this->randomMachineName(), '', TRUE);

    // Confirm that the reservation was created and has the correct threading.
    $this->assertTrue($this->reservationExists($reservation5_8), 'Reservation #5_8. Third reply found.');
    $this->assertEquals('03.01/', $reservation5_8->getThread());
    // Confirm that there is a link to the parent reservation.
    $this->assertParentLink($reservation5_8->id(), $reservation5->id());
  }

  /**
   * Asserts that the link to the specified parent reservation is present.
   *
   * @param int $cid
   *   The reservation ID to check.
   * @param int $pid
   *   The expected parent reservation ID.
   */
  protected function assertParentLink($cid, $pid) {
    // This pattern matches a markup structure like:
    // <a id="reservation-2"></a>
    // <article>
    //   <p class="parent">
    //     <a href="...reservation-1"></a>
    //   </p>
    //  </article>
    $pattern = "//article[@id='reservation-$cid']//p[contains(@class, 'parent')]//a[contains(@href, 'reservation-$pid')]";

    $this->assertSession()->elementExists('xpath', $pattern);
  }

  /**
   * Asserts that the specified reservation does not have a link to a parent.
   *
   * @param int $cid
   *   The reservation ID to check.
   */
  protected function assertNoParentLink($cid) {
    // This pattern matches a markup structure like:
    // <a id="reservation-2"></a>
    // <article>
    //   <p class="parent"></p>
    //  </article>

    $pattern = "//article[@id='reservation-$cid']//p[contains(@class, 'parent')]";
    $this->assertSession()->elementNotExists('xpath', $pattern);
  }

}
