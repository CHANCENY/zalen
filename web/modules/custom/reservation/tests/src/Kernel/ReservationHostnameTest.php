<?php

namespace Drupal\Tests\reservation\Kernel;

use Drupal\reservation\Entity\Reservation;
use Drupal\reservation\Entity\ReservationType;
use Drupal\KernelTests\KernelTestBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the hostname base field.
 *
 * @coversDefaultClass \Drupal\reservation\Entity\Reservation
 *
 * @group reservation
 */
class ReservationHostnameTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['reservation', 'entity_test', 'user'];

  /**
   * Tests hostname default value callback.
   *
   * @covers ::getDefaultHostname
   */
  public function testGetDefaultHostname() {
    // Create a fake request to be used for testing.
    $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '203.0.113.1']);
    /** @var \Symfony\Component\HttpFoundation\RequestStack $stack */
    $stack = $this->container->get('request_stack');
    $stack->push($request);

    ReservationType::create([
      'id' => 'foo',
      'target_entity_type_id' => 'entity_test',
    ])->save();

    // Check that the hostname is empty by default.
    $reservation = Reservation::create(['reservation_type' => 'foo']);
    $this->assertEquals('', $reservation->getHostname());

    \Drupal::configFactory()
      ->getEditable('reservation.settings')
      ->set('log_ip_addresses', TRUE)
      ->save(TRUE);
    // Check that the hostname was set correctly.
    $reservation = Reservation::create(['reservation_type' => 'foo']);
    $this->assertEquals('203.0.113.1', $reservation->getHostname());
  }

}
