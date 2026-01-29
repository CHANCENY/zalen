<?php

namespace Drupal\reservation;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Entity\FieldableEntityInterface;

/**
 * Defines an interface for reservation entity storage classes.
 */
interface ReservationStorageInterface extends ContentEntityStorageInterface {

  /**
   * Gets the maximum encoded thread value for the top level reservations.
   *
   * @param \Drupal\reservation\ReservationInterface $reservation
   *   A reservation entity.
   *
   * @return string
   *   The maximum encoded thread value among the top level reservations of the
   *   node $reservation belongs to.
   */
  public function getMaxThread(ReservationInterface $reservation);

  /**
   * Gets the maximum encoded thread value for the children of this reservation.
   *
   * @param \Drupal\reservation\ReservationInterface $reservation
   *   A reservation entity.
   *
   * @return string
   *   The maximum encoded thread value among all replies of $reservation.
   */
  public function getMaxThreadPerThread(ReservationInterface $reservation);

  /**
   * Calculates the page number for the first new reservation.
   *
   * @param int $total_reservations
   *   The total number of reservations that the entity has.
   * @param int $new_reservations
   *   The number of new reservations that the entity has.
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity to which the reservations belong.
   * @param string $field_name
   *   The field name on the entity to which reservations are attached.
   *
   * @return array|null
   *   The page number where first new reservation appears. (First page returns 0.)
   */
  public function getNewReservationPageNumber($total_reservations, $new_reservations, FieldableEntityInterface $entity, $field_name);

  /**
   * Gets the display ordinal or page number for a reservation.
   *
   * @param \Drupal\reservation\ReservationInterface $reservation
   *   The reservation to use as a reference point.
   * @param int $reservation_mode
   *   The reservation display mode: ReservationManagerInterface::RESERVATION_MODE_FLAT or
   *   ReservationManagerInterface::RESERVATION_MODE_THREADED.
   * @param int $divisor
   *   Defaults to 1, which returns the display ordinal for a reservation. If the
   *   number of reservations per page is provided, the returned value will be the
   *   page number. (The return value will be divided by $divisor.)
   *
   * @return int
   *   The display ordinal or page number for the reservation. It is 0-based, so
   *   will represent the number of items before the given reservation/page.
   */
  public function getDisplayOrdinal(ReservationInterface $reservation, $reservation_mode, $divisor = 1);

  /**
   * Gets the reservation ids of the passed reservation entities' children.
   *
   * @param \Drupal\reservation\ReservationInterface[] $reservations
   *   An array of reservation entities keyed by their ids.
   *
   * @return array
   *   The entity ids of the passed reservation entities' children as an array.
   */
  public function getChildCids(array $reservations);

  /**
   * Retrieves reservations for a thread, sorted in an order suitable for display.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity whose reservation(s) needs rendering.
   * @param string $field_name
   *   The field_name whose reservation(s) needs rendering.
   * @param int $mode
   *   The reservation display mode: ReservationManagerInterface::RESERVATION_MODE_FLAT or
   *   ReservationManagerInterface::RESERVATION_MODE_THREADED.
   * @param int $reservations_per_page
   *   (optional) The amount of reservations to display per page.
   *   Defaults to 0, which means show all reservations.
   * @param int $pager_id
   *   (optional) Pager id to use in case of multiple pagers on the one page.
   *   Defaults to 0; is only used when $reservations_per_page is greater than zero.
   *
   * @return array
   *   Ordered array of reservation objects, keyed by reservation id.
   */
  public function loadThread(EntityInterface $entity, $field_name, $mode, $reservations_per_page = 0, $pager_id = 0);

  /**
   * Returns the number of unapproved reservations.
   *
   * @return int
   *   The number of unapproved reservations.
   */
  public function getUnapprovedCount();

}
