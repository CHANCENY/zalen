<?php

namespace Drupal\Tests\reservation\Functional;

use Drupal\reservation\ReservationManagerInterface;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\node\Entity\Node;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests paging of reservations and their settings.
 *
 * @group reservation
 */
class ReservationPagerTest extends ReservationTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'classy';

  /**
   * Confirms reservation paging works correctly with flat and threaded reservations.
   */
  public function testReservationPaging() {
    $this->drupalLogin($this->adminUser);

    // Set reservation variables.
    $this->setReservationForm(TRUE);
    $this->setReservationSubject(TRUE);
    $this->setReservationPreview(DRUPAL_DISABLED);

    // Create a node and three reservations.
    $node = $this->drupalCreateNode(['type' => 'article', 'promote' => 1]);
    $reservations = [];
    $reservations[] = $this->postReservation($node, $this->randomMachineName(), $this->randomMachineName(), TRUE);
    $reservations[] = $this->postReservation($node, $this->randomMachineName(), $this->randomMachineName(), TRUE);
    $reservations[] = $this->postReservation($node, $this->randomMachineName(), $this->randomMachineName(), TRUE);

    $this->setReservationSettings('default_mode', ReservationManagerInterface::RESERVATION_MODE_FLAT, 'Reservation paging changed.');

    // Set reservations to one per page so that we are able to test paging without
    // needing to insert large numbers of reservations.
    $this->setReservationsPerPage(1);

    // Check the first page of the node, and confirm the correct reservations are
    // shown.
    $this->drupalGet('node/' . $node->id());
    $this->assertSession()->responseContains(t('next'));
    $this->assertTrue($this->reservationExists($reservations[0]), 'Reservation 1 appears on page 1.');
    $this->assertFalse($this->reservationExists($reservations[1]), 'Reservation 2 does not appear on page 1.');
    $this->assertFalse($this->reservationExists($reservations[2]), 'Reservation 3 does not appear on page 1.');

    // Check the second page.
    $this->drupalGet('node/' . $node->id(), ['query' => ['page' => 1]]);
    $this->assertTrue($this->reservationExists($reservations[1]), 'Reservation 2 appears on page 2.');
    $this->assertFalse($this->reservationExists($reservations[0]), 'Reservation 1 does not appear on page 2.');
    $this->assertFalse($this->reservationExists($reservations[2]), 'Reservation 3 does not appear on page 2.');

    // Check the third page.
    $this->drupalGet('node/' . $node->id(), ['query' => ['page' => 2]]);
    $this->assertTrue($this->reservationExists($reservations[2]), 'Reservation 3 appears on page 3.');
    $this->assertFalse($this->reservationExists($reservations[0]), 'Reservation 1 does not appear on page 3.');
    $this->assertFalse($this->reservationExists($reservations[1]), 'Reservation 2 does not appear on page 3.');

    // Post a reply to the oldest reservation and test again.
    $oldest_reservation = reset($reservations);
    $this->drupalGet('reservation/reply/node/' . $node->id() . '/reservation/' . $oldest_reservation->id());
    $reply = $this->postReservation(NULL, $this->randomMachineName(), $this->randomMachineName(), TRUE);

    $this->setReservationsPerPage(2);
    // We are still in flat view - the replies should not be on the first page,
    // even though they are replies to the oldest reservation.
    $this->drupalGet('node/' . $node->id(), ['query' => ['page' => 0]]);
    $this->assertFalse($this->reservationExists($reply, TRUE), 'In flat mode, reply does not appear on page 1.');

    // If we switch to threaded mode, the replies on the oldest reservation
    // should be bumped to the first page and reservation 6 should be bumped
    // to the second page.
    $this->setReservationSettings('default_mode', ReservationManagerInterface::RESERVATION_MODE_THREADED, 'Switched to threaded mode.');
    $this->drupalGet('node/' . $node->id(), ['query' => ['page' => 0]]);
    $this->assertTrue($this->reservationExists($reply, TRUE), 'In threaded mode, reply appears on page 1.');
    $this->assertFalse($this->reservationExists($reservations[1]), 'In threaded mode, reservation 2 has been bumped off of page 1.');

    // If (# replies > # reservations per page) in threaded expanded view,
    // the overage should be bumped.
    $reply2 = $this->postReservation(NULL, $this->randomMachineName(), $this->randomMachineName(), TRUE);
    $this->drupalGet('node/' . $node->id(), ['query' => ['page' => 0]]);
    $this->assertFalse($this->reservationExists($reply2, TRUE), 'In threaded mode where # replies > # reservations per page, the newest reply does not appear on page 1.');

    // Test that the page build process does not somehow generate errors when
    // # reservations per page is set to 0.
    $this->setReservationsPerPage(0);
    $this->drupalGet('node/' . $node->id(), ['query' => ['page' => 0]]);
    $this->assertFalse($this->reservationExists($reply2, TRUE), 'Threaded mode works correctly when reservations per page is 0.');

    $this->drupalLogout();
  }

  /**
   * Confirms reservation paging works correctly with flat and threaded reservations.
   */
  public function testReservationPermalink() {
    $this->drupalLogin($this->adminUser);

    // Set reservation variables.
    $this->setReservationForm(TRUE);
    $this->setReservationSubject(TRUE);
    $this->setReservationPreview(DRUPAL_DISABLED);

    // Create a node and three reservations.
    $node = $this->drupalCreateNode(['type' => 'article', 'promote' => 1]);
    $reservations = [];
    $reservations[] = $this->postReservation($node, 'reservation 1: ' . $this->randomMachineName(), $this->randomMachineName(), TRUE);
    $reservations[] = $this->postReservation($node, 'reservation 2: ' . $this->randomMachineName(), $this->randomMachineName(), TRUE);
    $reservations[] = $this->postReservation($node, 'reservation 3: ' . $this->randomMachineName(), $this->randomMachineName(), TRUE);

    $this->setReservationSettings('default_mode', ReservationManagerInterface::RESERVATION_MODE_FLAT, 'Reservation paging changed.');

    // Set reservations to one per page so that we are able to test paging without
    // needing to insert large numbers of reservations.
    $this->setReservationsPerPage(1);

    // Navigate to each reservation permalink as anonymous and assert it appears on
    // the page.
    foreach ($reservations as $index => $reservation) {
      $this->drupalGet($reservation->toUrl());
      $this->assertTrue($this->reservationExists($reservation), sprintf('Reservation %d appears on page %d.', $index + 1, $index + 1));
    }
  }

  /**
   * Tests reservation ordering and threading.
   */
  public function testReservationOrderingThreading() {
    $this->drupalLogin($this->adminUser);

    // Set reservation variables.
    $this->setReservationForm(TRUE);
    $this->setReservationSubject(TRUE);
    $this->setReservationPreview(DRUPAL_DISABLED);

    // Display all the reservations on the same page.
    $this->setReservationsPerPage(1000);

    // Create a node and three reservations.
    $node = $this->drupalCreateNode(['type' => 'article', 'promote' => 1]);
    $reservations = [];
    $reservations[] = $this->postReservation($node, $this->randomMachineName(), $this->randomMachineName(), TRUE);
    $reservations[] = $this->postReservation($node, $this->randomMachineName(), $this->randomMachineName(), TRUE);
    $reservations[] = $this->postReservation($node, $this->randomMachineName(), $this->randomMachineName(), TRUE);

    // Post a reply to the second reservation.
    $this->drupalGet('reservation/reply/node/' . $node->id() . '/reservation/' . $reservations[1]->id());
    $reservations[] = $this->postReservation(NULL, $this->randomMachineName(), $this->randomMachineName(), TRUE);

    // Post a reply to the first reservation.
    $this->drupalGet('reservation/reply/node/' . $node->id() . '/reservation/' . $reservations[0]->id());
    $reservations[] = $this->postReservation(NULL, $this->randomMachineName(), $this->randomMachineName(), TRUE);

    // Post a reply to the last reservation.
    $this->drupalGet('reservation/reply/node/' . $node->id() . '/reservation/' . $reservations[2]->id());
    $reservations[] = $this->postReservation(NULL, $this->randomMachineName(), $this->randomMachineName(), TRUE);

    // Post a reply to the second reservation.
    $this->drupalGet('reservation/reply/node/' . $node->id() . '/reservation/' . $reservations[3]->id());
    $reservations[] = $this->postReservation(NULL, $this->randomMachineName(), $this->randomMachineName(), TRUE);

    // At this point, the reservation tree is:
    // - 0
    //   - 4
    // - 1
    //   - 3
    //     - 6
    // - 2
    //   - 5

    $this->setReservationSettings('default_mode', ReservationManagerInterface::RESERVATION_MODE_FLAT, 'Reservation paging changed.');

    $expected_order = [
      0,
      1,
      2,
      3,
      4,
      5,
      6,
    ];
    $this->drupalGet('node/' . $node->id());
    $this->assertReservationOrder($reservations, $expected_order);

    $this->setReservationSettings('default_mode', ReservationManagerInterface::RESERVATION_MODE_THREADED, 'Switched to threaded mode.');

    $expected_order = [
      0,
      4,
      1,
      3,
      6,
      2,
      5,
    ];
    $this->drupalGet('node/' . $node->id());
    $this->assertReservationOrder($reservations, $expected_order);
  }

  /**
   * Asserts that the reservations are displayed in the correct order.
   *
   * @param \Drupal\reservation\ReservationInterface[] $reservations
   *   An array of reservations, must be of the type ReservationInterface.
   * @param array $expected_order
   *   An array of keys from $reservations describing the expected order.
   */
  public function assertReservationOrder(array $reservations, array $expected_order) {
    $expected_cids = [];

    // First, rekey the expected order by cid.
    foreach ($expected_order as $key) {
      $expected_cids[] = $reservations[$key]->id();
    }

    $reservation_anchors = $this->xpath('//article[starts-with(@id,"reservation-")]');
    $result_order = [];
    foreach ($reservation_anchors as $anchor) {
      $result_order[] = substr($anchor->getAttribute('id'), 8);
    }
    return $this->assertEquals($expected_cids, $result_order, new FormattableMarkup('Reservation order: expected @expected, returned @returned.', ['@expected' => implode(',', $expected_cids), '@returned' => implode(',', $result_order)]));
  }

  /**
   * Tests calculation of first page with new reservation.
   */
  public function testReservationNewPageIndicator() {
    $this->drupalLogin($this->adminUser);

    // Set reservation variables.
    $this->setReservationForm(TRUE);
    $this->setReservationSubject(TRUE);
    $this->setReservationPreview(DRUPAL_DISABLED);

    // Set reservations to one per page so that we are able to test paging without
    // needing to insert large numbers of reservations.
    $this->setReservationsPerPage(1);

    // Create a node and three reservations.
    $node = $this->drupalCreateNode(['type' => 'article', 'promote' => 1]);
    $reservations = [];
    $reservations[] = $this->postReservation($node, $this->randomMachineName(), $this->randomMachineName(), TRUE);
    $reservations[] = $this->postReservation($node, $this->randomMachineName(), $this->randomMachineName(), TRUE);
    $reservations[] = $this->postReservation($node, $this->randomMachineName(), $this->randomMachineName(), TRUE);

    // Post a reply to the second reservation.
    $this->drupalGet('reservation/reply/node/' . $node->id() . '/reservation/' . $reservations[1]->id());
    $reservations[] = $this->postReservation(NULL, $this->randomMachineName(), $this->randomMachineName(), TRUE);

    // Post a reply to the first reservation.
    $this->drupalGet('reservation/reply/node/' . $node->id() . '/reservation/' . $reservations[0]->id());
    $reservations[] = $this->postReservation(NULL, $this->randomMachineName(), $this->randomMachineName(), TRUE);

    // Post a reply to the last reservation.
    $this->drupalGet('reservation/reply/node/' . $node->id() . '/reservation/' . $reservations[2]->id());
    $reservations[] = $this->postReservation(NULL, $this->randomMachineName(), $this->randomMachineName(), TRUE);

    // At this point, the reservation tree is:
    // - 0
    //   - 4
    // - 1
    //   - 3
    // - 2
    //   - 5

    $this->setReservationSettings('default_mode', ReservationManagerInterface::RESERVATION_MODE_FLAT, 'Reservation paging changed.');

    $expected_pages = [
      // Page of reservation 5
      1 => 5,
      // Page of reservation 4
      2 => 4,
      // Page of reservation 3
      3 => 3,
      // Page of reservation 2
      4 => 2,
      // Page of reservation 1
      5 => 1,
      // Page of reservation 0
      6 => 0,
    ];

    $node = Node::load($node->id());
    foreach ($expected_pages as $new_replies => $expected_page) {
      $returned_page = \Drupal::entityTypeManager()->getStorage('reservation')
        ->getNewReservationPageNumber($node->get('reservation')->reservation_count, $new_replies, $node, 'reservation');
      $this->assertSame($expected_page, $returned_page, new FormattableMarkup('Flat mode, @new replies: expected page @expected, returned page @returned.', ['@new' => $new_replies, '@expected' => $expected_page, '@returned' => $returned_page]));
    }

    $this->setReservationSettings('default_mode', ReservationManagerInterface::RESERVATION_MODE_THREADED, 'Switched to threaded mode.');

    $expected_pages = [
      // Page of reservation 5
      1 => 5,
      // Page of reservation 4
      2 => 1,
      // Page of reservation 4
      3 => 1,
      // Page of reservation 4
      4 => 1,
      // Page of reservation 4
      5 => 1,
      // Page of reservation 0
      6 => 0,
    ];

    \Drupal::entityTypeManager()->getStorage('node')->resetCache([$node->id()]);
    $node = Node::load($node->id());
    foreach ($expected_pages as $new_replies => $expected_page) {
      $returned_page = \Drupal::entityTypeManager()->getStorage('reservation')
        ->getNewReservationPageNumber($node->get('reservation')->reservation_count, $new_replies, $node, 'reservation');
        $this->assertEquals($expected_page, $returned_page, new FormattableMarkup('Threaded mode, @new replies: expected page @expected, returned page @returned.', ['@new' => $new_replies, '@expected' => $expected_page, '@returned' => $returned_page]));
    }
  }

  /**
   * Confirms reservation paging works correctly with two pagers.
   */
  public function testTwoPagers() {
    // Add another field to article content-type.
    $this->addDefaultReservationField('node', 'article', 'reservation_2');
    // Set default to display reservation list with unique pager id.
    \Drupal::service('entity_display.repository')
      ->getViewDisplay('node', 'article')
      ->setComponent('reservation_2', [
        'label' => 'hidden',
        'type' => 'reservation_default',
        'weight' => 30,
        'settings' => [
          'pager_id' => 1,
          'view_mode' => 'default',
        ],
      ])
      ->save();

    // Make sure pager appears in formatter summary and settings form.
    $account = $this->drupalCreateUser(['administer node display']);
    $this->drupalLogin($account);
    $this->drupalGet('admin/structure/types/manage/article/display');
    // No summary for standard pager.
    $this->assertSession()->pageTextNotContains('Pager ID: 0');
    $this->assertSession()->pageTextContains('Pager ID: 1');
    $this->submitForm([], 'reservation_settings_edit');
    // Change default pager to 2.
    $this->submitForm(['fields[reservation][settings_edit_form][settings][pager_id]' => 2], 'Save');
    $this->assertSession()->pageTextContains('Pager ID: 2');
    // Revert the changes.
    $this->submitForm([], 'reservation_settings_edit');
    $this->submitForm(['fields[reservation][settings_edit_form][settings][pager_id]' => 0], 'Save');
    // No summary for standard pager.
    $this->assertSession()->pageTextNotContains('Pager ID: 0');

    $this->drupalLogin($this->adminUser);

    // Add a new node with both reservation fields open.
    $node = $this->drupalCreateNode(['type' => 'article', 'promote' => 1, 'uid' => $this->webUser->id()]);
    // Set reservation options.
    $reservations = [];
    foreach (['reservation', 'reservation_2'] as $field_name) {
      $this->setReservationForm(TRUE, $field_name);
      $this->setReservationPreview(DRUPAL_OPTIONAL, $field_name);
      $this->setReservationSettings('default_mode', ReservationManagerInterface::RESERVATION_MODE_FLAT, 'Reservation paging changed.', $field_name);

      // Set reservations to one per page so that we are able to test paging without
      // needing to insert large numbers of reservations.
      $this->setReservationsPerPage(1, $field_name);
      for ($i = 0; $i < 3; $i++) {
        $reservation = t('Reservation @count on field @field', [
          '@count' => $i + 1,
          '@field' => $field_name,
        ]);
        $reservations[] = $this->postReservation($node, $reservation, $reservation, TRUE, $field_name);
      }
    }

    // Check the first page of the node, and confirm the correct reservations are
    // shown.
    $this->drupalGet('node/' . $node->id());
    $this->assertSession()->responseContains(t('next'));
    $this->assertSession()->responseContains('Reservation 1 on field reservation');
    $this->assertSession()->responseContains('Reservation 1 on field reservation_2');
    // Navigate to next page of field 1.
    $this->clickLinkWithXPath('//h3/a[normalize-space(text())=:label]/ancestor::section[1]//a[@rel="next"]', [':label' => 'Reservation 1 on field reservation']);
    // Check only one pager updated.
    $this->assertSession()->responseContains('Reservation 2 on field reservation');
    $this->assertSession()->responseContains('Reservation 1 on field reservation_2');
    // Return to page 1.
    $this->drupalGet('node/' . $node->id());
    // Navigate to next page of field 2.
    $this->clickLinkWithXPath('//h3/a[normalize-space(text())=:label]/ancestor::section[1]//a[@rel="next"]', [':label' => 'Reservation 1 on field reservation_2']);
    // Check only one pager updated.
    $this->assertSession()->responseContains('Reservation 1 on field reservation');
    $this->assertSession()->responseContains('Reservation 2 on field reservation_2');
    // Navigate to next page of field 1.
    $this->clickLinkWithXPath('//h3/a[normalize-space(text())=:label]/ancestor::section[1]//a[@rel="next"]', [':label' => 'Reservation 1 on field reservation']);
    // Check only one pager updated.
    $this->assertSession()->responseContains('Reservation 2 on field reservation');
    $this->assertSession()->responseContains('Reservation 2 on field reservation_2');
  }

  /**
   * Follows a link found at a give xpath query.
   *
   * Will click the first link found with the given xpath query by default,
   * or a later one if an index is given.
   *
   * If the link is discovered and clicked, the test passes. Fail otherwise.
   *
   * @param string $xpath
   *   Xpath query that targets an anchor tag, or set of anchor tags.
   * @param array $arguments
   *   An array of arguments with keys in the form ':name' matching the
   *   placeholders in the query. The values may be either strings or numeric
   *   values.
   * @param int $index
   *   Link position counting from zero.
   *
   * @return string|false
   *   Page contents on success, or FALSE on failure.
   *
   * @see \Drupal\Tests\UiHelperTrait::clickLink()
   */
  protected function clickLinkWithXPath($xpath, $arguments = [], $index = 0) {
    $url_before = $this->getUrl();
    $urls = $this->xpath($xpath, $arguments);
    if (isset($urls[$index])) {
      $url_target = $this->getAbsoluteUrl($urls[$index]->getAttribute('href'));
      return $this->drupalGet($url_target);
    }
    $this->fail(new FormattableMarkup('Link %label does not exist on @url_before', ['%label' => $xpath, '@url_before' => $url_before]), 'Browser');
    return FALSE;
  }

}
