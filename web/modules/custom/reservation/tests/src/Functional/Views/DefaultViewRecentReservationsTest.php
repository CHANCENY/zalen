<?php

namespace Drupal\Tests\reservation\Functional\Views;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\reservation\ReservationInterface;
use Drupal\reservation\Entity\Reservation;
use Drupal\reservation\Tests\ReservationTestTrait;
use Drupal\views\Views;
use Drupal\Tests\views\Functional\ViewTestBase;

/**
 * Tests results for the Recent Reservations view shipped with the module.
 *
 * @group reservation
 */
class DefaultViewRecentReservationsTest extends ViewTestBase {

  use ReservationTestTrait;

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = ['node', 'reservation', 'block'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Number of results for the Master display.
   *
   * @var int
   */
  protected $masterDisplayResults = 5;

  /**
   * Number of results for the Block display.
   *
   * @var int
   */
  protected $blockDisplayResults = 5;

  /**
   * Number of results for the Page display.
   *
   * @var int
   */
  protected $pageDisplayResults = 5;

  /**
   * Will hold the reservations created for testing.
   *
   * @var array
   */
  protected $reservationsCreated = [];

  /**
   * Contains the node object used for reservations of this test.
   *
   * @var \Drupal\node\Node
   */
  public $node;

  protected function setUp($import_test_views = TRUE): void {
    parent::setUp($import_test_views);

    // Create a new content type
    $content_type = $this->drupalCreateContentType();

    // Add a node of the new content type.
    $node_data = [
      'type' => $content_type->id(),
    ];

    $this->addDefaultReservationField('node', $content_type->id());
    $this->node = $this->drupalCreateNode($node_data);

    // Force a flush of the in-memory storage.
    $this->container->get('views.views_data')->clear();

    // Create some reservations and attach them to the created node.
    for ($i = 0; $i < $this->masterDisplayResults; $i++) {
      /** @var \Drupal\reservation\ReservationInterface $reservation */
      $reservation = Reservation::create([
        'status' => ReservationInterface::PUBLISHED,
        'field_name' => 'reservation',
        'entity_type' => 'node',
        'entity_id' => $this->node->id(),
      ]);
      $reservation->setOwnerId(0);
      $reservation->setSubject('Test reservation ' . $i);
      $reservation->reservation_body->value = 'Test body ' . $i;
      $reservation->reservation_body->format = 'full_html';

      // Ensure reservations are sorted in ascending order.
      $request_time = \Drupal::time()->getRequestTime();
      $time = $request_time + ($this->masterDisplayResults - $i);
      $reservation->setCreatedTime($time);
      $reservation->changed->value = $time;

      $reservation->save();
    }

    // Store all the nodes just created to access their properties on the tests.
    $this->reservationsCreated = Reservation::loadMultiple();

    // Sort created reservations in descending order.
    ksort($this->reservationsCreated, SORT_NUMERIC);
  }

  /**
   * Tests the block defined by the reservations_recent view.
   */
  public function testBlockDisplay() {
    $user = $this->drupalCreateUser(['access reservations']);
    $this->drupalLogin($user);

    $view = Views::getView('reservations_recent');
    $view->setDisplay('block_1');
    $this->executeView($view);

    $map = [
      'subject' => 'subject',
      'cid' => 'cid',
      'reservation_field_data_created' => 'created',
    ];
    $expected_result = [];
    foreach (array_values($this->reservationsCreated) as $key => $reservation) {
      $expected_result[$key]['subject'] = $reservation->getSubject();
      $expected_result[$key]['cid'] = $reservation->id();
      $expected_result[$key]['created'] = $reservation->getCreatedTime();
    }
    $this->assertIdenticalResultset($view, $expected_result, $map);

    // Check the number of results given by the display is the expected.
    $this->assertCount($this->blockDisplayResults, $view->result,
      new FormattableMarkup('There are exactly @results reservations. Expected @expected',
        ['@results' => count($view->result), '@expected' => $this->blockDisplayResults]
      )
    );
  }

}
