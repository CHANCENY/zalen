<?php

namespace Drupal\room_invoice\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * @Constraint(
 *   id = "UniqueInvoiceAttendee",
 *   label = @Translation("Unique invoice attendees"),
 * )
 */
class UniqueInvoiceAttendeesConstraint extends Constraint {

  /** @var string */
  public $message = 'The %name is already attending this case.';

}
