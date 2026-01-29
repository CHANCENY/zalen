<?php

namespace Drupal\payment_provider\Plugin\Pricing;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\payment_provider\Plugin\PaymentProvider\MollieConfigValidator;
use Drupal\reservation\Entity\Reservation;
use Drupal\room_invoice\Entity\InvoicePayment;
use Drupal\user\Entity\User;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Payment;

/**
 * Pricing class will be handling the calculation of amounts based on a set of
 * rules.
 * @class Pricing.
 */
class Pricing {

  private array $invoice_storage;

  private array $reports = array();

  private array $booked_addtional_services;

  private array $per_person_options;

  public function getPerPersonOptions(): array {
    return $this->per_person_options;
  }

  public function getBookedAddtionalServices(): array {
    return $this->booked_addtional_services;
  }

  public function getReports(): array {
    return $this->reports;
  }

  public function getInvoiceStorage(): array {
    return $this->invoice_storage;
  }

  private array $calculated_price;

  /**
   * @param Reservation $reservation
   * @param Node $room
   * @param Paragraph $reservationInformation
   * @param string $roomType
   * @param bool $has_mollie
   */
  public function __construct(
    private readonly Reservation $reservation,
    private readonly Node        $room,
    private readonly Paragraph   $reservationInformation,
    string                       $roomType,
    bool                         $has_mollie = FALSE
  ) {

    // check room if is of a premium type.
    if ($roomType === 'premium_zaal') {
      $total_price = $this->reservationInformation->get('field_total_amount')->value;
      $this->calculated_price = $this->applyingPlatformFees($total_price, 'premium_zaal', $has_mollie);
    }
    else {
      $total_price = $this->reservationInformation->get('field_total_amount')->value;
      $this->calculated_price = $this->applyingPlatformFees($total_price, 'zaal_eigenaar');
    }
    $this->invoice_storage['calculated_price'] = $this->calculated_price;
  }

  /**
   * Loading pattern.
   * @param string $pattern_value eg per_hour
   * @param array $patterns data from field field_prijs_eenheid.
   *
   * @return array
   */
  public static function findPatternPricing(string $pattern_value, array $patterns): array {
    $pattern_found = array_filter($patterns, function($pattern) use ($pattern_value) {
      return $pattern['pattern'] === $pattern_value;
    });
    if(empty($pattern_found)) {
      return [];
    }
    return reset($pattern_found);
  }

  public static function hoursFromMinutes(int $minutes): int|float {
    // Check if the input is numeric and positive
    if (!is_numeric($minutes) || $minutes < 0) {
      return 0;
    }
    // Return the result in hours and minutes format
    return $minutes / 60;
  }

  public static function daysFromMinutes(int $minutes): int|float {
    // Check if the input is positive
    if ($minutes < 0) {
      return 0;
    }

    // Calculate the days
    $days = $minutes / (60 * 24);

    // Round the result based on the decimal part
    $decimalPart = $days - floor($days);
    if ($decimalPart >= 0.5) {
      // Round up to the nearest whole number
      $days = ceil($days);
    } else {
      // Round down to the nearest whole number
      $days = floor($days);
    }

    return $days;
  }

  /**
   * Calculate price based on a pattern used for room.
   * @return array
   */
  public function getCalculatedPrice(): array
  {
    return $this->calculated_price;
  }

  /**
   * Finding all amounts break down.
   *
   * @param int $price
   * @param $location_type
   * @param bool $has_mollie
   *
   * @return array|string
   */
  public static function applyingPlatformFees(int $price, $location_type, bool $has_mollie = FALSE): array|string
  {
    // Calculate the platform fee based on the total booking amount
    $platform_fee = self::calculatePlatformFee($price, $location_type, $has_mollie);

    // Advancement percentage.
    $advance_percentage = 10;

    // Calculate the advance payment
    $advance_payment = $price * ($advance_percentage / 100);

    $location_amount = 0;
    $platform_amount = 0;

    if ($location_type === 'zaal_eigenaar') {
      // Customer pays the advance payment amount
      $customer_payment = $advance_payment;

      // Calculate the split amounts
      if ($customer_payment >= $platform_fee) {
        $platform_amount = $platform_fee;
        $location_amount = $customer_payment - $platform_fee;
      } else {
        // If the advance payment is less than the platform fee, adjust accordingly
        $platform_amount = $customer_payment;
      }
    }
    elseif ($location_type === 'premium_zaal') {

      // If the room owner accepts mollie payment.
      if($has_mollie) {
        $platform_amount = $platform_fee;
        $customer_payment = $advance_payment;

        if ($customer_payment >= $platform_fee) {
          $location_amount = $customer_payment - $platform_fee;
        }
        else {
          // If the advance payment is less than the platform fee, adjust accordingly
          $platform_amount = $customer_payment;
        }
      }
    }

    return [
      'booking_amount' => $price,
      'advance_payment' => $advance_payment,
      'platform_fee' => $platform_fee,
      'platform_amount' => $platform_amount,
      'location_amount' => $location_amount,
    ];
  }

