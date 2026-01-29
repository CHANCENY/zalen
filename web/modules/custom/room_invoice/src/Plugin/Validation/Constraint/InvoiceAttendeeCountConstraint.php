<?php

namespace Drupal\room_invoice\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * @Constraint(
 *   id = "InvoiceAttendeeCount",
 *   label = @Translation("Invoice attendee count"),
 * )
 */
class InvoiceAttendeeCountConstraint extends Constraint {

  /** @var string */
  public $message = 'The invoice %title only allows existing entity %maximum - no such entity.';
  /** @var string */
  public $message_type = 'The invoice %title cannot be created because unknown object type for order target, %typetarget - unknown entity.';
  /** @var string */
  public $message_target = 'The invoice %title cannot be created with undefined item %targetitem target entity in order .';

}
