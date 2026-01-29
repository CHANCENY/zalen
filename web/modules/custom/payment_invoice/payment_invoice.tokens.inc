<?php

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;
use Drupal\payment_invoice\Plugin\Invoicing;
use Drupal\payment_provider\Plugin\Pricing\Pricing;
use Drupal\reservation\Entity\Reservation;
use Drupal\user\Entity\User;
use Mollie\Api\Resources\Payment;

/**
 * Implements hook_token_info().
 */
function payment_invoice_token_info(): array {
  $type = [
    'name' => t('Payment Invoice'),
    'description' => t('Tokens for payment invoices.'),
    'needs-data' => 'payment_invoices',
  ];

  $payment_invoices = [
    'payment_client_logo' => [
      'name' => t("Client Logo"),
      'description' => t("Logo of the client."),
    ],
    'payment_client_name' => [
      'name' => t("Client Name"),
      'description' => t("Name of the client."),
    ],
    'payment_client_email' => [
      'name' => t("Client Email"),
      'description' => t("Email of the client."),
    ],
    'payment_client_phone' => [
      'name' => t("Client Phone"),
      'description' => t("Phone number of the client."),
    ],
    'payment_site_phone' => [
      'name' => t("Site Phone"),
      'description' => t("Phone number of the site."),
    ],
    'payment_site_email' => [
      'name' => t("Site Email"),
      'description' => t("Email of the site."),
    ],
    'payment_site_name' => [
      'name' => t("Site Name"),
      'description' => t("Name of the site."),
    ],
    'payment_invoice_id' => [
      'name' => t("Invoice ID"),
      'description' => t("The unique ID of the invoice."),
    ],
    'payment_invoice_date' => [
      'name' => t("Invoice Date"),
      'description' => t("Date of the invoice."),
    ],
    'payment_payment_id' => [
      'name' => t("Payment ID"),
      'description' => t("ID of the payment."),
    ],
    'payment_payment_title' => [
      'name' => t("Payment Title"),
      'description' => t("Title of the payment."),
    ],
    'payment_reservation_subject' => [
      'name' => t("Reservation Subject"),
      'description' => t("Subject of the reservation."),
    ],
    'payment_booking_amount' => [
      'name' => t("Booking Amount"),
      'description' => t("Amount for the booking."),
    ],
    'payment_platform_fees' => [
      'name' => t("Platform Fees"),
      'description' => t("Fees for the platform."),
    ],
    'payment_sub_total' => [
      'name' => t("Sub Total"),
      'description' => t("Sub total amount."),
    ],
    'payment_currency' => [
      'name' => t("Currency"),
      'description' => t("Currency of the payment."),
    ],
    'payment_total_amount' => [
      'name' => t("Total Amount"),
      'description' => t("Total amount to be paid."),
    ],
    'payment_booked_date' => [
      'name' => t("Booked Date"),
      'description' => t("Date when the reservation was booked."),
    ],
    'payment_room_price' => [
      'name' => t("Room Price"),
      'description' => t("Price of the room."),
    ],
    'payment_price_per_hour' => [
      'name' => t("Price Per Hour"),
      'description' => t("Price per hour."),
    ],
    'payment_price_per_day' => [
      'name' => t("Price Per Day"),
      'description' => t("Price per day."),
    ],
    'payment_attendees' => [
      'name' => t("Attendees"),
      'description' => t("Number of attendees."),
    ],
    'payment_hours_booked' => [
      'name' => t("Hours Booked"),
      'description' => t("Number of hours booked."),
    ],
    'payment_payment_type' => [
      'name' => t("Payment Type"),
      'description' => t("Type of payment."),
    ],
    'payment_payment_status' => [
      'name' => t("Payment Status"),
      'description' => t("Status of the payment."),
    ],
    'payment_paid_amount' => [
      'name' => t("Paid Amount"),
      'description' => t("Amount that has been paid."),
    ],
    'payment_charged_on' => [
      'name' => t("Charged On"),
      'description' => t("Date when the payment was charged."),
    ],
    'payment_balance' => [
      'name' => t("Balance"),
      'description' => t("Remaining balance."),
    ],
    'payment_description_remaining' => [
      'name' => t("Description Remaining"),
      'description' => t("Description of the remaining balance."),
    ],
    'reservation_per_person_options' => [
      'name' => t("Reservation Per Person Options"),
      'description' => t("Table of all options listed on per person"),
    ],
    'payment_additional_services' => [
      'name' => t("Additional Services"),
      'description' => t("Additional services provided."),
    ],
    'platform_fees_vat_calculated_table' => [
      'name' => t("Platform Fees VAT Calculated Table"),
      'description' => t("Table of all platform fees calculated with VAT."),
    ],
    'location_amount_calculate_vat_table' => [
      'name' => t("Location Amount Calculate VAT Table"),
      'description' => t("Table of all location amounts calculated with VAT."),
    ],
  ];

  return [
    'types' => ['payment_invoices' => $type],
    'tokens' => [
      'payment_invoices' => $payment_invoices,
    ],
  ];
}

