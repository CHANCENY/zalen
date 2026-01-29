<?php

namespace Drupal\Tests\reservation\Functional;

use Drupal\reservation\ReservationManagerInterface;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\reservation\Entity\Reservation;
use Drupal\Tests\TestFileCreationTrait;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests reservation preview.
 *
 * @group reservation
 */
class ReservationPreviewTest extends ReservationTestBase {

  use TestFileCreationTrait {
    getTestFiles as drupalGetTestFiles;
  }

  /**
   * The profile to install as a basis for testing.
   *
   * Using the standard profile to test user picture display in reservations.
   *
   * @var string
   */
  protected $profile = 'standard';

  /**
   * Tests reservation preview.
   */
  public function testReservationPreview() {
    // As admin user, configure reservation settings.
    $this->drupalLogin($this->adminUser);
    $this->setReservationPreview(DRUPAL_OPTIONAL);
    $this->setReservationForm(TRUE);
    $this->setReservationSubject(TRUE);
    $this->setReservationSettings('default_mode', ReservationManagerInterface::RESERVATION_MODE_THREADED, 'Reservation paging changed.');
    $this->drupalLogout();

    // Log in as web user.
    $this->drupalLogin($this->webUser);

    // Test escaping of the username on the preview form.
    \Drupal::service('module_installer')->install(['user_hooks_test']);
    \Drupal::state()->set('user_hooks_test_user_format_name_alter', TRUE);
    $edit = [];
    $edit['subject[0][value]'] = $this->randomMachineName(8);
    $edit['reservation_body[0][value]'] = $this->randomMachineName(16);
    $this->submitForm('node/' . $this->node->id(), $edit, 'Preview');
    $this->assertSession()->assertEscaped('<em>' . $this->webUser->id() . '</em>');

    \Drupal::state()->set('user_hooks_test_user_format_name_alter_safe', TRUE);
    $this->submitForm('node/' . $this->node->id(), $edit, 'Preview');
    $this->assertInstanceOf(MarkupInterface::class, $this->webUser->getDisplayName());
    $this->assertSession()->assertNoEscaped('<em>' . $this->webUser->id() . '</em>');
    $this->assertSession()->responseContains('<em>' . $this->webUser->id() . '</em>');

    // Add a user picture.
    $image = current($this->drupalGetTestFiles('image'));
    $user_edit['files[user_picture_0]'] = \Drupal::service('file_system')->realpath($image->uri);
    $this->submitForm('user/' . $this->webUser->id() . '/edit', $user_edit, 'Save');

    // As the web user, fill in the reservation form and preview the reservation.
    $this->submitForm('node/' . $this->node->id(), $edit, 'Preview');

    // Check that the preview is displaying the title and body.
    $this->assertSession()->titleEquals('Preview reservation | Drupal');
    $this->assertSession()->pageTextContains($edit['subject[0][value]']);
    $this->assertSession()->pageTextContains($edit['reservation_body[0][value]']);

    // Check that the title and body fields are displayed with the correct values.
    $this->assertSession()->fieldValueEquals('subject[0][value]', $edit['subject[0][value]']);
    $this->assertSession()->fieldValueEquals('reservation_body[0][value]', $edit['reservation_body[0][value]']);

    // Check that the user picture is displayed.
    $this->assertSession()->elementExists('xpath', "//article[contains(@class, 'preview')]//div[contains(@class, 'user-picture')]//img");
  }

  /**
   * Tests reservation preview.
   */
  public function testReservationPreviewDuplicateSubmission() {
    // As admin user, configure reservation settings.
    $this->drupalLogin($this->adminUser);
    $this->setReservationPreview(DRUPAL_OPTIONAL);
    $this->setReservationForm(TRUE);
    $this->setReservationSubject(TRUE);
    $this->setReservationSettings('default_mode', ReservationManagerInterface::RESERVATION_MODE_THREADED, 'Reservation paging changed.');
    $this->drupalLogout();

    // Log in as web user.
    $this->drupalLogin($this->webUser);

    // As the web user, fill in the reservation form and preview the reservation.
    $edit = [];
    $edit['subject[0][value]'] = $this->randomMachineName(8);
    $edit['reservation_body[0][value]'] = $this->randomMachineName(16);
    $this->submitForm('node/' . $this->node->id(), $edit, 'Preview');

    // Check that the preview is displaying the title and body.
    $this->assertSession()->titleEquals('Preview reservation | Drupal');
    $this->assertSession()->pageTextContains($edit['subject[0][value]']);
    $this->assertSession()->pageTextContains($edit['reservation_body[0][value]']);

    // Check that the title and body fields are displayed with the correct values.
    $this->assertSession()->fieldValueEquals('subject[0][value]', $edit['subject[0][value]']);
    $this->assertSession()->fieldValueEquals('reservation_body[0][value]', $edit['reservation_body[0][value]']);

    // Store the content of this page.
    $this->submitForm([], 'Save');
    this->assertSession()->pageTextContains('Your reservation has been posted.');
    $elements = $this->xpath('//section[contains(@class, "reservation-wrapper")]/article');
    $this->assertCount(1, $elements);

    // Go back and re-submit the form.
    $this->getSession()->getDriver()->back();
    $submit_button = $this->assertSession()->buttonExists('Save');
    $submit_button->click();
    this->assertSession()->pageTextContains('Your reservation has been posted.');
    $elements = $this->xpath('//section[contains(@class, "reservation-wrapper")]/article');
    $this->assertCount(2, $elements);
  }

