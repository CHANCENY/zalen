<?php

namespace Drupal\reservation\Plugin\views\field;

use Drupal\views\Plugin\views\field\BulkForm;

/**
 * Defines a reservation operations bulk form element.
 *
 * @ViewsField("reservation_bulk_form")
 */
class ReservationBulkForm extends BulkForm {

  /**
   * {@inheritdoc}
   */
  protected function emptySelectedMessage() {
    return $this->t('Select one or more reservations to perform the update on.');
  }

}