/**
 * Implements hook_tokens().
 */
function payment_invoice_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata): array {
  $replacements = [];

  if ($type === 'payment_invoices') {

    /**@var $reservation Reservation|null **/
    $reservation = $data['reservation'] ?? null;

    /**@var $reservation_owner User **/
    $reservation_owner = $reservation?->getOwner();

    /**@var $node_room Node **/
    $node_room = $data['node'] ?? null;

    /**@var $pricing Pricing|null **/
    $pricing = $data['pricing'] ?? null;

    /**@var $payment Mollie\Api\Resources\Payment|null **/
    $payment = $data['payment'] ?? null;

    /**@var $invoice Invoicing|null **/
    $invoice = $data['invoice'] ?? null;

    /**@var $user User|null **/
    $user = $data['user'] ?? null;

    if($invoice instanceof Invoicing &&
      $pricing instanceof Pricing &&
      $payment instanceof Payment &&
      $node_room instanceof Node &&
      $reservation_owner instanceof User &&
      $reservation instanceof Reservation) {

      $reservationInformation = $reservation->get('field_reservation_information')->referencedEntities();
      $reservationInformation = !empty($reservationInformation) ? reset($reservationInformation) : null;
      $currency = Drupal::service('reservation.currencies');
      $actual_amount = $payment->amount->value;
      $routed_amount = !empty($payment->routing[0]->amount->value) ? $payment->routing[0]->amount->value : 0;

      $unit_price_used = 0;
      $days = 0;
      $hours = 0;
      $persons = 0;

      if ($reservationInformation?->get('field_unit_type')?->value == 'day') {
        $days = $reservationInformation?->get('field_days')?->value;
        $unit_price_used = $reservationInformation?->get('field_amount_used')?->value;
      }
      elseif ($reservationInformation?->get('field_unit_type')?->value == 'hour') {
        $hours = $reservationInformation?->get('field_hours')?->value;
        $unit_price_used = $reservationInformation?->get('field_amount_used')?->value;
      }
      elseif ($reservationInformation?->get('field_unit_type')?->value == 'person') {
        $persons = $reservationInformation?->get('field_occupants')?->value;
        $unit_price_used = $reservationInformation?->get('field_amount_used')?->value;
      }

      $additional_services_line = '';
      if ($reservationInformation?->get('field_services')?->getValue()) {
        $additional_services = $reservationInformation?->get('field_services')?->referencedEntities();
        foreach ($additional_services as $service) {
          $item = $service->get('field_service')?->referencedEntities();
          $item = !empty($item) ? reset($item) : null;
          $line = $service?->get('field_booking_count')?->value;
          $line = "<p>". $item?->get('field_service_short_description')?->value .' ('. $line. ")</p>";
          $additional_services_line .= $line . '<br>';
        }
      }

      $persons_options_line = '';
      if ($reservationInformation?->get('field_person_type_options')?->getValue()) {
        $persons_options = $reservationInformation?->get('field_person_type_options')?->referencedEntities();
        foreach ($persons_options as $option) {
          $item = $option->get('field_person_option')?->referencedEntities();
          $item = !empty($item) ? reset($item) : null;
          $line = $item?->get('field_short_description')?->value;
          $line .= ' ('. $option?->get('field_reserve_count')?->value. ')';
          $persons_options_line .= $line . '<br>';
        }
      }

      foreach ($tokens as $name => $original) {
        switch ($name) {
          case 'reservation_per_person_options':
            $replacements[$original] = $persons_options_line;
            break;
          case 'payment_client_logo':
            $replacements[$original] = ''; //$client_image;
            break;
          case 'payment_client_name':
            $replacements[$original] = $reservation_owner->getDisplayName();
            break;
          case 'payment_client_email':
            $replacements[$original] = $reservation_owner->getEmail();
            break;
          case 'payment_client_phone':
            // TODO: Implement replacement logic for payment_client_phone
            $replacements[$original] = '';
            break;
          case 'payment_site_phone':
            // TODO: Implement replacement logic for payment_site_phone
            $replacements[$original] = '';
            break;
          case 'payment_site_email':
            $replacements[$original] = ''; //$site_mail;
            break;
          case 'payment_site_name':
            $replacements[$original] = ''; //$site_name;
            break;
          case 'payment_invoice_id':
            $replacements[$original] = $payment->metadata->invoice;
            break;
          case 'payment_charged_on':
          case 'payment_invoice_date':
            $replacements[$original] = $payment->createdAt;
            break;
          case 'payment_payment_id':
            $replacements[$original] = $payment->id;
            break;
          case 'payment_payment_title':
            $replacements[$original] = $payment->description;
            break;
          case 'payment_reservation_subject':
            $replacements[$original] = $reservation->getSubject();
            break;
          case 'payment_sub_total':
          case 'payment_total_amount':
          case 'payment_booking_amount':
            $replacements[$original] = $reservationInformation?->get('field_total_amount')?->value;
            break;
          case 'payment_platform_fees':
            $replacements[$original] = $actual_amount - $routed_amount;
            break;
          case 'payment_currency':
            $replacements[$original] = $currency->getSymbol($payment->amount->currency);
            break;
          case 'payment_booked_date':
            $replacements[$original] = $reservationInformation?->get('field_starting_date')?->value . ' / '.
              $reservationInformation?->get('field_ending_date')?->value;
            break;
          case 'payment_room_price':
            $replacements[$original] = $actual_amount;
            break;
          case 'payment_price_per_day':
          case 'payment_price_per_hour':
            $replacements[$original] = $unit_price_used;
            break;
          case 'payment_attendees':
            $replacements[$original] = $persons;
            break;
          case 'payment_hours_booked':
            $replacements[$original] = $hours;
            break;
          case 'payment_payment_type':
            $replacements[$original] =  $payment->metadata->payment_type;
            break;
          case 'payment_payment_status':
            $replacements[$original] = $payment->status;
            break;
          case 'payment_paid_amount':
            $replacements[$original] = $pricing->formatCurrency($payment->getSettlementAmount());
            break;
          case 'payment_balance':
            $replacements[$original] = $reservationInformation?->get('field_total_amount')?->value - $payment->getSettlementAmount();
            break;
          case 'payment_description_remaining':
            $replacements[$original] = ''; //$remaining_desc;
            break;
          case 'payment_client_address':
            // TODO: Implement replacement logic for payment_client_address
            $replacements[$original] = '';
            break;
          case 'payment_client_city':
            // TODO: Implement replacement logic for payment_client_city
            $replacements[$original] = '';
            break;
          case 'payment_client_zipcode':
            // TODO: Implement replacement logic for payment_client_zipcode
            $replacements[$original] = '';
            break;
          case 'payment_site_city':
            // TODO: Implement replacement logic for payment_site_city
            $replacements[$original] = '';
            break;
          case 'payment_site_zipcode':
            // TODO: Implement replacement logic for payment_site_zipcode
            $replacements[$original] = '';
            break;
          case 'payment_additional_services':
            $replacements[$original] = $additional_services_line;
            break;
          case 'platform_fees_vat_calculated_table':
          case 'location_amount_calculate_vat_table':
            $replacements[$original] = process_vat_tokens($payment,$name);
            break;
        }
      }
    }
    else {

       if($payment instanceof Payment && $user instanceof User) {

         // Collecting site information.
         $config = \Drupal::service('config.factory')->get('system.site');
         $site_name = $config->get('name');
         $site_mail = $config->get('mail');

         foreach ($tokens as $name => $original) {
           switch ($name) {
             case 'payment_site_email':
               $replacements[$original] = $site_mail;
               break;
             case 'payment_site_name':
               $replacements[$original] = $site_name;
               break;
             case 'payment_client_name':
               $replacements[$original] = $user->getDisplayName();
               break;
             case 'payment_client_email':
               $replacements[$original] = $user->getEmail();
               break;
             case 'payment_invoice_id':
               $replacements[$original] = $payment->metadata->invoice;
               break;
             case 'payment_invoice_date':
               try{
                 $date = new DateTime($payment->createdAt);
                 $replacements[$original] = $date->format('d F Y H:i');
               }catch (\Throwable $exception) {
                 $replacements[$original] = $payment->createdAt;
               }
               break;
             case 'payment_payment_id':
               $replacements[$original] = $payment->id;
               break;
             case 'payment_payment_title':
               $replacements[$original] = $payment->description;
               break;
             case 'payment_booking_amount':
               $replacements[$original] = '';
               break;
             case 'payment_currency':
               $replacements[$original] = Drupal::service('reservation.currencies')?->getSymbol($payment->amount->currency);
               break;
             case 'payment_attendees':
               $replacements[$original] = 0;
               break;
             case 'payment_payment_type':
               $replacements[$original] =  $payment->method ?? $payment->mode;
               break;
             case 'payment_payment_status':
               $replacements[$original] = $payment->status;
               break;
             case 'payment_paid_amount':
               $replacements[$original] = $payment->getSettlementAmount();
               break;
             case 'platform_fees_vat_calculated_table':
             case 'location_amount_calculate_vat_table':
               $replacements[$original] = process_vat_tokens($payment,$name);
               break;

           }
         }
       }
    }


  }

  return $replacements;
}