  /**
   * Tests reservation edit, preview, and save.
   */
  public function testReservationEditPreviewSave() {
    $web_user = $this->drupalCreateUser([
      'access reservations',
      'post reservations',
      'skip reservation approval',
      'edit own reservations',
    ]);
    $this->drupalLogin($this->adminUser);
    $this->setReservationPreview(DRUPAL_OPTIONAL);
    $this->setReservationForm(TRUE);
    $this->setReservationSubject(TRUE);
    $this->setReservationSettings('default_mode', ReservationManagerInterface::RESERVATION_MODE_THREADED, 'Reservation paging changed.');

    $edit = [];
    $date = new DrupalDateTime('2008-03-02 17:23');
    $edit['subject[0][value]'] = $this->randomMachineName(8);
    $edit['reservation_body[0][value]'] = $this->randomMachineName(16);
    $edit['uid'] = $web_user->getAccountName() . ' (' . $web_user->id() . ')';
    $edit['date[date]'] = $date->format('Y-m-d');
    $edit['date[time]'] = $date->format('H:i:s');
    $raw_date = $date->getTimestamp();
    $expected_text_date = $this->container->get('date.formatter')->format($raw_date);
    $expected_form_date = $date->format('Y-m-d');
    $expected_form_time = $date->format('H:i:s');
    $reservation = $this->postReservation($this->node, $edit['subject[0][value]'], $edit['reservation_body[0][value]'], TRUE);
    $this->submitForm('reservation/' . $reservation->id() . '/edit', $edit, 'Preview');

    // Check that the preview is displaying the subject, reservation, author and date correctly.
    $this->assertSession()->titleEquals('Preview reservation | Drupal');
    $this->assertSession()->pageTextContains($edit['subject[0][value]']);
    $this->assertSession()->pageTextContains($edit['reservation_body[0][value]']);
    $this->assertSession()->pageTextContains($web_user->getAccountName());
    $this->assertSession()->pageTextContains($expected_text_date);

    // Check that the subject, reservation, author and date fields are displayed with the correct values.
    $this->assertSession()->fieldValueEquals('subject[0][value]', $edit['subject[0][value]']);
    $this->assertSession()->fieldValueEquals('reservation_body[0][value]', $edit['reservation_body[0][value]']);
    $this->assertSession()->fieldValueEquals('uid', $edit['uid']);
    $this->assertSession()->fieldValueEquals('date[date]', $edit['date[date]']);
    $this->assertSession()->fieldValueEquals('date[time]', $edit['date[time]']);

    // Check that saving a reservation produces a success message.
    $this->submitForm('reservation/' . $reservation->id() . '/edit', $edit, 'Save');
    $this->assertSession()->pageTextContains('Your reservation has been posted.');

    // Check that the reservation fields are correct after loading the saved reservation.
    $this->drupalGet('reservation/' . $reservation->id() . '/edit');
    $this->assertSession()->fieldValueEquals('subject[0][value]', $edit['subject[0][value]']);
    $this->assertSession()->fieldValueEquals('reservation_body[0][value]', $edit['reservation_body[0][value]']);
    $this->assertSession()->fieldValueEquals('uid', $edit['uid']);
    $this->assertSession()->fieldValueEquals('date[date]', $expected_form_date);
    $this->assertSession()->fieldValueEquals('date[time]', $expected_form_time);

    // Submit the form using the displayed values.
    $displayed = [];
    $displayed['subject[0][value]'] = current($this->xpath("//input[@id='edit-subject-0-value']"))->getValue();
    $displayed['reservation_body[0][value]'] = current($this->xpath("//textarea[@id='edit-reservation-body-0-value']"))->getValue();
    $displayed['uid'] = current($this->xpath("//input[@id='edit-uid']"))->getValue();
    $displayed['date[date]'] = current($this->xpath("//input[@id='edit-date-date']"))->getValue();
    $displayed['date[time]'] = current($this->xpath("//input[@id='edit-date-time']"))->getValue();
    $this->submitForm('reservation/' . $reservation->id() . '/edit', $displayed, 'Save');

    // Check that the saved reservation is still correct.
    $reservation_storage = \Drupal::entityTypeManager()->getStorage('reservation');
    $reservation_storage->resetCache([$reservation->id()]);
    /** @var \Drupal\reservation\ReservationInterface $reservation_loaded */
    $reservation_loaded = Reservation::load($reservation->id());
    $this->assertEquals($edit['subject[0][value]'], $reservation_loaded->getSubject(), 'Subject loaded.');
    $this->assertEquals($edit['reservation_body[0][value]'], $reservation_loaded->reservation_body->value, 'Reservation body loaded.');
    $this->assertEquals($web_user->id(), $reservation_loaded->getOwner()->id(), 'Name loaded.');
    $this->assertEquals($raw_date, $reservation_loaded->getCreatedTime(), 'Date loaded.');
    $this->drupalLogout();

    // Check that the date and time of the reservation are correct when edited by
    // non-admin users.
    $user_edit = [];
    $expected_created_time = $reservation_loaded->getCreatedTime();
    $this->drupalLogin($web_user);
    // Web user cannot change the reservation author.
    unset($edit['uid']);
    $this->submitForm('reservation/' . $reservation->id() . '/edit', $user_edit, 'Save');
    $reservation_storage->resetCache([$reservation->id()]);
    $reservation_loaded = Reservation::load($reservation->id());
    $this->assertEquals($expected_created_time, $reservation_loaded->getCreatedTime(), 'Expected date and time for reservation edited.');
    $this->drupalLogout();
  }

}
