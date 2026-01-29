<?php

namespace Drupal\payment_invoice\Plugin;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Render\Markup;
use Drupal\file\Entity\File;
use Drupal\mailsystem\MailsystemManager;
use Drupal\node\Entity\Node;
use Drupal\payment_provider\Plugin\Pricing\Pricing;
use Drupal\reservation\Entity\Reservation;
use Drupal\user\Entity\User;
use Mollie\Api\Resources\Payment;
use Mpdf\MpdfException;

/**
 * @class Invoicing will be creating invoices.
 */

class Invoicing {

  /**
   * Invoice html code.
   * @var string
   */
  private string $invoice;

  /**
   * Pure html to replace with these keys
   * - CLIENT_LOGO
   * - SITE_NAME
   * - SITE_EMAIL
   * - SITE_PHONE
   * - INVOICE_ID
   * - INVOICE_DATE
   * - PAYMENT_ID
   * - CLIENT_NAME
   * - CLIENT_EMAIL
   * - CLIENT_PHONE
   * - PAYMENT_TITLE
   * - RESERVATION_SUBJECT
   * - BOOKING_AMOUNT
   * - PLATFORM_FEES
   * - SUB_TOTAL
   * - CURRENCY
   * - TOTAL_AMOUNT
   * NOTE: all goes with {KEYS HERE}
   * @var string
   */
  private string $replaceableHtml;

  /**
   * @var array|false|string|string[]
   */
  private string|array|false $processed_invoice;

  private string $invoice_id;

  /**
   * @var int|mixed|string|null
   */
  private mixed $invoice_pdf_fid;

  private string $magnus_email;
  private array $invoicesEmails;
  /**
   * @var array|mixed
   */
  private mixed $invoices;

  public function getInvoicePdfFid(): mixed {
    return $this->invoice_pdf_fid;
  }

  public function __construct(private readonly Reservation $reservation, readonly private Pricing $pricing, private readonly Payment $payment) {

    // Extract need objects
    $reservation_owner = $this->reservation->getOwner();
    $node_room = \Drupal::service('entity_type.manager')->getStorage('node')->load($this->reservation->get('entity_id')->target_id ?? 0);
    $room_owner = \Drupal::service('entity_type.manager')->getStorage('user')->load($node_room?->getOwnerId());

    // Replacing all tokens in email template at /assets/invoice/in.html
    $this->processed_invoice = '';
    $token_service = \Drupal::token();
    $reservation_one_time = \Drupal::configFactory()->get('reservation.one_time_subscription_email')->get('template_mail');
    $magnus = \Drupal::configFactory()->get('reservation.magnus_reservation_paid_subscription_email')->get('template_mail');
    $this->processed_invoice = $token_service->replace($reservation_one_time,
      [
        'user'=>  $reservation_owner,
        'reservation'=> $this->reservation,
        'node' => $node_room,
        'payment' => $this->payment,
        'pricing' => $this->pricing,
        'invoice'=> $this
      ]
    );
    $this->magnus_email = $token_service->replace($magnus, [
      'user'=>  $reservation_owner,
      'reservation'=> $this->reservation,
      'node' => $node_room,
      'payment' => $this->payment,
      'pricing' => $this->pricing,
      'invoice'=> $this
    ]);

    //TODO: this is latest remove old code above this line.
    $this->invoices = [];
    $this->invoicesEmails = [];

    $actual_amount = $payment->amount->value;
    if (!empty($payment->routing[0])) {
      $routed_amount = $payment->routing[0]->amount->value;
      $difference_amount = $actual_amount - $routed_amount;
      $this->invoices['platform_payment_invoice']  = [
        'amount' => $difference_amount,
        'rates' => self::calculateVATBreakdown($difference_amount),
        'description' => 'Platform Commission',
        'currency' => $payment->amount->currency,
      ];
      $this->invoices['reservation_payment_invoice'] = [
        'amount' => $routed_amount,
        'rates' => self::calculateVATBreakdown($routed_amount),
        'description' => 'Reservation Payment',
        'currency' => $payment->amount->currency,
      ];
    }
    $this->invoices['reservation_payment_full'] = [
      'amount' => $actual_amount,
      'rates' => self::calculateVATBreakdown($actual_amount),
      'description' => 'Reservation Payment',
      'currency' => $payment->amount->currency,
    ];

    $room = array_filter($reservation->referencedEntities(), function ($entity) {
      return $entity->bundle() === 'zaal';
    });

    $room = !empty($room) ? reset($room) : NULL;
    $location = $room->get('field_bedrijf_zaal')?->referencedEntities();
    $location = !empty($location) ? reset($location) : NULL;
    $config = \Drupal::config('system.site');

    if ($location instanceof Node) {
      foreach ($this->invoices as $key => $invoice) {

        $invoice['billed_to'] = [
          'name' => $location->getTitle(),
          'address' => $location->get('field_adres')?->getValue(),
          //'vat_number' => $location->get('field_ondernemingsnummer')?->value,
        ];
        $invoice['billed_from'] = [
          'name' =>  $config->get('name'),
          'email' =>  $config->get('mail'),
        ];
        $invoice['date'] = date('d F Y');
        $invoice['invoice_number'] = "INV-".date('Y').'-'.rand(1000,9999);
        $render_array = [
          '#theme' => 'invoices_template',
          '#title' => 'Invoice Details',
          '#content' => $invoice,
        ];

        $renderer = \Drupal::service('renderer');
        $rendered_html = $renderer->renderPlain($render_array);

        if ($key === 'reservation_payment_invoice') {
          $this->invoicesEmails[$key] = [
            'to' => $location->getOwner()->getEmail(),
            'module' => 'zaal_condities',
            'key' => 'reservation_mails',
            'send' => true,
            'params' => [
              'subject' => 'Reservation Invoice',
              'body' => $rendered_html->__toString(),
            ],
            'langcode' => 'en',
            'reply' => NULL
          ];
        }
        elseif ($key === 'platform_payment_invoice') {
          $this->invoicesEmails[$key] = [
            'to' => $location->getOwner()->getEmail(),
            'module' => 'zaal_condities',
            'key' => 'reservation_mails',
            'send' => true,
            'params' => [
              'subject' => 'Platform Commission Invoice',
              'body' => $rendered_html->__toString(),
            ],
            'langcode' => 'en',
            'reply' => NULL
          ];
        }

        //TODO: add the other email to the list here eg the email for invoice reservation_payment_full

      }

    }

  }

