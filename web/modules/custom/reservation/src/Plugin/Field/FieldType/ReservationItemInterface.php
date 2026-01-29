<?php

namespace Drupal\reservation\Plugin\Field\FieldType;

/**
 * Interface definition for Reservation items.
 */
interface ReservationItemInterface {

  /**
   * Reservations for this entity are hidden.
   */
  const HIDDEN = 0;

  /**
   * Reservations for this entity are closed.
   */
  const CLOSED = 1;

  /**
   * Reservations for this entity are open.
   */
  const OPEN = 2;

  /**
   * Reservation form should be displayed on a separate page.
   */
  const FORM_SEPARATE_PAGE = 0;

  /**
   * Reservation form should be shown below post or list of reservations.
   */
  const FORM_BELOW = 1;

}