function process_vat_tokens(Payment $payment, string $token) {
  $actual_amount = $payment->amount->value;
  $routed_amount = !empty($payment->routing[0]->amount->value)
    ? $payment->routing[0]->amount->value
    : $actual_amount;

  $is_platform_fees = $token === 'platform_fees_vat_calculated_table';
  $title = $payment->description ?? ($is_platform_fees ? 'Commission charge' : 'Reservation amount');

  // Determine which amount to apply VAT breakdown on.
  $vat_amount = $is_platform_fees && !empty($payment->routing[0]->amount->value)
    ? $actual_amount - $routed_amount
    : $routed_amount;

  $rates = Invoicing::calculateVATBreakdown($vat_amount);

  $content = [
    'currency' => $payment->amount->currency,
    'description' => $title,
    'rates' => $rates,
    'amount' => $vat_amount,
  ];

  $render_array = [
    '#theme' => 'invoice_table_template',
    '#title' => 'Vat table',
    '#content' => $content,
  ];

  $renderer = \Drupal::service('renderer');
  $rendered_html = $renderer->renderPlain($render_array);

  // Log the output only for non-platform fees and empty routing.
  if (!$is_platform_fees && empty($payment->routing[0]->amount->value)) {
    \Drupal::logger('payment_invoice')->error($rendered_html);
  }

  return $rendered_html;
}

