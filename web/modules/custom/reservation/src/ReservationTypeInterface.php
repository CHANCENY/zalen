<?php

namespace Drupal\reservation;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a reservation type entity.
 */
interface ReservationTypeInterface extends ConfigEntityInterface {

  /**
   * Returns the reservation type description.
   *
   * @return string
   *   The reservation-type description.
   */
  public function getDescription();

  /**
   * Sets the description of the reservation type.
   *
   * @param string $description
   *   The new description.
   *
   * @return $this
   */
  public function setDescription($description);

  /**
   * Gets the target entity type id for this reservation type.
   *
   * @return string
   *   The target entity type id.
   */
  public function getTargetEntityTypeId();

}