  public static function calculateVATBreakdown(float $priceInclVAT, float $vatRate = 21.0): array {
    // Calculate price excluding VAT
    $priceExclVAT = $priceInclVAT / (1 + ($vatRate / 100));

    // Calculate VAT amount
    $vatAmount = $priceInclVAT - $priceExclVAT;

    // Round values to 2 decimal places
    return [
      'price_excl_vat' => round($priceExclVAT, 2),
      'vat_amount'     => round($vatAmount, 2),
      'total'          => round($priceInclVAT, 2),
    ];
  }

  /**
   * Get invoicing PDF file.
   *
   * @return string
   * @throws MpdfException
   * @throws EntityStorageException
   */
  public function getInvoicingPdf(): string {

    // Starting mpdf object.
    $mpdf = new \Mpdf\Mpdf();

    // Writing html to pdf.
    $mpdf->WriteHTML($this->processed_invoice);

    // Define the directory and file path
    $directory = 'public://reservation';
    $file_path = $directory . '/'.time().'_invoices.pdf';

    // Ensure the directory exists
    \Drupal::service('file_system')->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    // Save the PDF file to the specified path
    $mpdf->Output(\Drupal::service('file_system')->realpath($file_path), 'F');

    // Create the file entity
    $file = File::create([
      'uri' => $file_path,
      'status' => 1,
    ]);
    $file->save();
    $this->invoice_pdf_fid = $file->id();
    return $file_path;
  }

