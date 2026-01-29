<?php

namespace Drupal\reservation\Plugin\views\field;

use Drupal\Core\Url;
use Drupal\views\Plugin\views\field\LinkBase;
use Drupal\views\ResultRow;

/**
 * Field handler to present a link to reply to a reservation.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("reservation_link_reply")
 */
class LinkReply extends LinkBase {

  /**
   * {@inheritdoc}
   */
  protected function getUrlInfo(ResultRow $row) {
    /** @var \Drupal\reservation\ReservationInterface $reservation */
    $reservation = $this->getEntity($row);
    return Url::fromRoute('reservation.reply', [
      'entity_type' => $reservation->getReservationedEntityTypeId(),
      'entity' => $reservation->getReservationedEntityId(),
      'field_name' => $reservation->getFieldName(),
      'pid' => $reservation->id(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultLabel() {
    return $this->t('Reply');
  }

}
