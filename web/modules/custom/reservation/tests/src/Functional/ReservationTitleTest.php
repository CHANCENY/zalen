<?php

namespace Drupal\Tests\reservation\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests to ensure that appropriate and accessible markup is created for reservation
 * titles.
 *
 * @group reservation
 */
class ReservationTitleTest extends ReservationTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'classy';

  /**
   * Tests markup for reservations with empty titles.
   */
  public function testReservationEmptyTitles() {
    // Installs module that sets reservations to an empty string.
    \Drupal::service('module_installer')->install(['reservation_empty_title_test']);

    // Set reservations to have a subject with preview disabled.
    $this->setReservationPreview(DRUPAL_DISABLED);
    $this->setReservationForm(TRUE);
    $this->setReservationSubject(TRUE);

    // Create a node.
    $this->drupalLogin($this->webUser);
    $this->node = $this->drupalCreateNode(['type' => 'article', 'promote' => 1, 'uid' => $this->webUser->id()]);

    // Post reservation #1 and verify that h3's are not rendered.
    $subject_text = $this->randomMachineName();
    $reservation_text = $this->randomMachineName();
    $reservation = $this->postReservation($this->node, $reservation_text, $subject_text, TRUE);

    // The entity fields for name and mail have no meaning if the user is not
    // Anonymous.
    $this->assertNull($reservation->name->value);
    $this->assertNull($reservation->mail->value);

    // Confirm that the reservation was created.
    $regex = '/<article(.*?)id="reservation-' . $reservation->id() . '"(.*?)';
    $regex .= $reservation->reservation_body->value . '(.*?)';
    $regex .= '/s';
    // Verify that the reservation is created successfully.
    $this->assertSession()->responseMatches($regex);
    // Tests that markup is not generated for the reservation without header.
    $this->assertSession()->responseNotMatches('|<h3[^>]*></h3>|');
  }

  /**
   * Tests markup for reservations with populated titles.
   */
  public function testReservationPopulatedTitles() {
    // Set reservations to have a subject with preview disabled.
    $this->setReservationPreview(DRUPAL_DISABLED);
    $this->setReservationForm(TRUE);
    $this->setReservationSubject(TRUE);

    // Create a node.
    $this->drupalLogin($this->webUser);
    $this->node = $this->drupalCreateNode(['type' => 'article', 'promote' => 1, 'uid' => $this->webUser->id()]);

    // Post reservation #1 and verify that title is rendered in h3.
    $subject_text = $this->randomMachineName();
    $reservation_text = $this->randomMachineName();
    $reservation1 = $this->postReservation($this->node, $reservation_text, $subject_text, TRUE);

    // The entity fields for name and mail have no meaning if the user is not
    // Anonymous.
    $this->assertNull($reservation1->name->value);
    $this->assertNull($reservation1->mail->value);

    // Confirm that the reservation was created.
    $this->assertTrue($this->reservationExists($reservation1), 'Reservation #1. Reservation found.');
    // Tests that markup is created for reservation with heading.
    $this->assertSession()->responseMatches('|<h3[^>]*><a[^>]*>' . $subject_text . '</a></h3>|');
    // Tests that the reservation's title link is the permalink of the reservation.
    $reservation_permalink = $this->cssSelect('.permalink');
    $reservation_permalink = $reservation_permalink[0]->getAttribute('href');
    // Tests that the reservation's title link contains the url fragment.
    $this->assertStringContainsString('#reservation-' . $reservation1->id(), $reservation_permalink, "The reservation's title link contains the url fragment.");
    $this->assertEquals($reservation1->permalink()->toString(), $reservation_permalink, "The reservation's title has the correct link.");
  }

}