  /**
   * Find platform fees.
   * Calculating platform fees.
   *
   * @param $amount
   * @param string $type
   * @param bool $has_mollie
   *
   * @return float
   */
  private static function calculatePlatformFee($amount, string $type, bool $has_mollie): float
  {
    // Do platform free calculation on a standard
    if($type === 'zaal_eigenaar') {
      if ($amount <= 50) {
        return 5.99;
      } elseif ($amount <= 250) {
        return $amount * 0.10;
      } elseif ($amount <= 500) {
        return $amount * 0.08;
      } elseif ($amount <= 1000) {
        return $amount * 0.07;
      } else {
        return $amount * 0.05;
      }
    }

    // Checking if accept by mollie then apply 4.99 if not no charges but contact form.
    if($type === 'premium_zaal' && $has_mollie) {
      return 4.99;
    }
    return 0;
  }

  /**
   * Making payment on mollie and getting a checkout link of payment.
   *
   * @param bool $is_advance_payment True is only advance will be paid
   * @param string $redirect_endpoint This must have domain name and protocol.
   * @param string|null $web_book_url Web book url where mollie can send data.
   * @param bool $is_split_payment True if you are making split payment.
   * @param string|null $partner_org_id Organization id of the vip partner.
   *
   * @return string
   * @throws \Mollie\Api\Exceptions\ApiException
   */
  public function getCheckoutLink(bool $is_advance_payment,
    string $redirect_endpoint,
    string|null $web_book_url = null,
    bool $is_split_payment = TRUE,
    string|null $partner_org_id = null):string
  {

    // Mollie credentials class
    $config_mollie = new MollieConfigValidator();

    // Start mollie payment gateway.
    $mollie = new \Mollie\Api\MollieApiClient();
    $mollie->setApiKey($config_mollie->getTestApiKey());
    $mollie->setAccessToken($config_mollie->get('token_organization_access'));
    $payment_data = [];

    $payment_type= 'Full payment';

    $force_not_split = $is_advance_payment && $this->calculated_price['advance_payment'] - $this->calculated_price['platform_fee'] <= 0;

    // Making payment without splitting.
    if($is_split_payment === FALSE || $force_not_split === TRUE) {
      // Build data object for making checkout link link for mollie.
      if($is_advance_payment) {
        $payment_data = [
          'amount' => [
            'currency' => strtoupper($this->reservationInformation->get('field_reservation_currency')?->vaue ?? 'eur'),
            'value' => $this->formatCurrency($this->calculated_price['advance_payment']),
          ],
        ];
        $payment_type = 'Advance payment';
      }
      else {
        $payment_data = [
          'amount' => [
            'currency' => strtoupper($this->reservationInformation->get('field_reservation_currency')?->vaue ?? 'eur'),
            'value' => $this->formatCurrency($this->calculated_price['booking_amount']),
          ],
        ];
      }
    }

    else {
      // Making sure that access token of whom we will be doing splitting with is given.
      if(!empty($partner_org_id)) {
        if($is_advance_payment) {
          $payment_data = array(
            'amount' => [
              'currency' => strtoupper($this->reservationInformation->get('field_reservation_currency')?->vaue ?? 'eur'),
              'value' => $this->formatCurrency($this->calculated_price['advance_payment']),
            ],
            'profileId'=> $config_mollie->get('profile_id'),
            'description' => 'Payment for reservation of #'.$this->room->getTitle(),
            'redirectUrl' => $redirect_endpoint,
            'webhookUrl' => $web_book_url,
            'cancelUrl' => $redirect_endpoint,
            'routing' => [
              [
                'amount' => [
                  'currency' => strtoupper($this->reservationInformation->get('field_reservation_currency')?->vaue ?? 'eur'),
                  'value' =>  $this->formatCurrency($this->calculated_price['advance_payment'] - $this->calculated_price['platform_fee']),
                ],
                'destination' => [
                  'type' => 'organization',
                  'organizationId' => $partner_org_id,
                ],
              ],
            ],
          );
          $payment_type = 'Advance payment';
        }

        else {
          $payment_data = array(
            'amount' => [
              'currency' => strtoupper($this->reservationInformation->get('field_reservation_currency')?->vaue ?? 'eur'),
              'value' => $this->formatCurrency($this->calculated_price['booking_amount']),
            ],
            'profileId'=> $config_mollie->get('profile_id'),
            'description' => 'Payment for booking of #'.$this->room->getTitle(),
            'redirectUrl' => $redirect_endpoint,
            'webhookUrl' => $web_book_url,
            'routing' => [
              [
                'amount' => [
                  'currency' => strtoupper($this->reservationInformation->get('field_reservation_currency')?->vaue ?? 'eur'),
                  'value' =>  $this->formatCurrency($this->calculated_price['booking_amount'] - ($this->calculated_price['platform_amount'] ?? $this->calculated_price['platform_fee'])),
                ],
                'destination' => [
                  'type' => 'organization',
                  'organizationId' => $partner_org_id,
                ],
              ],
            ],
          );
        }
      }
    }

    // Adding more required api data.
    $payment_data = array_merge($payment_data, array(
      'testmode'=> TRUE,  //TODO: remember to leave <-- or set it to FALSE.
      "method" => array('bancontact', 'belfius', 'creditcard','kbc','paysafecard','ideal'), // TODO: add more payment methods here
      'profileId'=> $config_mollie->get('profile_id'),
      "description" => 'Payment for booking of ('.$this->room->getTitle() . ')',
      "redirectUrl" => $redirect_endpoint,
      "webhookUrl" => $web_book_url,
      'cancelUrl' => $redirect_endpoint,
      'metadata' => [
        'invoice'=> time(),
        'payment_type' => $payment_type,
      ],
    ));

    // Creating payment to get a checkout link.
    $payment_created = $mollie->payments->create($payment_data);

    // Storing the payment created object in cache for later usage.
    $cache_id = explode('/', $redirect_endpoint);
    \Drupal::cache()->set('payment_provider_created_'.end($cache_id), $payment_created, CacheBackendInterface::CACHE_PERMANENT);
    return $payment_created->getCheckoutUrl();
  }

