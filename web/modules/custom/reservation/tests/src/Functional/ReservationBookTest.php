<?php

namespace Drupal\Tests\reservation\Functional;

use Drupal\reservation\ReservationInterface;
use Drupal\reservation\Tests\ReservationTestTrait;
use Drupal\node\Entity\Node;
use Drupal\Tests\BrowserTestBase;
use Drupal\reservation\Entity\Reservation;

/**
 * Tests visibility of reservations on book pages.
 *
 * @group reservation
 */
class ReservationBookTest extends BrowserTestBase {

  use ReservationTestTrait;

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = ['book', 'reservation'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  protected function setUp(): void {
    parent::setUp();

    // Create reservation field on book.
    $this->addDefaultReservationField('node', 'book');
  }

  /**
   * Tests reservations in book export.
   */
  public function testBookReservationPrint() {
    $book_node = Node::create([
      'type' => 'book',
      'title' => 'Book title',
      'body' => 'Book body',
    ]);
    $book_node->book['bid'] = 'new';
    $book_node->save();

    $reservation_subject = $this->randomMachineName(8);
    $reservation_body = $this->randomMachineName(8);
    $reservation = Reservation::create([
      'subject' => $reservation_subject,
      'reservation_body' => $reservation_body,
      'entity_id' => $book_node->id(),
      'entity_type' => 'node',
      'field_name' => 'reservation',
      'status' => ReservationInterface::PUBLISHED,
    ]);
    $reservation->save();

    $reservationing_user = $this->drupalCreateUser([
      'access printer-friendly version',
      'access reservations',
      'post reservations',
    ]);
    $this->drupalLogin($reservationing_user);

    $this->drupalGet('node/' . $book_node->id());

    $this->assertSession()->pageTextContains($reservation_subject);
    $this->assertSession()->pageTextContains($reservation_body);
    $this->assertSession()->pageTextContains('Add new reservation');
    // Ensure that the reservation form subject field exists.
    $this->assertSession()->fieldExists('subject[0][value]');

    $this->drupalGet('book/export/html/' . $book_node->id());

    $this->assertSession()->pageTextContains('Reservations');
    $this->assertSession()->pageTextContains($reservation_subject);
    $this->assertSession()->pageTextContains($reservation_body);

    $this->assertSession()->pageTextNotContains('Add new reservation');
    // Verify that the reservation form subject field is not found.
    $this->assertSession()->fieldNotExists('subject[0][value]');
  }

}
