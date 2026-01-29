<?php

namespace Drupal\reservation;

use Drupal\Core\Entity\EntityInterface;

/**
 * Reservation manager contains common functions to manage reservation fields.
 */
interface ReservationManagerInterface {

  /**
   * Reservations are displayed in a flat list - expanded.
   */
  const RESERVATION_MODE_FLAT = 0;

  /**
   * Reservations are displayed as a threaded list - expanded.
   */
  const RESERVATION_MODE_THREADED = 1;

  /**
   * Utility function to return an array of reservation fields.
   *
   * @param string $entity_type_id
   *   The content entity type to which the reservation fields are attached.
   *
   * @return array
   *   An array of reservation field map definitions, keyed by field name. Each
   *   value is an array with two entries:
   *   - type: The field type.
   *   - bundles: The bundles in which the field appears, as an array with entity
   *     types as keys and the array of bundle names as values.
   */
  public function getFields($entity_type_id);

  /**
   * Creates a reservation_body field.
   *
   * @param string $reservation_type
   *   The reservation bundle.
   */
  public function addBodyField($reservation_type);

  /**
   * Provides a message if posting reservations is forbidden.
   *
   * If authenticated users can post reservations, a message is returned that
   * prompts the anonymous user to log in (or register, if applicable) that
   * redirects to entity reservation form. Otherwise, no message is returned.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to which reservations are attached to.
   * @param string $field_name
   *   The field name on the entity to which reservations are attached to.
   *
   * @return string
   *   HTML for a "you can't post reservations" notice.
   */
  public function forbiddenMessage(EntityInterface $entity, $field_name);

  /**
   * Returns the number of new reservations available on a given entity for a user.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to which the reservations are attached to.
   * @param string $field_name
   *   (optional) The field_name to count reservations for. Defaults to any field.
   * @param int $timestamp
   *   (optional) Time to count from. Defaults to time of last user access the
   *   entity.
   *
   * @return int|false
   *   The number of new reservations or FALSE if the user is not authenticated.
   */
  public function getCountNewReservations(EntityInterface $entity, $field_name = NULL, $timestamp = 0);

}