  /**
   * Formatting amount for mollie payment.
   * @param int|float $amount numerical figure need to be formatted.
   *
   * @return string
   */
  public function formatCurrency(int|float $amount): string {
    // Check if the string contains a decimal point
    $amount = (string) $amount;
    if (strpos($amount, '.') !== false) {
      // Get the decimal part
      $parts = explode('.', $amount);
      $decimalPart = $parts[1];

      // Check the length of the decimal part
      if (strlen($decimalPart) == 1) {
        // If it has only one digit, append a zero
        $amount .= '0';
      } elseif (strlen($decimalPart) == 0) {
        // If it has no digits, append '00'
        $amount .= '00';
      }
    } else {
      // If there's no decimal point, append '.00'
      $amount .= '.00';
    }

    return $amount;
  }

  /**
   * Get a Payment object after redirect.
   * @param string $payment_id Payment id.
   *
   * @return mixed
   * @throws \Mollie\Api\Exceptions\ApiException
   */
  public function justPaymentMade(string $payment_id , bool $is_test_mode = false): Payment {
    // Mollie credentials class
    $config_mollie = new MollieConfigValidator();
    // Start mollie payment gateway.
    $mollie = new \Mollie\Api\MollieApiClient();
    $mollie->setApiKey($config_mollie->getTestApiKey());
    $mollie->setAccessToken($config_mollie->get('token_organization_access'));
    return $mollie->payments->get($payment_id,['testmode'=>$is_test_mode]); //TODO: remember to leave <-- or set it to FALSE
  }

