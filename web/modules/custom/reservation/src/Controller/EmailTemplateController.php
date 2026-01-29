<?php

namespace Drupal\reservation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\reservation\Form\LocationNotificationEmailForm;
use Drupal\reservation\Form\LocationPaymentWasReceivedFrom;
use Drupal\reservation\Form\LocationPaymentInstructionWasSentForm;
use Drupal\reservation\Form\MagnusPaidEmailForm;
use Drupal\reservation\Form\OneTimeSubscriptionEmail;
use Drupal\reservation\Form\OrganizerInstanceConfirmation;
use Drupal\reservation\Form\OrganizerNotInstanceConfirmation;
use Drupal\reservation\Form\OrganizerPaidViaMollie;
use Drupal\reservation\Form\OrganizerPaymentInstruction;
use Drupal\reservation\Form\OrganizerReservationCanceled;
use Drupal\reservation\Form\PaymentNotReceived;
use Drupal\reservation\Form\SubscriptionEmail;

/**
 * @class EmailTemplateController to lay out forms for building email templates
 */
class EmailTemplateController extends ControllerBase {

  /**
   * Laying out forms for builder email templates.
   * @return array
   */
  public function emailFormTemplateLayout(): array {
    return array(
      'form_1' => $this->formBuilder()->getForm(LocationNotificationEmailForm::class),
      'form_2' => $this->formBuilder()->getForm(LocationPaymentInstructionWasSentForm::class),
      'form_3' => $this->formBuilder()->getForm(LocationPaymentWasReceivedFrom::class),
      'form_4' => $this->formBuilder()->getForm(OrganizerInstanceConfirmation::class),
      'form_5' => $this->formBuilder()->getForm(OrganizerNotInstanceConfirmation::class),
      'form_6' => $this->formBuilder()->getForm(OrganizerPaymentInstruction::class),
      'form_7' => $this->formBuilder()->getForm(OrganizerPaidViaMollie::class),
      'form_8' => $this->formBuilder()->getForm(OrganizerReservationCanceled::class),
      'form_9' => $this->formBuilder()->getForm(PaymentNotReceived::class),
      'form_10' => $this->formBuilder()->getForm(OneTimeSubscriptionEmail::class),
      'form_11' => $this->formBuilder()->getForm(SubscriptionEmail::class),
      'form_12' => $this->formBuilder()->getForm(MagnusPaidEmailForm::class),
      'tokens' =>  \Drupal::service('token.tree_builder')->buildRenderable(['node', 'user','reservation','payment_invoices']),
    );
  }
}

