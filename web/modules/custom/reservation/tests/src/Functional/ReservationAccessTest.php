<?php

namespace Drupal\Tests\reservation\Functional;

use Drupal\reservation\Entity\Reservation;
use Drupal\reservation\Tests\ReservationTestTrait;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests reservation administration and preview access.
 *
 * @group reservation
 */
class ReservationAccessTest extends BrowserTestBase {

  use ReservationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'reservation',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Node for reservationing.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $unpublishedNode;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $node_type = NodeType::create([
      'type' => 'article',
      'name' => 'Article',
    ]);
    $node_type->save();
    $node_author = $this->drupalCreateUser([
      'create article content',
      'access reservations',
    ]);

    $this->drupalLogin($this->drupalCreateUser([
      'edit own reservations',
      'skip reservation approval',
      'post reservations',
      'access reservations',
      'access content',
    ]));

    $this->addDefaultReservationField('node', 'article');
    $this->unpublishedNode = $this->createNode([
      'title' => 'This is unpublished',
      'uid' => $node_author->id(),
      'status' => 0,
      'type' => 'article',
    ]);
    $this->unpublishedNode->save();
  }

  /**
   * Tests reservationing disabled for access-blocked entities.
   */
  public function testCannotReservationOnEntitiesYouCannotView() {
    $assert = $this->assertSession();

    $reservation_url = 'reservation/reply/node/' . $this->unpublishedNode->id() . '/reservation';

    // Reservationing on an unpublished node results in access denied.
    $this->drupalGet($reservation_url);
    $assert->statusCodeEquals(403);

    // Publishing the node grants access.
    $this->unpublishedNode->setPublished()->save();
    $this->drupalGet($reservation_url);
    $assert->statusCodeEquals(200);
  }

  /**
   * Tests cannot view reservation reply form on entities you cannot view.
   */
  public function testCannotViewReservationReplyFormOnEntitiesYouCannotView() {
    $assert = $this->assertSession();

    // Create a reservation on an unpublished node.
    $reservation = Reservation::create([
      'entity_type' => 'node',
      'name' => 'Tony',
      'hostname' => 'magic.example.com',
      'mail' => 'foo@example.com',
      'subject' => 'Reservation on unpublished node',
      'entity_id' => $this->unpublishedNode->id(),
      'reservation_type' => 'reservation',
      'field_name' => 'reservation',
      'pid' => 0,
      'uid' => $this->unpublishedNode->getOwnerId(),
      'status' => 1,
    ]);
    $reservation->save();

    $reservation_url = 'reservation/reply/node/' . $this->unpublishedNode->id() . '/reservation/' . $reservation->id();

    // Replying to a reservation on an unpublished node results in access denied.
    $this->drupalGet($reservation_url);
    $assert->statusCodeEquals(403);

    // Publishing the node grants access.
    $this->unpublishedNode->setPublished()->save();
    $this->drupalGet($reservation_url);
    $assert->statusCodeEquals(200);
  }

}