  public function sendInvoices(): void
  {
    // Sending invoice email to organizer.
    $to  = $this->reservation->getAuthorEmail();
    $mailManager = \Drupal::service('plugin.manager.mail');
    $module = 'zaal_condities';
    $key = 'reservation_mails';
    $langcode = \Drupal::currentUser()->getPreferredLangcode();
    $params['body'] = Markup::create($this->processed_invoice);
    $params['subject'] = "Payment Information";

    // Loading pdf file.
    $pdf_invoice = $this->getInvoicingPdf();

    // checking if pdf was created to send to email.
    if(file_exists($pdf_invoice)) {
      $params['attachments'][] = [
        'filecontent' => file_get_contents($pdf_invoice),
        'filename' => basename($pdf_invoice),
        'filemime' => mime_content_type($pdf_invoice),
      ];
    }

    // Sending an email to a room owner of invoice of payment received.
    $node_room = \Drupal::service('entity_type.manager')->getStorage('node')->load($this->reservation->get('entity_id')->target_id ?? 0);
    /**@var $room_owner \Drupal\user\Entity\User **/
    $room_owner = \Drupal::service('entity_type.manager')->getStorage('user')->load($node_room?->getOwnerId());
    $to = $room_owner->getEmail();
    if($to) {
      $mailManager->mail($module, $key, $to, $langcode, $params, NULL, TRUE);
    }

    // Email to magnus
    /**@var $reservation_owner \Drupal\user\Entity\User **/
    $reservation_owner = $this->reservation->getOwner();
    $to = $reservation_owner->getEmail();
    if($to) {
      $params1['body'] = Markup::create($this->magnus_email);
      $params1['subject'] = "Payment Information";
      $mailManager->mail($module, $key, $to, $langcode, $params1, NULL, TRUE);
    }

    //    /**@var MailsystemManager $mail_service **/
    //    $mail_service = \Drupal::service('plugin.manager.mail');
    //    foreach ($this->invoicesEmails as $key => $email) {
    //      $mail_service->mail(...$email);
    //    }
  }

