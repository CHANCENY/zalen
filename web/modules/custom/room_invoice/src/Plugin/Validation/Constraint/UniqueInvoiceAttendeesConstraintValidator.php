<?php

namespace Drupal\room_invoice\Plugin\Validation\Constraint;

use Drupal\reservation\Entity\Reservation;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class UniqueInvoiceAttendeesConstraintValidator extends ConstraintValidator {

  public function validate($value, Constraint $constraint) {

    /** @var \Drupal\Core\Entity\Plugin\DataType\EntityAdapter $adapter */
    $adapter = $value->getParent();
    /** @var \Drupal\room_invoice\Entity\invoice_payment $invoice */
    $invoice = $adapter->getEntity();
    $type_order = $invoice->getTarget();
    if ($type_order == 'reservation') {
      $target = $invoice->getAttendees();
      $query = \Drupal::entityQuery('invoice_payment');
      $query->condition('attendees', $target);
      $nid = $query->execute();
      if ($nid) {
        $this->context->buildViolation($constraint->message)->setParameter('%name', Reservation::load($target)->getSubject())->addViolation();
        return;
      };
    };

  }

}

