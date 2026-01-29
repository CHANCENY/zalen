<?php

namespace Drupal\reservation_test\Controller;

use Drupal\reservation\ReservationInterface;
use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for the reservation_test.module.
 */
class ReservationTestController extends ControllerBase {

  /**
   * Provides a reservation report.
   */
  public function reservationReport(ReservationInterface $reservation) {
    return ['#markup' => $this->t('Report for a reservation')];
  }

}
