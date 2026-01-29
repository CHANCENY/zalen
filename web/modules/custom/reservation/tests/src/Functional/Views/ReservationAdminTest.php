<?php

namespace Drupal\Tests\reservation\Functional\Views;

use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\reservation\Entity\Reservation;
use Drupal\reservation\Plugin\Field\FieldType\ReservationItemInterface;
use Drupal\Tests\reservation\Functional\ReservationTestBase as ReservationBrowserTestBase;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\user\RoleInterface;
use Drupal\views\Views;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests reservation approval functionality.
 *
 * @group reservation
 */
class ReservationAdminTest extends ReservationBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'classy';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    \Drupal::service('module_installer')->install(['views']);
    $view = Views::getView('reservation');
    $view->storage->enable()->save();
    \Drupal::service('router.builder')->rebuildIfNeeded();
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
    $this->drupalPlaceBlock('page_title_block');
    $this->drupalLogin($this->adminUser);
    // Ensure that doesn't require contact info.
    $this->setReservationAnonymous('0');

    // Test that the reservations page loads correctly when there are no reservations.
    $this->drupalGet('admin/content/reservation');
    $this->assertSession()->pageTextContains('No reservations available.');

    // Assert the expose filters on the admin page.
    $this->assertSession()->fieldExists('subject');
    $this->assertSession()->fieldExists('author_name');
    $this->assertSession()->fieldExists('langcode');

    $this->drupalLogout();

    // Post anonymous reservation without contact info.
    $body = $this->getRandomGenerator()->sentences(4);
    $subject = Unicode::truncate(trim(Html::decodeEntities(strip_tags($body))), 29, TRUE, TRUE);
    $author_name = $this->randomMachineName();
    $this->submitForm('reservation/reply/node/' . $this->node->id() . '/reservation', [
      'name' => $author_name,
      'reservation_body[0][value]' => $body,
    ], 'Save');
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
    $edit = [];
    $edit['action'] = 'reservation_publish_action';
    $edit['reservation_bulk_form[0]'] = $anonymous_reservation4->id();
    $this->submitForm('admin/content/reservation/approval', $edit, 'Apply to selected items');

    $this->assertSession()->pageTextContains('Publish reservation was applied to 1 item.');
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

    // Assert the expose filters on the admin page.
    $this->assertSession()->fieldExists('subject');
    $this->assertSession()->fieldExists('author_name');
    $this->assertSession()->fieldExists('langcode');

    $edit = [
      "action" => 'reservation_publish_action',
      "reservation_bulk_form[1]" => $reservations[0]->id(),
      "reservation_bulk_form[0]" => $reservations[1]->id(),
    ];
    $this->submitForm($edit, 'Apply to selected items');
    $this->assertSession()->pageTextContains('Unapproved reservations (0)');

    // Test message when no reservations selected.
    $this->submitForm('admin/content/reservation', [], 'Apply to selected items');
    $this->assertSession()->pageTextContains('Select one or more reservations to perform the update on.');

    $subject_link = $this->xpath('//table/tbody/tr/td/a[contains(@href, :href) and contains(@title, :title) and text()=:text]', [
      ':href' => $reservations[0]->permalink()->toString(),
      ':title' => Unicode::truncate($reservations[0]->get('reservation_body')->value, 128),
      ':text' => $reservations[0]->getSubject(),
    ]);
    $this->assertTrue(!empty($subject_link), 'Reservation listing shows the correct subject link.');
    // Verify that anonymous author name is displayed correctly.
    $this->assertSession()->pageTextContains($author_name . ' (not verified)');

    $subject_link = $this->xpath('//table/tbody/tr/td/a[contains(@href, :href) and contains(@title, :title) and text()=:text]', [
      ':href' => $anonymous_reservation4->permalink()->toString(),
      ':title' => Unicode::truncate($body, 128),
      ':text' => $subject,
    ]);
    $this->assertTrue(!empty($subject_link), 'Reservation listing shows the correct subject link.');
    // Verify that anonymous author name is displayed correctly.
    $this->assertSession()->pageTextContains($author_name . ' (not verified)');

    // Delete multiple reservations in one operation.
    $edit = [
      'action' => 'reservation_delete_action',
      "reservation_bulk_form[1]" => $reservations[0]->id(),
      "reservation_bulk_form[0]" => $reservations[1]->id(),
      "reservation_bulk_form[2]" => $anonymous_reservation4->id(),
    ];
    $this->submitForm($edit, 'Apply to selected items');
    $this->assertSession()->pageTextContains('Are you sure you want to delete these reservations and all their children?');
    $this->submitForm([], 'Delete');
    $this->assertSession()->pageTextContains('No reservations available.');

    // Make sure the label of unpublished node is not visible on listing page.
    $this->drupalGet('admin/content/reservation');
    $this->postReservation($this->node, $this->randomMachineName());
    $this->drupalLogout();
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/content/reservation');
    // Verify that reservation admin can see the title of a published node.
    $this->assertSession()->pageTextContains(Html::escape($this->node->label()));
    $this->node->setUnpublished()->save();
    $this->assertFalse($this->node->isPublished(), 'Node is unpublished now.');
    $this->drupalGet('admin/content/reservation');
    // Verify that reservation admin cannot see the title of an unpublished node.
    $this->assertSession()->pageTextNotContains(Html::escape($this->node->label()));
    $this->drupalLogout();
    $node_access_user = $this->drupalCreateUser([
      'administer reservations',
      'bypass node access',
    ]);
    $this->drupalLogin($node_access_user);
    $this->drupalGet('admin/content/reservation');
    // Verify that reservation admin with bypass node access permissions can still
    // see the title of a published node.
    $this->assertSession()->pageTextContains(Html::escape($this->node->label()));
  }

  /**
   * Tests reservationed entity label of admin view.
   */
  public function testReservationedEntityLabel() {
    \Drupal::service('module_installer')->install(['block_content']);
    \Drupal::service('router.builder')->rebuildIfNeeded();
    $bundle = BlockContentType::create([
      'id' => 'basic',
      'label' => 'basic',
      'revision' => FALSE,
    ]);
    $bundle->save();
    $block_content = BlockContent::create([
      'type' => 'basic',
      'label' => 'Some block title',
      'info' => 'Test block',
    ]);
    $block_content->save();

    // Create reservation field on block_content.
    $this->addDefaultReservationField('block_content', 'basic', 'block_reservation', ReservationItemInterface::OPEN, 'block_reservation');
    $this->drupalLogin($this->webUser);
    // Post a reservation to node.
    $node_reservation = $this->postReservation($this->node, $this->randomMachineName(), $this->randomMachineName(), TRUE);
    // Post a reservation to block content.
    $block_content_reservation = $this->postReservation($block_content, $this->randomMachineName(), $this->randomMachineName(), TRUE, 'block_reservation');
    $this->drupalLogout();
    // Login as admin to test the admin reservation page.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/content/reservation');

    $reservation_author_link = $this->xpath('//table/tbody/tr[1]/td/a[contains(@href, :href) and text()=:text]', [
      ':href' => $this->webUser->toUrl()->toString(),
      ':text' => $this->webUser->label(),
    ]);
    $this->assertTrue(!empty($reservation_author_link), 'Reservation listing links to reservation author.');
    $reservation_author_link = $this->xpath('//table/tbody/tr[2]/td/a[contains(@href, :href) and text()=:text]', [
      ':href' => $this->webUser->toUrl()->toString(),
      ':text' => $this->webUser->label(),
    ]);
    $this->assertTrue(!empty($reservation_author_link), 'Reservation listing links to reservation author.');
    // Admin page contains label of both entities.
    $this->assertSession()->pageTextContains(Html::escape($this->node->label()));
    $this->assertSession()->pageTextContains(Html::escape($block_content->label()));
    // Admin page contains subject of both entities.
    $this->assertSession()->pageTextContains(Html::escape($node_reservation->label()));
    $this->assertSession()->pageTextContains(Html::escape($block_content_reservation->label()));
  }

}
