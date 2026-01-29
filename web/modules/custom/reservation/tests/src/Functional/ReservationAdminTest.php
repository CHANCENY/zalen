<?php

namespace Drupal\Tests\reservation\Functional;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Html;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\user\RoleInterface;
use Drupal\reservation\Entity\Reservation;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests reservation approval functionality.
 *
 * @group reservation
 */
class ReservationAdminTest extends ReservationTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'classy';

  protected function setUp(): void {
    parent::setUp();

    $this->drupalPlaceBlock('page_title_block');
  }

  /**
   * Test reservation approval functionality through admin/content/reservation.
   */
  public function testApprovalAdminInterface() {
    // Set anonymous reservations to require approval.
    user_role_change_permissions(RoleInterface::ANONYMOUS_ID, [
      'access reservations' => TRUE,
      'post reservations' => TRUE,
      'skip reservation approval' => FALSE,
    ]);
    $this->drupalLogin($this->adminUser);
    // Ensure that doesn't require contact info.
    $this->setReservationAnonymous('0');

    // Test that the reservations page loads correctly when there are no reservations
    $this->drupalGet('admin/content/reservation');
    //$this->assertText('No reservations available.');deprecated
    $this->assertSession()->pageTextContains('No reservations available.');

    $this->drupalLogout();

    // Post anonymous reservation without contact info.
    $subject = $this->randomMachineName();
    $body = $this->randomMachineName();
    // Set $contact to true so that it won't check for id and message.
    $this->postReservation($this->node, $body, $subject, TRUE);
    //$this->assertSession()->pageTextContains('Your reservation has been queued for review by site administrators and will be published after approval.');
    $this->assertSession()->pageTextContains('Your reservation has been queued for review by site administrators and will be published after approval.');

    // Get unapproved reservation id.
    $this->drupalLogin($this->adminUser);
    $anonymous_reservation4 = $this->getUnapprovedReservation($subject);
    $anonymous_reservation4 = Reservation::create([
      'cid' => $anonymous_reservation4,
      'subject' => $subject,
      'reservation_body' => $body,
      'entity_id' => $this->node->id(),
      'entity_type' => 'node',
      'field_name' => 'reservation',
    ]);
    $this->drupalLogout();

    $this->assertFalse($this->reservationExists($anonymous_reservation4), 'Anonymous reservation was not published.');

    // Approve reservation.
    $this->drupalLogin($this->adminUser);
    $this->performReservationOperation($anonymous_reservation4, 'publish', TRUE);
    $this->drupalLogout();

    $this->drupalGet('node/' . $this->node->id());
    $this->assertTrue($this->reservationExists($anonymous_reservation4), 'Anonymous reservation visible.');

    // Post 2 anonymous reservations without contact info.
    $reservations[] = $this->postReservation($this->node, $this->randomMachineName(), $this->randomMachineName(), TRUE);
    $reservations[] = $this->postReservation($this->node, $this->randomMachineName(), $this->randomMachineName(), TRUE);

    // Publish multiple reservations in one operation.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/content/reservation/approval');
    $this->assertSession()->pageTextContains('Unapproved reservations (2)');
    $edit = [
      "reservations[{$reservations[0]->id()}]" => 1,
      "reservations[{$reservations[1]->id()}]" => 1,
    ];
    $this->submitForm($edit, 'Update');
    $this->assertSession()->pageTextContains('Unapproved reservations (0)');

    // Delete multiple reservations in one operation.
    $edit = [
      'operation' => 'delete',
      "reservations[{$reservations[0]->id()}]" => 1,
      "reservations[{$reservations[1]->id()}]" => 1,
      "reservations[{$anonymous_reservation4->id()}]" => 1,
    ];
    $this->submitForm($edit, 'Update');
    $this->assertSession()->pageTextContains('Are you sure you want to delete these reservations and all their children?');
    $this->submitForm([], 'Delete');
    $this->assertSession()->pageTextContains('No reservations available.');
    // Test message when no reservations selected.
    $edit = [
      'operation' => 'delete',
    ];
    $this->submitForm($edit, 'Update');
    $this->assertSession()->pageTextContains('Select one or more reservations to perform the update on.');

    // Make sure the label of unpublished node is not visible on listing page.
    $this->drupalGet('admin/content/reservation');
    $this->postReservation($this->node, $this->randomMachineName());
    $this->drupalGet('admin/content/reservation');
    $this->assertSession()->pageTextNotContains(Html::escape($this->node->label()));
    $this->node->setUnpublished()->save();
    $this->drupalGet('admin/content/reservation');
    $this->assertSession()->pageTextNotContains(Html::escape($this->node->label()));
  }

  /**
   * Tests reservation approval functionality through the node interface.
   */
  public function testApprovalNodeInterface() {
    // Set anonymous reservations to require approval.
    user_role_change_permissions(RoleInterface::ANONYMOUS_ID, [
      'access reservations' => TRUE,
      'post reservations' => TRUE,
      'skip reservation approval' => FALSE,
    ]);
    $this->drupalLogin($this->adminUser);
    // Ensure that doesn't require contact info.
    $this->setReservationAnonymous('0');
    $this->drupalLogout();

    // Post anonymous reservation without contact info.
    $subject = $this->randomMachineName();
    $body = $this->randomMachineName();
    // Set $contact to true so that it won't check for id and message.
    $this->postReservation($this->node, $body, $subject, TRUE);
    $this->assertSession()->pageTextContains('Your reservation has been queued for review by site administrators and will be published after approval.');

    // Get unapproved reservation id.
    $this->drupalLogin($this->adminUser);
    $anonymous_reservation4 = $this->getUnapprovedReservation($subject);
    $anonymous_reservation4 = Reservation::create([
      'cid' => $anonymous_reservation4,
      'subject' => $subject,
      'reservation_body' => $body,
      'entity_id' => $this->node->id(),
      'entity_type' => 'node',
      'field_name' => 'reservation',
    ]);
    $this->drupalLogout();

    $this->assertFalse($this->reservationExists($anonymous_reservation4), 'Anonymous reservation was not published.');

    // Ensure reservations cannot be approved without a valid token.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('reservation/1/approve');
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalGet('reservation/1/approve', ['query' => ['token' => 'forged']]);
    $this->assertSession()->statusCodeEquals(403);

    // Approve reservation.
    $this->drupalGet('reservation/1/edit');
    $this->assertSession()->checkboxChecked('edit-status-0');
    $this->drupalGet('node/' . $this->node->id());
    $this->clickLink(t('Approve'));
    $this->drupalLogout();

    $this->drupalGet('node/' . $this->node->id());
    $this->assertTrue($this->reservationExists($anonymous_reservation4), 'Anonymous reservation visible.');
  }

  /**
   * Tests reservation bundle admin.
   */
  public function testReservationAdmin() {
    // Login.
    $this->drupalLogin($this->adminUser);
    // Browse to reservation bundle overview.
    $this->drupalGet('admin/structure/reservation');
    $this->assertSession()->statusCodeEquals(200);
    // Make sure titles visible.
    $this->assertSession()->pageTextContains('Reservation type');
    $this->assertSession()->pageTextContains('Description');
    // Make sure the description is present.
    $this->assertSession()->pageTextContains('Default reservation field');
    // Manage fields.
    $this->clickLink('Manage fields');
    $this->assertSession()->statusCodeEquals(200);
    // Make sure reservation_body field is shown.
    $this->assertSession()->pageTextContains('reservation_body');
    // Rest from here on in is field_ui.
  }

  /**
   * Tests editing a reservation as an admin.
   */
  public function testEditReservation() {
    // Enable anonymous user reservations.
    user_role_grant_permissions(RoleInterface::ANONYMOUS_ID, [
      'access reservations',
      'post reservations',
      'skip reservation approval',
    ]);

    // Log in as a web user.
    $this->drupalLogin($this->webUser);
    // Post a reservation.
    $reservation = $this->postReservation($this->node, $this->randomMachineName());

    $this->drupalLogout();

    // Post anonymous reservation.
    $this->drupalLogin($this->adminUser);
    // Ensure that we need email id before posting reservation.
    $this->setReservationAnonymous('2');
    $this->drupalLogout();

    // Post reservation with contact info (required).
    $author_name = $this->randomMachineName();
    $author_mail = $this->randomMachineName() . '@example.com';
    $anonymous_reservation = $this->postReservation($this->node, $this->randomMachineName(), $this->randomMachineName(), ['name' => $author_name, 'mail' => $author_mail]);

    // Log in as an admin user.
    $this->drupalLogin($this->adminUser);

    // Make sure the reservation field is not visible when
    // the reservation was posted by an authenticated user.
    $this->drupalGet('reservation/' . $reservation->id() . '/edit');
    $this->assertSession()->fieldNotExists('edit-mail');

    // Make sure the reservation field is visible when
    // the reservation was posted by an anonymous user.
    $this->drupalGet('reservation/' . $anonymous_reservation->id() . '/edit');
    $this->assertSession()->fieldValueEquals('edit-mail', $anonymous_reservation->getAuthorEmail());
  }

  /**
   * Tests reservationed translation deletion admin view.
   */
  public function testReservationedTranslationDeletion() {
    \Drupal::service('module_installer')->install([
      'language',
      'locale',
    ]);
    \Drupal::service('router.builder')->rebuildIfNeeded();

    ConfigurableLanguage::createFromLangcode('ur')->save();
    // Rebuild the container to update the default language container variable.
    $this->rebuildContainer();
    // Ensure that doesn't require contact info.
    $this->setReservationAnonymous('0');
    $this->drupalLogin($this->webUser);
    $count_query = \Drupal::entityTypeManager()
      ->accessCheck(TRUE)
      ->getStorage('reservation')
      ->getQuery()
      ->count();
    $before_count = $count_query->execute();
    // Post 2 anonymous reservations without contact info.
    $reservation1 = $this->postReservation($this->node, $this->randomMachineName(), $this->randomMachineName(), TRUE);
    $reservation2 = $this->postReservation($this->node, $this->randomMachineName(), $this->randomMachineName(), TRUE);

    $reservation1->addTranslation('ur', ['subject' => 'ur ' . $reservation1->label()])
      ->save();
    $reservation2->addTranslation('ur', ['subject' => 'ur ' . $reservation1->label()])
      ->save();
    $this->drupalLogout();
    $this->drupalLogin($this->adminUser);
    // Delete multiple reservations in one operation.
    $edit = [
      'operation' => 'delete',
      "reservations[{$reservation1->id()}]" => 1,
      "reservations[{$reservation2->id()}]" => 1,
    ];
    $this->submitForm('admin/content/reservation', $edit, 'Update');
    $this->assertSession()->responseContains(new FormattableMarkup('@label (Original translation) - <em>The following reservation translations will be deleted:</em>', ['@label' => $reservation1->label()]));
    $this->assertSession()->responseContains(new FormattableMarkup('@label (Original translation) - <em>The following reservation translations will be deleted:</em>', ['@label' => $reservation2->label()]));
    $this->assertSession()->pageTextContains('English');
    $this->assertSession()->pageTextContains('Urdu');
    $this->submitForm([], 'Delete');
    $after_count = $count_query->execute();
    $this->assertEquals($before_count, $after_count, 'No reservation or translation found.');
  }

}
