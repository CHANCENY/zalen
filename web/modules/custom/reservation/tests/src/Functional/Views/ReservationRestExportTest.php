<?php

namespace Drupal\Tests\reservation\Functional\Views;

use Drupal\Component\Serialization\Json;
use Drupal\reservation\Entity\Reservation;
use Drupal\Tests\BrowserTestBase;


/**
 * Tests a reservation rest export view.
 *
 * @group reservation
 */
class ReservationRestExportTest extends ReservationTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_reservation_rest'];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'reservation',
    'reservation_test_views',
    'rest',
    'hal',
  ];

  protected function setUp($import_test_views = TRUE): void {
    parent::setUp($import_test_views);
    // Add another anonymous reservation.
    $reservation = [
      'uid' => 0,
      'entity_id' => $this->nodeUserReservationed->id(),
      'entity_type' => 'node',
      'field_name' => 'reservation',
      'subject' => 'A lot, apparently',
      'cid' => '',
      'pid' => $this->reservation->id(),
      'mail' => 'someone@example.com',
      'name' => 'bobby tables',
      'hostname' => 'public.example.com',
    ];
    $this->reservation = Reservation::create($reservation);
    $this->reservation->save();

    $user = $this->drupalCreateUser(['access reservations']);
    $this->drupalLogin($user);
  }

  /**
   * Test reservation row.
   */
  public function testReservationRestExport() {
    $this->drupalGet(sprintf('node/%d/reservations', $this->nodeUserReservationed->id()), ['query' => ['_format' => 'hal_json']]);
    $this->assertSession()->statusCodeEquals(200);
    $contents = Json::decode($this->getSession()->getPage()->getContent());
    $this->assertEquals('How much wood would a woodchuck chuck', $contents[0]['subject']);
    $this->assertEquals('A lot, apparently', $contents[1]['subject']);
    $this->assertCount(2, $contents);

    // Ensure field-level access is respected - user shouldn't be able to see
    // mail or hostname fields.
    $this->assertSession()->pageTextNotContains('someone@example.com');
    $this->assertSession()->pageTextNotContains('public.example.com');
  }

}
