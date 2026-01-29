<?php

namespace Drupal\Tests\reservation\Functional;

use Drupal\Core\Url;
use Drupal\reservation\ReservationManagerInterface;
use Drupal\reservation\Plugin\Field\FieldType\ReservationItemInterface;
use Drupal\reservation\Entity\Reservation;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\user\RoleInterface;
use Drupal\filter\Entity\FilterFormat;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests reservation user interfaces.
 *
 * @group reservation
 */
class ReservationInterfaceTest extends ReservationTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'classy';

  /**
   * Set up reservations to have subject and preview disabled.
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalLogin($this->adminUser);
    // Make sure that reservation field title is not displayed when there's no
    // reservations posted.
    $this->drupalGet($this->node->toUrl());
    $this->assertSession()->responseNotMatches('@<h2[^>]*>Reservations</h2>@');

    // Set reservations to have subject and preview disabled.
    $this->setReservationPreview(DRUPAL_DISABLED);
    $this->setReservationForm(TRUE);
    $this->setReservationSubject(FALSE);
    $this->setReservationSettings('default_mode', ReservationManagerInterface::RESERVATION_MODE_THREADED, 'Reservation paging changed.');
    $this->drupalLogout();
  }

  /**
   * Tests the reservation interface.
   */
  public function testReservationInterface() {

    // Post reservation #1 without subject or preview.
    $this->drupalLogin($this->webUser);
    $reservation_text = $this->randomMachineName();
    $reservation = $this->postReservation($this->node, $reservation_text);
    $this->assertTrue($this->reservationExists($reservation), 'Reservation found.');

    // Test that using an invalid entity-type does not raise an error.
    $this->drupalGet('reservation/reply/yeah-this-is-not-an-entity-type/' . $this->node->id() . '/reservation/' . $reservation->id());
    $this->assertSession()->statusCodeEquals(404);

    // Test the reservation field title is displayed when there's reservations.
    $this->drupalGet($this->node->toUrl());
    $this->assertSession()->responseMatches('@<h2[^>]*>Reservations</h2>@');

    // Set reservations to have subject and preview to required.
    $this->drupalLogout();
    $this->drupalLogin($this->adminUser);
    $this->setReservationSubject(TRUE);
    $this->setReservationPreview(DRUPAL_REQUIRED);
    $this->drupalLogout();

    // Create reservation #2 that allows subject and requires preview.
    $this->drupalLogin($this->webUser);
    $subject_text = $this->randomMachineName();
    $reservation_text = $this->randomMachineName();
    $reservation = $this->postReservation($this->node, $reservation_text, $subject_text, TRUE);
    $this->assertTrue($this->reservationExists($reservation), 'Reservation found.');

    // Reservation as anonymous with preview required.
    $this->drupalLogout();
    user_role_grant_permissions(RoleInterface::ANONYMOUS_ID, ['access content', 'access reservations', 'post reservations', 'skip reservation approval']);
    $anonymous_reservation = $this->postReservation($this->node, $this->randomMachineName(), $this->randomMachineName(), TRUE);
    $this->assertTrue($this->reservationExists($anonymous_reservation), 'Reservation found.');
    $anonymous_reservation->delete();

    // Check reservation display.
    $this->drupalLogin($this->webUser);
    $this->drupalGet('node/' . $this->node->id());
    $this->assertSession()->pageTextContains($subject_text);
    $this->assertSession()->pageTextContains($reservation_text);
    $arguments = [
      ':link' => base_path() . 'reservation/' . $reservation->id() . '#reservation-' . $reservation->id(),
    ];
    $pattern_permalink = '//footer[contains(@class,"reservation__meta")]/a[contains(@href,:link) and text()="Permalink"]';
    $permalink = $this->xpath($pattern_permalink, $arguments);
    $this->assertTrue(!empty($permalink), 'Permalink link found.');

    // Set reservations to have subject and preview to optional.
    $this->drupalLogout();
    $this->drupalLogin($this->adminUser);
    $this->setReservationSubject(TRUE);
    $this->setReservationPreview(DRUPAL_OPTIONAL);

    $this->drupalGet('reservation/' . $reservation->id() . '/edit');
    $this->assertSession()->titleEquals('Edit reservation ' . $reservation->getSubject() . ' | Drupal');

    // Test changing the reservation author to "Anonymous".
    $reservation = $this->postReservation(NULL, $reservation->reservation_body->value, $reservation->getSubject(), ['uid' => '']);
    $this->assertSame('Anonymous', $reservation->getAuthorName());
    $this->assertEquals(0, $reservation->getOwnerId());

    // Test changing the reservation author to an unverified user.
    $random_name = $this->randomMachineName();
    $this->drupalGet('reservation/' . $reservation->id() . '/edit');
    $reservation = $this->postReservation(NULL, $reservation->reservation_body->value, $reservation->getSubject(), ['name' => $random_name]);
    $this->drupalGet('node/' . $this->node->id());
    $this->assertSession()->pageTextContains($random_name . ' (not verified)');

    // Test changing the reservation author to a verified user.
    $this->drupalGet('reservation/' . $reservation->id() . '/edit');
    $reservation = $this->postReservation(NULL, $reservation->reservation_body->value, $reservation->getSubject(), ['uid' => $this->webUser->getAccountName() . ' (' . $this->webUser->id() . ')']);
    $this->assertSame($this->webUser->getAccountName(), $reservation->getAuthorName());
    $this->assertSame($this->webUser->id(), $reservation->getOwnerId());

    $this->drupalLogout();

    // Reply to reservation #2 creating reservation #3 with optional preview and no
    // subject though field enabled.
    $this->drupalLogin($this->webUser);
    // Deliberately use the wrong url to test
    // \Drupal\reservation\Controller\ReservationController::redirectNode().
    $this->drupalGet('reservation/' . $this->node->id() . '/reply');
    // Verify we were correctly redirected.
    $this->assertSession()->addressEquals(Url::fromRoute('reservation.reply', ['entity_type' => 'node', 'entity' => $this->node->id(), 'field_name' => 'reservation']));
    $this->drupalGet('reservation/reply/node/' . $this->node->id() . '/reservation/' . $reservation->id());
    $this->assertSession()->pageTextContains($subject_text);
    $this->assertSession()->pageTextContains($reservation_text);
    $reply = $this->postReservation(NULL, $this->randomMachineName(), '', TRUE);
    $reply_loaded = Reservation::load($reply->id());
    $this->assertTrue($this->reservationExists($reply, TRUE), 'Reply found.');
    $this->assertEquals($reservation->id(), $reply_loaded->getParentReservation()->id(), 'Pid of a reply to a reservation is set correctly.');
    // Check the thread of reply grows correctly.
    $this->assertEquals(rtrim($reservation->getThread(), '/') . '.00/', $reply_loaded->getThread());

    // Second reply to reservation #2 creating reservation #4.
    $this->drupalGet('reservation/reply/node/' . $this->node->id() . '/reservation/' . $reservation->id());
    $this->assertSession()->pageTextContains($reservation->getSubject());
    $this->assertSession()->pageTextContains($reservation->reservation_body->value);
    $reply = $this->postReservation(NULL, $this->randomMachineName(), $this->randomMachineName(), TRUE);
    $reply_loaded = Reservation::load($reply->id());
    $this->assertTrue($this->reservationExists($reply, TRUE), 'Second reply found.');
    // Check the thread of second reply grows correctly.
    $this->assertEquals(rtrim($reservation->getThread(), '/') . '.01/', $reply_loaded->getThread());

    // Reply to reservation #4 creating reservation #5.
    $this->drupalGet('reservation/reply/node/' . $this->node->id() . '/reservation/' . $reply_loaded->id());
    $this->assertSession()->pageTextContains($reply_loaded->getSubject());
    $this->assertSession()->pageTextContains($reply_loaded->reservation_body->value);
    $reply = $this->postReservation(NULL, $this->randomMachineName(), $this->randomMachineName(), TRUE);
    $reply_loaded = Reservation::load($reply->id());
    $this->assertTrue($this->reservationExists($reply, TRUE), 'Second reply found.');
    // Check the thread of reply to second reply grows correctly.
    $this->assertEquals(rtrim($reservation->getThread(), '/') . '.01.00/', $reply_loaded->getThread());

    // Edit reply.
    $this->drupalGet('reservation/' . $reply->id() . '/edit');
    $reply = $this->postReservation(NULL, $this->randomMachineName(), $this->randomMachineName(), TRUE);
    $this->assertTrue($this->reservationExists($reply, TRUE), 'Modified reply found.');

    // Confirm a new reservation is posted to the correct page.
    $this->setReservationsPerPage(2);
    $reservation_new_page = $this->postReservation($this->node, $this->randomMachineName(), $this->randomMachineName(), TRUE);
    $this->assertTrue($this->reservationExists($reservation_new_page), 'Page one exists. %s');
    $this->drupalGet('node/' . $this->node->id(), ['query' => ['page' => 2]]);
    $this->assertTrue($this->reservationExists($reply, TRUE), 'Page two exists. %s');
    $this->setReservationsPerPage(50);

    // Attempt to reply to an unpublished reservation.
    $reply_loaded->setUnpublished();
    $reply_loaded->save();
    $this->drupalGet('reservation/reply/node/' . $this->node->id() . '/reservation/' . $reply_loaded->id());
    $this->assertSession()->statusCodeEquals(403);

    // Attempt to post to node with reservations disabled.
    $this->node = $this->drupalCreateNode(['type' => 'article', 'promote' => 1, 'reservation' => [['status' => ReservationItemInterface::HIDDEN]]]);
    $this->assertNotNull($this->node, 'Article node created.');
    $this->drupalGet('reservation/reply/node/' . $this->node->id() . '/reservation');
    $this->assertSession()->statusCodeEquals(403);
    $this->assertSession()->fieldNotExists('edit-reservation');

    // Attempt to post to node with read-only reservations.
    $this->node = $this->drupalCreateNode(['type' => 'article', 'promote' => 1, 'reservation' => [['status' => ReservationItemInterface::CLOSED]]]);
    $this->assertNotNull($this->node, 'Article node created.');
    $this->drupalGet('reservation/reply/node/' . $this->node->id() . '/reservation');
    $this->assertSession()->statusCodeEquals(403);
    $this->assertSession()->fieldNotExists('edit-reservation');

    // Attempt to post to node with reservations enabled (check field names etc).
    $this->node = $this->drupalCreateNode(['type' => 'article', 'promote' => 1, 'reservation' => [['status' => ReservationItemInterface::OPEN]]]);
    $this->assertNotNull($this->node, 'Article node created.');
    $this->drupalGet('reservation/reply/node/' . $this->node->id() . '/reservation');
    $this->assertSession()->pageTextNotContains('This discussion is closed');
    // Ensure that the reservation body field exists.
    $this->assertSession()->fieldExists('edit-reservation-body-0-value');

    // Delete reservation and make sure that reply is also removed.
    $this->drupalLogout();
    $this->drupalLogin($this->adminUser);
    $this->deleteReservation($reservation);
    $this->deleteReservation($reservation_new_page);

    $this->drupalGet('node/' . $this->node->id());
    $this->assertFalse($this->reservationExists($reservation), 'Reservation not found.');
    $this->assertFalse($this->reservationExists($reply, TRUE), 'Reply found.');

    // Enabled reservation form on node page.
    $this->drupalLogin($this->adminUser);
    $this->setReservationForm(TRUE);
    $this->drupalLogout();

    // Submit reservation through node form.
    $this->drupalLogin($this->webUser);
    $this->drupalGet('node/' . $this->node->id());
    $form_reservation = $this->postReservation(NULL, $this->randomMachineName(), $this->randomMachineName(), TRUE);
    $this->assertTrue($this->reservationExists($form_reservation), 'Form reservation found.');

    // Disable reservation form on node page.
    $this->drupalLogout();
    $this->drupalLogin($this->adminUser);
    $this->setReservationForm(FALSE);
  }

  /**
   * Test that the subject is automatically filled if disabled or left blank.
   *
   * When the subject field is blank or disabled, the first 29 characters of the
   * reservation body are used for the subject. If this would break within a word,
   * then the break is put at the previous word boundary instead.
   */
  public function testAutoFilledSubject() {
    $this->drupalLogin($this->webUser);
    $this->drupalGet('node/' . $this->node->id());

    // Break when there is a word boundary before 29 characters.
    $body_text = 'Lorem ipsum Lorem ipsum Loreming ipsum Lorem ipsum';
    $reservation1 = $this->postReservation(NULL, $body_text, '', TRUE);
    $this->assertTrue($this->reservationExists($reservation1), 'Form reservation found.');
    $this->assertEquals('Lorem ipsum Lorem ipsum…', $reservation1->getSubject());

    // Break at 29 characters where there's no boundary before that.
    $body_text2 = 'LoremipsumloremipsumLoremingipsumLoremipsum';
    $reservation2 = $this->postReservation(NULL, $body_text2, '', TRUE);
    $this->assertEquals('LoremipsumloremipsumLoreming…', $reservation2->getSubject());
  }

  /**
   * Test that automatic subject is correctly created from HTML reservation text.
   *
   * This is the same test as in ReservationInterfaceTest::testAutoFilledSubject()
   * with the additional check that HTML is stripped appropriately prior to
   * character-counting.
   */
  public function testAutoFilledHtmlSubject() {
    // Set up two default (i.e. filtered HTML) input formats, because then we
    // can select one of them. Then create a user that can use these formats,
    // log the user in, and then GET the node page on which to test the
    // reservations.
    $filtered_html_format = FilterFormat::create([
      'format' => 'filtered_html',
      'name' => 'Filtered HTML',
    ]);
    $filtered_html_format->save();
    $full_html_format = FilterFormat::create([
      'format' => 'full_html',
      'name' => 'Full HTML',
    ]);
    $full_html_format->save();
    $html_user = $this->drupalCreateUser([
      'access reservations',
      'post reservations',
      'edit own reservations',
      'skip reservation approval',
      'access content',
      $filtered_html_format->getPermissionName(),
      $full_html_format->getPermissionName(),
    ]);
    $this->drupalLogin($html_user);
    $this->drupalGet('node/' . $this->node->id());

    // HTML should not be included in the character count.
    $body_text1 = '<span></span><strong> </strong><span> </span><strong></strong>Hello World<br />';
    $edit1 = [
      'reservation_body[0][value]' => $body_text1,
      'reservation_body[0][format]' => 'filtered_html',
    ];
    $this->submitForm($edit1, 'Save');
    $this->assertEquals('Hello World', Reservation::load(1)->getSubject());

    // If there's nothing other than HTML, the subject should be '(No subject)'.
    $body_text2 = '<span></span><strong> </strong><span> </span><strong></strong> <br />';
    $edit2 = [
      'reservation_body[0][value]' => $body_text2,
      'reservation_body[0][format]' => 'filtered_html',
    ];
    $this->submitForm($edit2, 'Save');
    $this->assertEquals('No subject', Reservation::load(2)->getSubject());
  }

  /**
   * Tests the reservation formatter configured with a custom reservation view mode.
   */
  public function testViewMode() {
    $this->drupalLogin($this->webUser);
    $this->drupalGet($this->node->toUrl());
    $reservation_text = $this->randomMachineName();
    // Post a reservation.
    $this->postReservation($this->node, $reservation_text);

    // Reservation displayed in 'default' display mode found and has body text.
    $reservation_element = $this->cssSelect('.reservation-wrapper');
    $this->assertTrue(!empty($reservation_element));
    $this->assertSession()->responseContains('<p>' . $reservation_text . '</p>');

    // Create a new reservation entity view mode.
    $mode = mb_strtolower($this->randomMachineName());
    EntityViewMode::create([
      'targetEntityType' => 'reservation',
      'id' => "reservation.$mode",
    ])->save();
    // Create the corresponding entity view display for article node-type. Note
    // that this new view display mode doesn't contain the reservation body.
    EntityViewDisplay::create([
      'targetEntityType' => 'reservation',
      'bundle' => 'reservation',
      'mode' => $mode,
    ])->setStatus(TRUE)->save();

    /** @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface $node_display */
    $node_display = EntityViewDisplay::load('node.article.default');
    $formatter = $node_display->getComponent('reservation');
    // Change the node reservation field formatter to use $mode mode instead of
    // 'default' mode.
    $formatter['settings']['view_mode'] = $mode;
    $node_display
      ->setComponent('reservation', $formatter)
      ->save();

    // Reloading the node page to show the same node with its same reservation but
    // with a different display mode.
    $this->drupalGet($this->node->toUrl());
    // The reservation should exist but without the body text because we used $mode
    // mode this time.
    $reservation_element = $this->cssSelect('.reservation-wrapper');
    $this->assertTrue(!empty($reservation_element));
    $this->assertSession()->responseNotContains('<p>' . $reservation_text . '</p>');
  }

}