  /**
   * Creating invoice node.
   * @param \Mollie\Api\Resources\Payment $payment
   * @param \Drupal\reservation\Entity\Reservation $reservation
   * @param \Drupal\node\Entity\Node $room
   * @param \Drupal\user\Entity\User $room_owner
   *
   * @return void
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function paymentInvoiceCreation(Payment $payment, Reservation $reservation, Node $room, User $room_owner): void {

    $currentDate = (new \Drupal\Core\Datetime\DrupalDateTime)->getTimestamp();
    $invoice = InvoicePayment::create(
      array(
        'title' => 'Reservation payment user (ID' . $reservation->getOwnerId(). ')',
        'date' => $currentDate,
        'recipient' => $room_owner->id(),
        'author' => $reservation->getOwnerId(),
        'target_type_order' => 'reservation',
        'attendees' => $reservation->get('field_bezetting')->getValue(),
        'money' => $payment->getSettlementAmount(),
        'currency' => $payment->amount->currency,
        'payment_method' => 'reservation-booking-payment',
        'connection_method' => \Drupal\payment_invoice\Form\PremiumAccountForm::CONNECTION_METHOD,
        'payment_mode' => $payment->mode,
        'payment_status' => ['date' => $currentDate, 'meaning' => $payment->status],
        'transaction_id' => $payment->id,
        'sequence_type' => $payment->sequenceType,
        'customers_id' => $reservation->getOwnerId(),
        'purpose_payment' => $payment->description,
        'seller'
      )
    );
    $allowed_formats = $invoice->getDescriptionAllowedFormats();
    $allowed_formats = in_array('basic_html', $allowed_formats) ? 'basic_html' : current($allowed_formats);
    $allowed_description = array(
      'transaction_id' => $payment->id,
      'connection_method' => $payment->mode,
      'profileId' => $payment->profileId,
      'paidAt' => $payment->paidAt,
      'customerId' => $payment->customerId,
      'method' => $payment->method,
      'mandateId' => $payment->mandateId,
      'description' => $payment->description,
    );

    $invoice->setDescriptionFromArray($allowed_description, $allowed_formats);
    $invoice->save();
  }

  /**
   * Finds the number of days between two timestamps.
   * @param $startTimestamp
   * @param $endTimestamp
   *
   * @return float|int
   */
  public static function calculateDaysBetweenTimestamps($startTimestamp, $endTimestamp): float|int {
    // Convert timestamps to DateTime objects
    $startDate = new \DateTime();
    $startDate->setTimestamp($startTimestamp);

    $endDate = new \DateTime();
    $endDate->setTimestamp($endTimestamp);

    // Calculate the difference in the past days
    $interval = $startDate->diff($endDate);

    // Return the difference in the past days
    return $interval->days + 1;
  }

  /**
   * Find days and minutes between timestamps
   *
   * @param $startTimestamp
   * @param $endTimestamp
   *
   * @return float|int
   */
  public static function calculateMinutesTimestamps($startTimestamp, $endTimestamp): float|int {
    // Calculate the difference in minutes
    return abs($endTimestamp - $startTimestamp) / 60;
  }

  public static function calculateHoursBetweenTimestamps($startTimestamp, $endTimestamp): float {
    // Convert timestamps to DateTime objects
    $startDate = new \DateTime();
    $startDate->setTimestamp($startTimestamp);

    $endDate = new \DateTime();
    $endDate->setTimestamp($endTimestamp);

    // Calculate the difference
    $interval = $startDate->diff($endDate);

    // Calculate total hours
    // Return the difference in hours
    return ($interval->days * 24) + $interval->h + ($interval->i / 60) + ($interval->s / 3600);
  }

  public static function personPriceRule(int $attend, int $nid): ?float {
    $node = Node::load($nid);
    if (!$node || !$node->hasField('field_regels')) {
        return null;
    }

    $price_rules = $node->get('field_regels')->getValue();

    if (empty($price_rules)) {
        return null;
    }

    // Filter for rules with pattern_tariff = 'i_person'
    $filtered = array_filter($price_rules, function ($item) {
        return isset($item['pattern_tariff']) && $item['pattern_tariff'] === 'i_person';
    });

    // Further filter where span_time is less than or equal to attendance
    $eligible = array_filter($filtered, function ($item) use ($attend) {
        return isset($item['span_time']) && (int)$item['span_time'] <= $attend;
    });

    // Find the rule with the highest span_time among the eligible ones
    $closest = null;
    foreach ($eligible as $item) {
        if (!isset($item['span_time'])) {
            continue;
        }
        if (!$closest || (int)$item['span_time'] > (int)$closest['span_time']) {
            $closest = $item;
        }
    }
    return isset($closest['price']) ? $closest['price'] : null;
  }

}

