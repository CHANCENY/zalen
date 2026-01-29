<?php

namespace Drupal\reservation\Plugin\Validation\Constraint;

use Drupal\Core\Entity\Plugin\Validation\Constraint\CompositeConstraintBase;

/**
 * Supports validating reservation author names.
 *
 * @Constraint(
 *   id = "ReservationName",
 *   label = @Translation("Reservation author name", context = "Validation"),
 *   type = "entity:reservation"
 * )
 */
class ReservationNameConstraint extends CompositeConstraintBase {

  /**
   * Message shown when an anonymous user reservations using a registered name.
   *
   * @var string
   */
  public $messageNameTaken = 'The name you used (%name) belongs to a registered user.';

  /**
   * Message shown when an admin changes the reservation-author to an invalid user.
   *
   * @var string
   */
  public $messageRequired = 'You have to specify a valid author.';

  /**
   * Message shown when the name doesn't match the author's name.
   *
   * @var string
   */
  public $messageMatch = 'The specified author name does not match the reservation author.';

  /**
   * {@inheritdoc}
   */
  public function coversFields() {
    return ['name', 'uid'];
  }

}