  /**
   * Return symbol currency uses eg GBP is �
   * @param string $code
   *
   * @return string
   */
  public static function currencies(string $code): string {

    // All currencies in world with code and symbol.
    $currency_symbols = [
      'AED' => '&#1583;.&#1573;',
      'AFN' => '&#1547;',
      'ALL' => 'L',
      'AMD' => '&#1423;',
      'ANG' => '�',
      'AOA' => 'Kz',
      'ARS' => '$',
      'AUD' => 'A$',
      'AWG' => '�',
      'AZN' => '&#8380;',
      'BAM' => 'KM',
      'BBD' => 'Bds$',
      'BDT' => '&#2547;',
      'BGN' => '&#1083;&#1074;',
      'BHD' => '&#1576;.&#1583;',
      'BIF' => 'FBu',
      'BMD' => '$',
      'BND' => 'B$',
      'BOB' => 'Bs.',
      'BRL' => 'R$',
      'BSD' => 'B$',
      'BTN' => 'Nu.',
      'BWP' => 'P',
      'BYN' => 'Br',
      'BZD' => 'BZ$',
      'CAD' => 'C$',
      'CDF' => 'FC',
      'CHF' => 'CHF',
      'CLP' => '$',
      'CNY' => '�',
      'COP' => '$',
      'CRC' => '&#8353;',
      'CUP' => '&#8369;',
      'CVE' => 'Esc',
      'CZK' => 'K&#269;',
      'DJF' => 'Fdj',
      'DKK' => 'kr',
      'DOP' => 'RD$',
      'DZD' => '&#1583;.&#1580;',
      'EGP' => 'E�',
      'ERN' => 'Nfk',
      'ETB' => 'Br',
      'EUR' => '�',
      'FJD' => 'FJ$',
      'FKP' => '�',
      'FOK' => 'kr',
      'GBP' => '�',
      'GEL' => '&#8382;',
      'GGP' => '�',
      'GHS' => '&#8373;',
      'GIP' => '�',
      'GMD' => 'D',
      'GNF' => 'FG',
      'GTQ' => 'Q',
      'GYD' => 'G$',
      'HKD' => 'HK$',
      'HNL' => 'L',
      'HRK' => 'kn',
      'HTG' => 'G',
      'HUF' => 'Ft',
      'IDR' => 'Rp',
      'ILS' => '&#8362;',
      'IMP' => '�',
      'INR' => '&#8377;',
      'IQD' => '&#1593;.&#1583;',
      'IRR' => '&#65020;',
      'ISK' => 'kr',
      'JEP' => '�',
      'JMD' => 'J$',
      'JOD' => '&#1583;.&#1575;',
      'JPY' => '�',
      'KES' => 'KSh',
      'KGS' => '&#1089;&#1086;&#1084;',
      'KHR' => '&#6107;',
      'KID' => '$',
      'KMF' => 'CF',
      'KRW' => '&#8361;',
      'KWD' => '&#1583;.&#1603;',
      'KYD' => 'CI$',
      'KZT' => '&#8376;',
      'LAK' => '&#8365;',
      'LBP' => '&#1604;.&#1604;',
      'LKR' => 'Rs',
      'LRD' => '$',
      'LSL' => 'M',
      'LYD' => '&#1604;.&#1583;',
      'MAD' => '&#1583;.&#1605;.',
      'MDL' => 'L',
      'MGA' => 'Ar',
      'MKD' => '&#1076;&#1077;&#1085;',
      'MMK' => 'K',
      'MNT' => '&#8366;',
      'MOP' => 'MOP$',
      'MRU' => 'UM',
      'MUR' => '&#8360;',
      'MVR' => 'Rf',
      'MWK' => 'MK',
      'MXN' => '$',
      'MYR' => 'RM',
      'MZN' => 'MT',
      'NAD' => 'N$',
      'NGN' => '&#8358;',
      'NIO' => 'C$',
      'NOK' => 'kr',
      'NPR' => '&#8360;',
      'NZD' => 'NZ$',
      'OMR' => '&#1585;.&#1593;.',
      'PAB' => 'B/.',
      'PEN' => 'S/.',
      'PGK' => 'K',
      'PHP' => '&#8369;',
      'PKR' => '&#8360;',
      'PLN' => 'z&#322;',
      'PYG' => '&#8370;',
      'QAR' => '&#1585;.&#1602;',
      'RON' => 'lei',
      'RSD' => '&#1076;&#1080;&#1085;.',
      'RUB' => '&#8381;',
      'RWF' => 'FRw',
      'SAR' => '&#65020;',
      'SBD' => 'SI$',
      'SCR' => '&#8360;',
      'SDG' => '&#1580;.&#1587;.',
      'SEK' => 'kr',
      'SGD' => 'S$',
      'SHP' => '�',
      'SLL' => 'Le',
      'SOS' => 'Sh',
      'SRD' => '$',
      'SSP' => '�',
      'STN' => 'Db',
      'SYP' => '�',
      'SZL' => 'E',
      'THB' => '&#3647;',
      'TJS' => 'SM',
      'TMT' => 'T',
      'TND' => '&#1583;.&#1578;',
      'TOP' => 'T$',
      'TRY' => '&#8378;',
      'TTD' => 'TT$',
      'TVD' => '$',
      'TWD' => 'NT$',
      'TZS' => 'TSh',
      'UAH' => '&#8372;',
      'UGX' => 'USh',
      'USD' => '$',
      'UYU' => '$U',
      'UZS' => 'so&#699;m',
      'VES' => 'Bs.',
      'VND' => '&#8363;',
      'VUV' => 'VT',
      'WST' => 'WS$',
      'XAF' => 'FCFA',
      'XCD' => 'EC$',
      'XOF' => 'CFA',
      'XPF' => '&#8355;',
      'YER' => '&#65020;',
      'ZAR' => 'R',
      'ZMW' => 'K',
      'ZWL' => '$',
    ];

    return $currency_symbols[$code] ?? $code;

  }

