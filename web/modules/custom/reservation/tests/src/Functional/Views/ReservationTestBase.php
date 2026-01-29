<?php

namespace Drupal\Tests\reservation\Functional\Views;

use Drupal\reservation\Tests\ReservationTestTrait;
use Drupal\Tests\views\Functional\ViewTestBase;
use Drupal\views\Tests\ViewTestData;
use Drupal\reservation\Entity\Reservation;

/**
 * Provides setup and helper methods for reservation views tests.
 */
abstract class ReservationTestBase extends ViewTestBase {

  use ReservationTestTrait;

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = ['node', 'reservation', 'reservation_test_views'];

  /**
   * A normal user with permission to post reservations (without approval).
   *
   * @var \Drupal\user\UserInterface
   */
  protected $account;

  /**
   * A second normal user that will author a node for $account to reservation on.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $account2;

  /**
   * Stores a node posted by the user created as $account.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $nodeUserPosted;

  /**
   * Stores a node posted by the user created as $account2.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $nodeUserReservationed;

  /**
   * Stores a reservation used by the tests.
   *
   * @var \Drupal\reservation\Entity\Reservation
   */
  protected $reservation;

  protected function setUp($import_test_views = TRUE) {
    parent::setUp($import_test_views);

    ViewTestData::createTestViews(static::class, ['reservation_test_views']);

    // Add two users, create a node with the user1 as author and another node
    // with user2 as author. For the second node add a reservation from user1.
    $this->account = $this->drupalCreateUser(['skip reservation approval']);
    $this->account2 = $this->drupalCreateUser();
    $this->drupalLogin($this->account);

    $this->drupalCreateContentType(['type' => 'page', 'name' => t('Basic page')]);
    $this->addDefaultReservationField('node', 'page');

    $this->nodeUserPosted = $this->drupalCreateNode();
    $this->nodeUserReservationed = $this->drupalCreateNode(['uid' => $this->account2->id()]);

    $reservation = [
      'uid' => $this->loggedInUser->id(),
      'entity_id' => $this->nodeUserReservationed->id(),
      'entity_type' => 'node',
      'field_name' => 'reservation',
      'subject' => 'How much wood would a woodchuck chuck',
      'cid' => '',
      'pid' => '',
      'mail' => 'someone@example.com',
    ];
    $this->reservation = Reservation::create($reservation);
    $this->reservation->save();
  }

}
