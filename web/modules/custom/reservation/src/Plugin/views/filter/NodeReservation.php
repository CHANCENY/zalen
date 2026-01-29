<?php

namespace Drupal\reservation\Plugin\views\filter;

use Drupal\reservation\Plugin\Field\FieldType\ReservationItemInterface;
use Drupal\views\Plugin\views\filter\InOperator;

/**
 * Filter based on reservation node status.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("node_reservation")
 */
class NodeReservation extends InOperator {

  public function getValueOptions() {
    $this->valueOptions = [
      ReservationItemInterface::HIDDEN => $this->t('Hidden'),
      ReservationItemInterface::CLOSED => $this->t('Closed'),
      ReservationItemInterface::OPEN => $this->t('Open'),
    ];
    return $this->valueOptions;
  }

}
