<?php

namespace Drupal\reservation\Plugin;

use Drupal;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;

/**
 * @class BookingConfirmationEmail will be handling mails of bookings.
 */
class BookingConfirmationEmail {

  /**
   * @param \Drupal\Core\Entity\EntityInterface $entity
   */
  public function __construct(private readonly \Drupal\Core\Entity\EntityInterface $entity) {}

  /**
   * Process the mails required to send.
   * @return void
   */
  public function processRequiredMail(): void {

    $room_id = $this->entity->get('entity_id')->getValue()[0]['target_id'] ?? null;
    $node = Node::load($room_id);
    $reservation_owner = $this->entity->get('uid')?->getValue()[0]['target_id'] ?? null;
    if ($node && $node->bundle() === 'zaal' && $reservation_owner = User::load($reservation_owner)) {

      // Let's check if a room is available.
      $room_available = $node->get('field_status')->getValue();

      if(!empty($room_available) && $room_available[0]['value'] !== 'Available') {
        return;
      }

      $location_notification = \Drupal::configFactory()->get('reservation.notify_email_of_booking_event')->get('template_mail');
      $not_instance_booking = \Drupal::configFactory()->get('reservation.not_instance_booking_done_email')->get('template_mail');
      $instance_booking = \Drupal::configFactory()->get('reservation.instance_booking_done_email')->get('template_mail');
      $payment_instruction = \Drupal::configFactory()->get('reservation.payment_instruction_email')->get('template_mail');
      $payment_inst_sent = \Drupal::configFactory()->get('reservation.notify_email_payment_instructions_sent')->get('template_mail');
      $token_service = \Drupal::token();

      /**@var User $room_owner**/
      $room_owner = $node->getOwner();

      // Replacing tokens in email templates.
      $location_notification = $token_service->replace($location_notification,
        [
          'user'=>  $reservation_owner,
          'reservation'=> $this->entity,
          'node' => $node,
        ]
      );
      $not_instance_booking = $token_service->replace($not_instance_booking,
        [
          'user'=>  $reservation_owner,
          'reservation'=> $this->entity,
          'node' => $node,
        ]
      );
      $instance_booking = $token_service->replace($instance_booking,
        [
          'user'=>  $reservation_owner,
          'reservation'=> $this->entity,
          'node' => $node,
        ]
      );
      $payment_instruction = $token_service->replace($payment_instruction,
        [
          'user'=>  $reservation_owner,
          'reservation'=> $this->entity,
          'node' => $node,
        ]
      );
      $payment_inst_sent = $token_service->replace($payment_inst_sent,
        [
          'user'=>  $reservation_owner,
          'reservation'=> $this->entity,
          'node' => $node,
        ]
      );

      $confirmation_status = (int) $node->get('field_confirmatie')->getValue()[0]['value'] ?? null;

      if($instance_booking && $not_instance_booking && $location_notification) {
        $mailManager = \Drupal::service('plugin.manager.mail');
        $module = 'zaal_condities';
        $key = 'reservation_mails';
        $langcode = \Drupal::currentUser()->getPreferredLangcode();

        // Sending notification email to location of booking request.
        $to = $room_owner->getEmail();
        $params['body'] = Drupal\Core\Render\Markup::create($location_notification);
        $params['subject'] = "Notification: Booking To Confirm";
        $mailManager->mail($module, $key, $to, $langcode, $params, NULL, TRUE);

        // Instant confirmation protocol.
        if($confirmation_status === 0) {
          $to =  $reservation_owner->getEmail();
          $params['body'] = Drupal\Core\Render\Markup::create($instance_booking);
          $params['subject'] = "Booking Confirmation";
          $result = $mailManager->mail($module, $key, $to, $langcode, $params, NULL, TRUE);
          if ($result['result'] !== true) {
            \Drupal::messenger()->addError(t('There was a problem sending your email.'));
          }
          else {

            //Sending payment instruction here.
            $to =  $reservation_owner->getEmail();
            $params['body'] = Drupal\Core\Render\Markup::create($payment_instruction);
            $params['subject'] = "Booking Payment Instruction";
            $result = $mailManager->mail($module, $key, $to, $langcode, $params, NULL, TRUE);
            if($result['result']) {

              // Sending to location of instruction sent status.
              $to = $room_owner->getEmail();
              $params['body'] = Drupal\Core\Render\Markup::create($payment_inst_sent);
              $params['subject'] = "Booking Payment Instruction";
              $mailManager->mail($module, $key, $to, $langcode, $params, NULL, TRUE);
            }
          }
        }

        // Not instance confirmation protocol.
        else {

          // Sending an email to booker of there booking info.
          $to = $reservation_owner->getEmail();
          $params['body'] = Drupal\Core\Render\Markup::create($not_instance_booking);
          $params['subject'] = "Booking Information";
          $result = $mailManager->mail($module, $key, $to, $langcode, $params, NULL, TRUE);
          if ($result['result'] !== true) {
            \Drupal::messenger()->addError(t('There was a problem sending your email notification.'));
          }
        }
      }

    }
  }

