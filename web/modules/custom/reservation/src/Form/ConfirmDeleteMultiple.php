<?php

namespace Drupal\reservation\Form;

use Drupal\Core\Entity\Form\DeleteMultipleForm as EntityDeleteMultipleForm;
use Drupal\Core\Url;

/**
 * Provides the reservation multiple delete confirmation form.
 *
 * @internal
 */
class ConfirmDeleteMultiple extends EntityDeleteMultipleForm {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->formatPlural(count($this->selection), 'Are you sure you want to delete this reservation and all its children?', 'Are you sure you want to delete these reservations and all their children?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('reservation.admin');
  }

  /**
   * {@inheritdoc}
   */
  protected function getDeletedMessage($count) {
    return $this->formatPlural($count, 'Deleted @count reservation.', 'Deleted @count reservations.');
  }

  /**
   * {@inheritdoc}
   */
  protected function getInaccessibleMessage($count) {
    return $this->formatPlural($count, "@count reservation has not been deleted because you do not have the necessary permissions.", "@count reservations have not been deleted because you do not have the necessary permissions.");
  }

}
