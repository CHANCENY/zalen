<?php

namespace Drupal\reservation;

use Drupal\Core\Entity\FieldableEntityInterface;

/**
 * Defines an interface for building reservation links on a reservationed entity.
 *
 * Reservation links include 'log in to post new reservation', 'add new reservation' etc.
 */
interface ReservationLinkBuilderInterface {

  /**
   * Builds links for the given entity.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   Entity for which the links are being built.
   * @param array $context
   *   Array of context passed from the entity view builder.
   *
   * @return array
   *   Array of entity links.
   */
  public function buildReservationedEntityLinks(FieldableEntityInterface $entity, array &$context);

}
