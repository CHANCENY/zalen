<?php

namespace Drupal\Tests\reservation\Functional;

use Drupal\reservation\ReservationInterface;
use Drupal\user\RoleInterface;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests anonymous reservationing.
 *
 * @group reservation
 */
class ReservationAnonymousTest extends ReservationTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'classy';

  protected function setUp(): void {
    parent::setUp();

    // Enable anonymous and authenticated user reservations.
    user_role_grant_permissions(RoleInterface::ANONYMOUS_ID, [
      'access reservations',
      'post reservations',
      'skip reservation approval',
    ]);
    user_role_grant_permissions(RoleInterface::AUTHENTICATED_ID, [
      'access reservations',
      'post reservations',
      'skip reservation approval',
    ]);
  }

  /**
   * Tests anonymous reservation functionality.
   */
  public function testAnonymous() {
    $this->drupalLogin($this->adminUser);
    $this->setReservationAnonymous(ReservationInterface::ANONYMOUS_MAYNOT_CONTACT);
    $this->drupalLogout();

    // Preview reservations (with `skip reservation approval` permission).
    $edit = [];
    $title = 'reservation title with skip reservation approval';
    $body = 'reservation body with skip reservation approval';
    $edit['subject[0][value]'] = $title;
    $edit['reservation_body[0][value]'] = $body;
    $this->submitForm($this->node->toUrl(), $edit, 'Preview');
    // Cannot use assertRaw here since both title and body are in the form.
    $preview = (string) $this->cssSelect('.preview')[0]->getHtml();
    $this->assertStringContainsString($title, $preview, 'Anonymous user can preview reservation title.');
    $this->assertStringContainsString($body, $preview, 'Anonymous user can preview reservation body.');

    // Preview reservations (without `skip reservation approval` permission).
    user_role_revoke_permissions(RoleInterface::ANONYMOUS_ID, ['skip reservation approval']);
    $edit = [];
    $title = 'reservation title without skip reservation approval';
    $body = 'reservation body without skip reservation approval';
    $edit['subject[0][value]'] = $title;
    $edit['reservation_body[0][value]'] = $body;
    $this->submitForm($this->node->toUrl(), $edit, 'Preview');
    // Cannot use assertRaw here since both title and body are in the form.
    $preview = (string) $this->cssSelect('.preview')[0]->getHtml();
    $this->assertStringContainsString($title, $preview, 'Anonymous user can preview reservation title.');
    $this->assertStringContainsString($body, $preview, 'Anonymous user can preview reservation body.');
    user_role_grant_permissions(RoleInterface::ANONYMOUS_ID, ['skip reservation approval']);

    // Post anonymous reservation without contact info.
    $anonymous_reservation1 = $this->postReservation($this->node, $this->randomMachineName(), $this->randomMachineName());
    $this->assertTrue($this->reservationExists($anonymous_reservation1), 'Anonymous reservation without contact info found.');

    // Ensure anonymous users cannot post in the name of registered users.
    $edit = [
      'name' => $this->adminUser->getAccountName(),
      'reservation_body[0][value]' => $this->randomMachineName(),
    ];
    $this->submitForm('reservation/reply/node/' . $this->node->id() . '/reservation', $edit, 'Save');
    $this->assertSession()->responseContains(t('The name you used (%name) belongs to a registered user.', [
      '%name' => $this->adminUser->getAccountName(),
    ]));

    // Allow contact info.
    $this->drupalLogin($this->adminUser);
    $this->setReservationAnonymous(ReservationInterface::ANONYMOUS_MAY_CONTACT);

    // Attempt to edit anonymous reservation.
    $this->drupalGet('reservation/' . $anonymous_reservation1->id() . '/edit');
    $edited_reservation = $this->postReservation(NULL, $this->randomMachineName(), $this->randomMachineName());
    $this->assertTrue($this->reservationExists($edited_reservation, FALSE), 'Modified reply found.');
    $this->drupalLogout();

    // Post anonymous reservation with contact info (optional).
    $this->drupalGet('reservation/reply/node/' . $this->node->id() . '/reservation');
    $this->assertTrue($this->reservationContactInfoAvailable(), 'Contact information available.');

    // Check the presence of expected cache tags.
    $this->assertSession()->responseHeaderContains('X-Drupal-Cache-Tags', 'config:field.field.node.article.reservation');
    $this->assertSession()->responseHeaderContains('X-Drupal-Cache-Tags', 'config:user.settings');

    $anonymous_reservation2 = $this->postReservation($this->node, $this->randomMachineName(), $this->randomMachineName());
    $this->assertTrue($this->reservationExists($anonymous_reservation2), 'Anonymous reservation with contact info (optional) found.');

    // Ensure anonymous users cannot post in the name of registered users.
    $edit = [
      'name' => $this->adminUser->getAccountName(),
      'mail' => $this->randomMachineName() . '@example.com',
      'subject[0][value]' => $this->randomMachineName(),
      'reservation_body[0][value]' => $this->randomMachineName(),
    ];
    $this->submitForm('reservation/reply/node/' . $this->node->id() . '/reservation', $edit, 'Save');
    $this->assertSession()->responseContains(t('The name you used (%name) belongs to a registered user.', [
      '%name' => $this->adminUser->getAccountName(),
    ]));

    // Require contact info.
    $this->drupalLogin($this->adminUser);
    $this->setReservationAnonymous(ReservationInterface::ANONYMOUS_MUST_CONTACT);
    $this->drupalLogout();

    // Try to post reservation with contact info (required).
    $this->drupalGet('reservation/reply/node/' . $this->node->id() . '/reservation');
    $this->assertTrue($this->reservationContactInfoAvailable(), 'Contact information available.');

    $anonymous_reservation3 = $this->postReservation($this->node, $this->randomMachineName(), $this->randomMachineName(), TRUE);
    // Name should have 'Anonymous' for value by default.
    $this->assertSession()->pageTextContains('Email field is required.');
    $this->assertFalse($this->reservationExists($anonymous_reservation3), 'Anonymous reservation with contact info (required) not found.');

    // Post reservation with contact info (required).
    $author_name = $this->randomMachineName();
    $author_mail = $this->randomMachineName() . '@example.com';
    $anonymous_reservation3 = $this->postReservation($this->node, $this->randomMachineName(), $this->randomMachineName(), ['name' => $author_name, 'mail' => $author_mail]);
    $this->assertTrue($this->reservationExists($anonymous_reservation3), 'Anonymous reservation with contact info (required) found.');

    // Make sure the user data appears correctly when editing the reservation.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('reservation/' . $anonymous_reservation3->id() . '/edit');
    $this->assertSession()->responseContains($author_name);
    // Check the author field is empty (i.e. anonymous) when editing the reservation.
    $this->assertSession()->fieldValueEquals('uid', '');
    $this->assertSession()->responseContains($author_mail);

    // Unpublish reservation.
    $this->performReservationOperation($anonymous_reservation3, 'unpublish');

    $this->drupalGet('admin/content/reservation/approval');
    $this->assertSession()->responseContains('reservations[' . $anonymous_reservation3->id() . ']');

    // Publish reservation.
    $this->performReservationOperation($anonymous_reservation3, 'publish', TRUE);

    $this->drupalGet('admin/content/reservation');
    $this->assertSession()->responseContains('reservations[' . $anonymous_reservation3->id() . ']');

    // Delete reservation.
    $this->performReservationOperation($anonymous_reservation3, 'delete');

    $this->drupalGet('admin/content/reservation');
    $this->assertSession()->responseNotContains('reservations[' . $anonymous_reservation3->id() . ']');
    $this->drupalLogout();

    // Reservation 3 was deleted.
    $this->drupalGet('reservation/reply/node/' . $this->node->id() . '/reservation/' . $anonymous_reservation3->id());
    $this->assertSession()->statusCodeEquals(403);

    // Reset.
    user_role_change_permissions(RoleInterface::ANONYMOUS_ID, [
      'access reservations' => FALSE,
      'post reservations' => FALSE,
      'skip reservation approval' => FALSE,
    ]);

    // Attempt to view reservations while disallowed.
    // NOTE: if authenticated user has permission to post reservations, then a
    // "Login or register to post reservations" type link may be shown.
    $this->drupalGet('node/' . $this->node->id());
    // Verify that reservations were not displayed.
    $this->assertSession()->responseNotMatches('@<h2[^>]*>Reservations</h2>@');
    $this->assertSession()->linkNotExists('Add new reservation', 'Link to add reservation was found.');

    // Attempt to view node-reservation form while disallowed.
    $this->drupalGet('reservation/reply/node/' . $this->node->id() . '/reservation');
    $this->assertSession()->statusCodeEquals(403);

    user_role_change_permissions(RoleInterface::ANONYMOUS_ID, [
      'access reservations' => TRUE,
      'post reservations' => FALSE,
      'skip reservation approval' => FALSE,
    ]);
    $this->drupalGet('node/' . $this->node->id());
    // Verify that the reservation field title is displayed.
    $this->assertSession()->responseMatches('@<h2[^>]*>Reservations</h2>@');
    $this->assertSession()->linkExists('Log in', 1, 'Link to login was found.');
    $this->assertSession()->linkExists('register', 1, 'Link to register was found.');

    user_role_change_permissions(RoleInterface::ANONYMOUS_ID, [
      'access reservations' => FALSE,
      'post reservations' => TRUE,
      'skip reservation approval' => TRUE,
    ]);
    $this->drupalGet('node/' . $this->node->id());
    // Verify that reservations were not displayed.
    $this->assertSession()->responseNotMatches('@<h2[^>]*>Reservations</h2>@');
    $this->assertSession()->fieldValueEquals('subject[0][value]', '');
    $this->assertSession()->fieldValueEquals('reservation_body[0][value]', '');

    $this->drupalGet('reservation/reply/node/' . $this->node->id() . '/reservation/' . $anonymous_reservation2->id());
    $this->assertSession()->statusCodeEquals(403);
  }

}
