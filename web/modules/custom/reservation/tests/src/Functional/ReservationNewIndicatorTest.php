<?php

namespace Drupal\Tests\reservation\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Language\LanguageInterface;
use Drupal\reservation\ReservationInterface;
use Drupal\Core\Url;
use Drupal\reservation\Entity\Reservation;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the 'new' indicator posted on reservations.
 *
 * @group reservation
 */
class ReservationNewIndicatorTest extends ReservationTestBase {

  /**
   * Use the main node listing to test rendering on teasers.
   *
   * @var array
   *
   * @todo Remove this dependency.
   */
  protected static $modules = ['views'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Get node "x new reservations" metadata from the server for the current user.
   *
   * @param array $node_ids
   *   An array of node IDs.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The HTTP response.
   */
  protected function renderNewReservationsNodeLinks(array $node_ids) {
    $client = $this->getHttpClient();
    $url = Url::fromRoute('reservation.new_reservations_node_links');

    return $client->request('POST', $this->buildUrl($url), [
      'cookies' => $this->getSessionCookies(),
      'http_errors' => FALSE,
      'form_params' => [
        'node_ids' => $node_ids,
        'field_name' => 'reservation',
      ],
    ]);
  }

  /**
   * Tests new reservation marker.
   */
  public function testReservationNewReservationsIndicator() {
    // Test if the right links are displayed when no reservation is present for the
    // node.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('node');
    $this->assertSession()->linkNotExists('0 reservations');
    $this->assertSession()->linkExists('Read more');
    // Verify the data-history-node-last-reservation-timestamp attribute, which is
    // used by the drupal.node-new-reservations-link library to determine whether
    // a "x new reservations" link might be necessary or not. We do this in
    // JavaScript to prevent breaking the render cache.
    $this->assertCount(0, $this->xpath('//*[@data-history-node-last-reservation-timestamp]'), 'data-history-node-last-reservation-timestamp attribute is not set.');

    // Create a new reservation. This helper function may be run with different
    // reservation settings so use $reservation->save() to avoid complex setup.
    /** @var \Drupal\reservation\ReservationInterface $reservation */
    $reservation = Reservation::create([
      'cid' => NULL,
      'entity_id' => $this->node->id(),
      'entity_type' => 'node',
      'field_name' => 'reservation',
      'pid' => 0,
      'uid' => $this->loggedInUser->id(),
      'status' => ReservationInterface::PUBLISHED,
      'subject' => $this->randomMachineName(),
      'hostname' => '127.0.0.1',
      'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
      'reservation_body' => [LanguageInterface::LANGCODE_NOT_SPECIFIED => [$this->randomMachineName()]],
    ]);
    $reservation->save();
    $this->drupalLogout();

    // Log in with 'web user' and check reservation links.
    $this->drupalLogin($this->webUser);
    $this->drupalGet('node');
    // Verify the data-history-node-last-reservation-timestamp attribute. Given its
    // value, the drupal.node-new-reservations-link library would determine that the
    // node received a reservation after the user last viewed it, and hence it would
    // perform an HTTP request to render the "new reservations" node link.
    $this->assertCount(1, $this->xpath('//*[@data-history-node-last-reservation-timestamp="' . $reservation->getChangedTime() . '"]'), 'data-history-node-last-reservation-timestamp attribute is set to the correct value.');
    $this->assertCount(1, $this->xpath('//*[@data-history-node-field-name="reservation"]'), 'data-history-node-field-name attribute is set to the correct value.');
    // The data will be pre-seeded on this particular page in drupalSettings, to
    // avoid the need for the client to make a separate request to the server.
    $settings = $this->getDrupalSettings();
    $this->assertEquals(['lastReadTimestamps' => [1 => 0]], $settings['history']);
    $this->assertEquals([
      'newReservationsLinks' => [
        'node' => [
          'reservation' => [
            1 => [
              'new_reservation_count' => 1,
              'first_new_reservation_link' => Url::fromRoute('entity.node.canonical', ['node' => 1])->setOptions([
                'fragment' => 'new',
              ])->toString(),
            ],
          ],
        ],
      ],
    ], $settings['reservation']);
    // Pretend the data was not present in drupalSettings, i.e. test the
    // separate request to the server.
    $response = $this->renderNewReservationsNodeLinks([$this->node->id()]);
    $this->assertSame(200, $response->getStatusCode());
    $json = Json::decode($response->getBody());
    $expected = [
      $this->node->id() => [
        'new_reservation_count' => 1,
        'first_new_reservation_link' => $this->node->toUrl('canonical', ['fragment' => 'new'])->toString(),
      ],
    ];
    $this->assertSame($expected, $json);

    // Failing to specify node IDs for the endpoint should return a 404.
    $response = $this->renderNewReservationsNodeLinks([]);
    $this->assertSame(404, $response->getStatusCode());

    // Accessing the endpoint as the anonymous user should return a 403.
    $this->drupalLogout();
    $response = $this->renderNewReservationsNodeLinks([$this->node->id()]);
    $this->assertSame(403, $response->getStatusCode());
    $response = $this->renderNewReservationsNodeLinks([]);
    $this->assertSame(403, $response->getStatusCode());
  }

}
