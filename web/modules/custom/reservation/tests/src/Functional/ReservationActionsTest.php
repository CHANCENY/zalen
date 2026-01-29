<?php

namespace Drupal\Tests\reservation\Functional;

use Drupal\reservation\Entity\Reservation;
use Drupal\system\Entity\Action;

/**
 * Tests actions provided by the Reservation module.
 *
 * @group reservation
 */
class ReservationActionsTest extends ReservationTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = ['dblog', 'action'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests reservation publish and unpublish actions.
   */
  public function testReservationPublishUnpublishActions() {
    $this->drupalLogin($this->webUser);
    $reservation_text = $this->randomMachineName();
    $subject = $this->randomMachineName();
    $reservation = $this->postReservation($this->node, $reservation_text, $subject);

    // Unpublish a reservation.
    $action = Action::load('reservation_unpublish_action');
    $action->execute([$reservation]);
    $this->assertTrue($reservation->isPublished() === FALSE, 'Reservation was unpublished');
    $this->assertSame(['module' => ['reservation']], $action->getDependencies());
    // Publish a reservation.
    $action = Action::load('reservation_publish_action');
    $action->execute([$reservation]);
    $this->assertTrue($reservation->isPublished() === TRUE, 'Reservation was published');
  }

  /**
   * Tests the unpublish reservation by keyword action.
   */
  public function testReservationUnpublishByKeyword() {
    $this->drupalLogin($this->adminUser);
    $keyword_1 = $this->randomMachineName();
    $keyword_2 = $this->randomMachineName();
    $action = Action::create([
      'id' => 'reservation_unpublish_by_keyword_action',
      'label' => $this->randomMachineName(),
      'type' => 'reservation',
      'configuration' => [
        'keywords' => [$keyword_1, $keyword_2],
      ],
      'plugin' => 'reservation_unpublish_by_keyword_action',
    ]);
    $action->save();

    $reservation = $this->postReservation($this->node, $keyword_2, $this->randomMachineName());

    // Load the full reservation so that status is available.
    $reservation = Reservation::load($reservation->id());

    $this->assertTrue($reservation->isPublished() === TRUE, 'The reservation status was set to published.');

    $action->execute([$reservation]);
    $this->assertTrue($reservation->isPublished() === FALSE, 'The reservation status was set to not published.');
  }

}