  /**
   * Creating invoice for payment.
   *
   * @return void
   * @throws EntityStorageException
   */
  public function createInvoiceOfPayment(): void {

    // Extract need objects
    $reservation_owner = $this->reservation->getOwner();
    $information = $this->reservation->get('field_reservation_information')->referencedEntities();
    $information = reset($information);

    if ($information instanceof Node) {

      $node_room = \Drupal::service('entity_type.manager')->getStorage('node')->load($this->reservation->get('entity_id')->target_id ?? 0);
      $room_owner = \Drupal::service('entity_type.manager')->getStorage('user')->load($node_room?->getOwnerId());

      $module = \Drupal::moduleHandler()->getModule('payment_invoice');
      $root = $module->getPath();
      $this->invoice = trim($root, '/') . '/assets/invoice/in.html';
      $this->replaceableHtml = file_get_contents($this->invoice);

      $price = $this->pricing->getInvoiceStorage();

      // Using Pricing object to get per hour and per day patterns.
      $per_hour_info = 0;
      $per_day_info = 0;
      $per_person = 0;
      $hours_booked = 0;
      if ($information->get('field_unit_type')?->value  === 'hourly') {
        $per_hour_info = $information->get('field_amount_used')->value ?? 0;
        $hours_booked = $information->get('field_hours')->value ?? 0;
      }
      elseif ($information->get('field_unit_type')->value === 'day') {
        $per_hour_info = $information->get('field_amount_used')->value ?? 0;
        $hours_booked = ($information->get('field_days')->value ?? 0) * 24;
      }
      elseif ($information->get('field_unit_type')->value === 'person') {
        $per_person = $information->get('field_amount_used')->value ?? 0;
      }
      $duration = $this->reservation->get('field_date_booking')->getValue()[0]['duration'] ?? 0;

      // Finding out how left to be paid.
      $remaining_balance = $price['calculated_price']['booking_amount'] - $this->payment->getSettlementAmount();
      if($remaining_balance < 0) {
        $remaining_balance = 0;
      }

      Node::create(
        [
          'type'=> 'invoices_receipt',
          'status' => 1,
          'title' => $this->payment->description,
          'field_attendees' => $this->reservation->get('field_bezetting')->value ?? 1,
          'field_booking_amount' => $this->pricing->formatCurrency($price['calculated_price']['booking_amount']),
          'field_hours_booked' => ceil($hours_booked),
          'field_payment_by' => ['target_id' => $reservation_owner->id()],
          'field_payment_method' => $this->payment->method,
          'field_payment_status' => $this->payment->status,
          'field_payment_type' => $this->payment->metadata->payment_type,
          'field_pending_balance' => $this->pricing->formatCurrency($remaining_balance),
          'field_per_hour' => $per_hour_info,
          'field_per_day' => $per_day_info,
          'field_platform_fee' => $this->pricing->formatCurrency($price['calculated_price']['platform_fee'] ?? 0),
          'field_room_owner' => ['target_id' => $room_owner->id()],
          'field_settled_amount' => $this->pricing->formatCurrency($this->payment->getSettlementAmount()),
          'field_reservation' => ['target_id' => $this->reservation->id()],
          'field_room_ref' => ['target_id' => $node_room->id()],
          'uid' => $room_owner?->id() ?? $reservation_owner->id(),
          'field_invoice_pdf' => ['target_id' => $this->invoice_pdf_fid],
          'promote' => 0,
        ]
      )
        ->enforceIsNew(TRUE)
        ->save();
    }
  }

  /**
   * Creating an invoice to send for one-time payment.
   * @param Payment $payment
   *
   * @return void
   * @throws EntityStorageException
   * @throws MpdfException
   */
  public static function oneTimePaymentInvoice(Payment $payment): void {

    $user_id = $payment->metadata?->user_id ?? 0;
    $user = null;
    if($user_id) {
      $user = User::load($user_id);
    }

    if ($user instanceof User) {
      try{
        $token_service = \Drupal::token();
        $reservation_one_time = \Drupal::configFactory()->get('reservation.subscription_email_one_time')->get('template_mail');
        $processed_invoice = $token_service->replace($reservation_one_time,
          [
            'user'=>  $user,
            'reservation'=> null,
            'node' => null,
            'payment' => $payment,
            'pricing' => null,
            'invoice'=> null,
          ]
        );

        // Starting mpdf object.
        $mpdf = new \Mpdf\Mpdf();

        // Writing html to pdf.
        $mpdf->WriteHTML($processed_invoice);

        // Define the directory and file path
        $directory = 'public://reservation';
        $file_path = $directory .'/'.time().'_invoices_one_time.pdf';

        // Ensure the directory exists
        \Drupal::service('file_system')->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

        // Save the PDF file to the specified path
        $mpdf->Output(\Drupal::service('file_system')->realpath($file_path), 'F');

        // Create the file entity
        $file = File::create([
          'uri' => $file_path,
          'status' => 1,
        ]);
        $file->save();

        Node::create(
          [
            'type'=> 'invoices_receipt',
            'status' => 1,
            'title' => $payment->description,
            'field_payment_by' => ['target_id' => $user_id],
            'field_payment_method' => $payment->method,
            'field_payment_status' => $payment->status,
            'field_payment_type' => 'one time payment',
            'field_room_owner' => ['target_id' => $user_id],
            'field_settled_amount' => $payment->getSettlementAmount(),
            'uid' => $user_id,
            'field_invoice_pdf' => ['target_id' => $file->id()],
            'promote' => 0,
          ]
        )
          ->enforceIsNew(TRUE)
          ->save();

        $invoicesEmails = [
          'to' => $user->getEmail(),
          'module' => 'zaal_condities',
          'key' => 'reservation_mails',
          'send' => true,
          'params' => [
            'subject' => 'Subscription invoice',
            'body' =>  $processed_invoice,
          ],
          'langcode' => 'en',
          'reply' => NULL
        ];

        // checking if pdf was created to send to email.
        if(file_exists($file_path)) {
          $invoicesEmails['params']['attachments'][] = [
            'filecontent' => file_get_contents($file_path),
            'filename' => basename($file_path),
            'filemime' => mime_content_type($file_path),
          ];
        }
        \Drupal::service('plugin.manager.mail')->mail(...$invoicesEmails);
      }catch (\Throwable $e){
        \Drupal::logger('payment_invoice')->error($e->getMessage().'\n'.$e->getTraceAsString());
      }
    }


  }

