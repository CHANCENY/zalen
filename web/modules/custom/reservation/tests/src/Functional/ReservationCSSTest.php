<?php

namespace Drupal\Tests\reservation\Functional;

use Drupal\Core\Language\LanguageInterface;
use Drupal\reservation\ReservationInterface;
use Drupal\user\RoleInterface;
use Drupal\reservation\Entity\Reservation;
use Drupal\Tests\Traits\Core\GeneratePermutationsTrait;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests CSS classes on reservations.
 *
 * @group reservation
 */
class ReservationCSSTest extends ReservationTestBase {

  use GeneratePermutationsTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'classy';

  protected function setUp(): void {
    parent::setUp();

    // Allow anonymous users to see reservations.
    user_role_grant_permissions(RoleInterface::ANONYMOUS_ID, [
      'access reservations',
      'access content',
    ]);
  }

  /**
   * Tests CSS classes on reservations.
   */
  public function testReservationClasses() {
    // Create all permutations for reservations, users, and nodes.
    $parameters = [
      'node_uid' => [0, $this->webUser->id()],
      'reservation_uid' => [0, $this->webUser->id(), $this->adminUser->id()],
      'reservation_status' => [ReservationInterface::PUBLISHED, ReservationInterface::NOT_PUBLISHED],
      'user' => ['anonymous', 'authenticated', 'admin'],
    ];
    $permutations = $this->generatePermutations($parameters);

    foreach ($permutations as $case) {
      // Create a new node.
      $node = $this->drupalCreateNode(['type' => 'article', 'uid' => $case['node_uid']]);

      // Add a reservation.
      /** @var \Drupal\reservation\ReservationInterface $reservation */
      $reservation = Reservation::create([
        'entity_id' => $node->id(),
        'entity_type' => 'node',
        'field_name' => 'reservation',
        'uid' => $case['reservation_uid'],
        'status' => $case['reservation_status'],
        'subject' => $this->randomMachineName(),
        'language' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
        'reservation_body' => [LanguageInterface::LANGCODE_NOT_SPECIFIED => [$this->randomMachineName()]],
      ]);
      $reservation->save();

      // Adjust the current/viewing user.
      switch ($case['user']) {
        case 'anonymous':
          if ($this->loggedInUser) {
            $this->drupalLogout();
          }
          $case['user_uid'] = 0;
          break;

        case 'authenticated':
          $this->drupalLogin($this->webUser);
          $case['user_uid'] = $this->webUser->id();
          break;

        case 'admin':
          $this->drupalLogin($this->adminUser);
          $case['user_uid'] = $this->adminUser->id();
          break;
      }
      // Request the node with the reservation.
      $this->drupalGet('node/' . $node->id());
      $settings = $this->getDrupalSettings();

      // Verify the data-history-node-id attribute, which is necessary for the
      // by-viewer class and the "new" indicator, see below.
      $this->assertCount(1, $this->xpath('//*[@data-history-node-id="' . $node->id() . '"]'), 'data-history-node-id attribute is set on node.');

      // Verify classes if the reservation is visible for the current user.
      if ($case['reservation_status'] == ReservationInterface::PUBLISHED || $case['user'] == 'admin') {
        // Verify the by-anonymous class.
        $reservations = $this->xpath('//*[contains(@class, "reservation") and contains(@class, "by-anonymous")]');
        if ($case['reservation_uid'] == 0) {
          $this->assertCount(1, $reservations, 'by-anonymous class found.');
        }
        else {
          $this->assertCount(0, $reservations, 'by-anonymous class not found.');
        }

        // Verify the by-node-author class.
        $reservations = $this->xpath('//*[contains(@class, "reservation") and contains(@class, "by-node-author")]');
        if ($case['reservation_uid'] > 0 && $case['reservation_uid'] == $case['node_uid']) {
          $this->assertCount(1, $reservations, 'by-node-author class found.');
        }
        else {
          $this->assertCount(0, $reservations, 'by-node-author class not found.');
        }

        // Verify the data-reservation-user-id attribute, which is used by the
        // drupal.reservation-by-viewer library to add a by-viewer when the current
        // user (the viewer) was the author of the reservation. We do this in Java-
        // Script to prevent breaking the render cache.
        $this->assertCount(1, $this->xpath('//*[contains(@class, "reservation") and @data-reservation-user-id="' . $case['reservation_uid'] . '"]'), 'data-reservation-user-id attribute is set on reservation.');
        $this->assertSession()->responseContains(\Drupal::service('extension.path.resolver')->getPath('module', 'reservation') . '/js/reservation-by-viewer.js');
      }

      // Verify the unpublished class.
      $reservations = $this->xpath('//*[contains(@class, "reservation") and contains(@class, "unpublished")]');
      if ($case['reservation_status'] == ReservationInterface::NOT_PUBLISHED && $case['user'] == 'admin') {
        $this->assertCount(1, $reservations, 'unpublished class found.');
      }
      else {
        $this->assertCount(0, $reservations, 'unpublished class not found.');
      }

      // Verify the data-reservation-timestamp attribute, which is used by the
      // drupal.reservation-new-indicator library to add a "new" indicator to each
      // reservation that was created or changed after the last time the current
      // user read the corresponding node.
      if ($case['reservation_status'] == ReservationInterface::PUBLISHED || $case['user'] == 'admin') {
        $this->assertCount(1, $this->xpath('//*[contains(@class, "reservation")]/*[@data-reservation-timestamp="' . $reservation->getChangedTime() . '"]'), 'data-reservation-timestamp attribute is set on reservation');
        $expectedJS = ($case['user'] !== 'anonymous');
        $this->assertSame($expectedJS, isset($settings['ajaxPageState']['libraries']) && in_array('reservation/drupal.reservation-new-indicator', explode(',', $settings['ajaxPageState']['libraries'])), 'drupal.reservation-new-indicator library is present.');
      }
    }
  }

}
