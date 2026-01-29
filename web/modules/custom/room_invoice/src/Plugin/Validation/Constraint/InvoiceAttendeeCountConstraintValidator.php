<?php

namespace Drupal\room_invoice\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class InvoiceAttendeeCountConstraintValidator extends ConstraintValidator {

  public function validate($value, Constraint $constraint) {

    /** @var \Drupal\Core\Entity\Plugin\DataType\EntityAdapter $adapter */
    $adapter = $value->getParent();
    /** @var \Drupal\room_invoice\Entity\invoice_payment $invoice */
    $invoice = $adapter->getEntity();
    $type_order = $invoice->getTarget();
    switch ($type_order) {
      case 'reservation':
        $identifier_order = 'cid';
        break;
      case 'user':
        $identifier_order = 'uid';
        break;
      case 'comment':
        $identifier_order = 'cid';
        break;
      case 'node':
        $identifier_order = 'nid';
        break;
      default:
        $this->context->buildViolation($constraint->message_type)->setParameter('%title', $invoice->getTitle())->setParameter('%typetarget', $type_order)->addViolation();
        return;
    };
    $target = $invoice->getAttendees();
    if (!isset($target)) {
      $this->context->buildViolation($constraint->message_target)->setParameter('%title', $invoice->getTitle())->setParameter('%targetitem', $target)->addViolation();
    };
    $items = \Drupal::entityTypeManager()->getStorage($type_order)->loadByProperties([$identifier_order => $target]);
    if (!$items) {
      $this->context->buildViolation($constraint->message)->setParameter('%title', $invoice->getTitle())->setParameter('%maximum', $target)->addViolation();
    }


  }

}