  /**
   * Sending emails on approval of reservation.
   * @return void
   */
  public function approvedEmails(): void {

    $room_id = $this->entity->get('entity_id')->getValue()[0]['target_id'] ?? null;
    $node = Node::load($room_id);
    $reservation_owner = $this->entity->get('uid')?->getValue()[0]['target_id'] ?? null;
    if ($node?->bundle() === 'zaal' && $reservation_owner = User::load($reservation_owner)) {

      // Let's check if a room is available.
      $room_available = $node->get('field_status')->getValue();

      if(!empty($room_available) && $room_available[0]['value'] !== 'Available') {
        return;
      }

      // Loading templates needed form emails.
      $instance_booking = \Drupal::configFactory()->get('reservation.instance_booking_done_email')->get('template_mail');
      $payment_instruction = \Drupal::configFactory()->get('reservation.payment_instruction_email')->get('template_mail');
      $payment_inst_sent = \Drupal::configFactory()->get('reservation.notify_email_payment_instructions_sent')->get('template_mail');
      $token_service = \Drupal::token();

      /**@var User $room_owner**/
      $room_owner = $node->getOwner();

      // Replacing tokens in email templates.
      $instance_booking = $token_service->replace($instance_booking,
        [
          'user'=>  $reservation_owner,
          'reservation'=> $this->entity,
          'node' => $node,
        ]
      );
      $payment_instruction = $token_service->replace($payment_instruction,
        [
          'user'=>  $reservation_owner,
          'reservation'=> $this->entity,
          'node' => $node,
        ]
      );
      $payment_inst_sent = $token_service->replace($payment_inst_sent,
        [
          'user'=>  $reservation_owner,
          'reservation'=> $this->entity,
          'node' => $node,
        ]
      );

      if($instance_booking && $payment_instruction && $payment_inst_sent) {

        $mailManager = \Drupal::service('plugin.manager.mail');
        $module = 'zaal_condities';
        $key = 'reservation_mails';
        $langcode = \Drupal::currentUser()->getPreferredLangcode();

        // Sending approved email confirmation protocol. To reservation booker.
        $to = $reservation_owner->getEmail();
        if($to) {
          $params['body'] = Drupal\Core\Render\Markup::create($instance_booking);
          $params['subject'] = "Booking Confirmation";
          $result = $mailManager->mail($module, $key, $to, $langcode, $params, NULL, TRUE);
          if ($result['result'] !== true) {
            \Drupal::messenger()->addError(t('There was a problem sending your approval email.'));
          }
          else {

            //Sending payment instruction here. To booker.
            $params['body'] = Drupal\Core\Render\Markup::create($payment_instruction);
            $params['subject'] = "Reservation Payment Instruction";
            $result = $mailManager->mail($module, $key, $to, $langcode, $params, NULL, TRUE);
            if ($result['result']) {
              // Sending to location of instruction sent status.
              $to = $room_owner->getEmail();
              $params['body'] = Drupal\Core\Render\Markup::create($payment_inst_sent);
              $params['subject'] = "Reservation Payment Instruction Sent";
              $mailManager->mail($module, $key, $to, $langcode, $params, NULL, TRUE);
            }
          }
        }
      }

    }
  }

}