  /**
   * Building services table.
   * @return string|null
   */
  public function additionalTemplate(): ?string {
    $additional_services = $this->pricing->getBookedAddtionalServices();
    if($additional_services) {
      $total = $additional_services['total_additional_services_amount'];
      $currency = null;
      unset($additional_services['total_additional_services_amount']);
      $tr = null;
      foreach($additional_services as $service) {
        $currency = self::currencies($service['currency']);
        $tr .= <<<TR
<tr>
      <td style="padding: 10px; border: 1px solid #ddd;">{$service['service_name']}</td>
      <td style="padding: 10px; border: 1px solid #ddd; text-align: right;">{$service['count_service']}</td>
      <td style="padding: 10px; border: 1px solid #ddd; text-align: right;">$currency{$service['amount']}</td>
    </tr>
TR;
      }
      if(!empty($tr)) {
        return <<<TABLE
<div style="margin-bottom: 10px; margin-top: 10px;">
    <strong>Additional services.</strong>
  </div>
  <table style="width: 100%; border-collapse: collapse; border: 1px solid #ddd;">
    <thead>
    <tr style="background-color: #f7f7f7;">
      <th style="padding: 10px; border: 1px solid #ddd;">Service</th>
      <th style="padding: 10px; border: 1px solid #ddd; text-align: right;">Count</th>
      <th style="padding: 10px; border: 1px solid #ddd; text-align: right;">Total</th>
    </tr>
    </thead>
    <tbody>
     $tr
     <tr>
      <td style="padding: 10px; border: 1px solid #ddd;"></td>
      <td style="padding: 10px; border: 1px solid #ddd; text-align: right;">Total</td>
      <td style="padding: 10px; border: 1px solid #ddd; text-align: right;">$currency{$total}</td>
    </tr>
    </tbody>
  </table>
  <br>
TABLE;
      }
    }
    return null;
  }

  /**
   * Making table of per-person listing.
   * @return string|null
   */
  public function perPersonOptions():string|null {

    $per_person_options = $this->pricing->getPerPersonOptions();
    $tr = null;
    foreach($per_person_options as $key=>$option) {
      $option_list = explode('#', $option['value'] ?? '');
      $subject = trim($option_list[0] ?? '');
      $count = trim($option_list[1] ?? '');
      $tr .= <<<TR
      <tr>
      <td style="padding: 10px; border: 1px solid #ddd;">{$subject}</td>
      <td style="padding: 10px; border: 1px solid #ddd; text-align: right;">{$count}</td>
    </tr>
TR;
    }
    if(!empty($tr)) {
      return <<<TABLE
<div style="margin-bottom: 10px; margin-top: 10px;">
    <strong>Per person options.</strong>
  </div>
  <table style="width: 100%; border-collapse: collapse; border: 1px solid #ddd;">
    <thead>
    <tr style="background-color: #f7f7f7;">
      <th style="padding: 10px; border: 1px solid #ddd;">Subject</th>
      <th style="padding: 10px; border: 1px solid #ddd; text-align: right;">Total</th>
    </tr>
    </thead>
    <tbody>
     $tr
    </tbody>
  </table>
  <br>
TABLE;
    }
    return null;
  }

}
