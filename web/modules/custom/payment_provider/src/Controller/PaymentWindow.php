<?php

namespace Drupal\payment_provider\Controller;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Render\Markup;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\payment_invoice\Plugin\Invoicing;
use Drupal\payment_provider\Form\StandardPartnerPaymentForm;
use Drupal\payment_provider\Form\VipDirectPaymentContactForm;
use Drupal\payment_provider\Plugin\PaymentProvider\MolliePaymentClient;
use Drupal\payment_provider\Plugin\Pricing\Pricing;
use Drupal\reservation\Entity\Reservation;
use Drupal\room_invoice\Entity\InvoicePayment;
use Drupal\user\Entity\User;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Invoice;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * PaymentWindow is for displaying what need to be done for payments based on
 * a room reservation type.
 *
 * @class PaymentWindow
 *
 */

class PaymentWindow extends ControllerBase {

  /**
   * Injecting dependencies in controller.
   *
   * @param RequestStack $requestStack
   * @param $entityTypeManager
   * @param CacheBackendInterface $cacheFactory
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   */

  /**
   * Messenger interface.
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected  $messenger;

  protected $currentUser;

  protected $configFactory;

  public function __construct(
    private RequestStack $requestStack,
    protected $entityTypeManager,
    protected CacheBackendInterface $cacheFactory,
    MessengerInterface $messenger,
    protected KillSwitch $killSwitch,
    AccountProxyInterface $currentUser,
    ConfigFactoryInterface $configFactory
  ) {
    $this->messenger = $messenger;
    $this->currentUser = $currentUser;
    $this->configFactory = $configFactory;

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('request_stack'),
      $container->get('entity_type.manager'),
      $container->get('cache.default'),
      $container->get('messenger'),
      $container->get('page_cache_kill_switch'),
      $container->get('current_user'),
      $container->get('config.factory')
    );
  }

  /**
   * Controller for a link to be sent in email for making payment.
   * @return array|string[]
   */
  public function paymentWindowDisplay(): array {

    // Getting reservation id from current uri.
    $reservation_id = $this->requestStack->getCurrentRequest()->get('reservation_id');

    // Let's be sure that we have reservation id to proceed.
    if (empty($reservation_id)) {
      return [
        '#markup' => '<p>Error occurred please contact site administrator for help.</p>',
      ];
    }

    // Getting a reservation node to work with.
    $reservation = $this->entityTypeManager->getStorage('reservation')->load($reservation_id);
    $node_room = $this->entityTypeManager->getStorage('node')->load($reservation->get('entity_id')->target_id ?? 0);
    $room_owner = $this->entityTypeManager->getStorage('user')->load($node_room?->getOwnerId());
    $reservationInformation = $reservation?->get('field_reservation_information')->referencedEntities();
    $reservationInformation = reset($reservationInformation);

    if ($reservation instanceof Reservation &&
      $node_room instanceof Node && $node_room->bundle() === 'zaal' &&
      $room_owner instanceof User &&
      $reservationInformation instanceof Paragraph && $reservationInformation->bundle() === 'reservation') {

      if ($room_owner->hasRole('zaal_eigenaar')) {
        // Calculate the break-down of amounts.
        $pricing = new Pricing($reservation, $node_room, $reservationInformation,'zaal_eigenaar', TRUE);
        $payments = $pricing->getCalculatedPrice();
        return array(
          '#theme' => 'payments_breakdown_standard_layout',
          '#title' => 'Payment Breakdown',
          '#content' => array(
            'reservation_information' => $reservationInformation,
            'room' => $node_room,
            'room_owner' => $room_owner,
            'payment_breakdown' => $payments,
            'form' => $this->formBuilder()->getForm(StandardPartnerPaymentForm::class),
          ),
        );
//        return array(
//          '#markup' => '<p>' . $this->t('You can choose below the payments you will be making.') . '</p>',
//          'form' => $this->formBuilder()->getForm(StandardPartnerPaymentForm::class),
//        );
      }

      if ($room_owner->hasRole('premium_zaal')) {

        // Checking if vip owner has requested for mollie payment or not
        if ($room_owner->get('field_betaling_accepteren_via_mo')?->value === 'ja') {

          // Calculate the break-down of amounts.
          $pricing = new Pricing($reservation, $node_room, $reservationInformation,'premium_zaal', TRUE);
          $payments = $pricing->getCalculatedPrice();

          //dd($reservationInformation, $payments);
          $cache_id = time();

          // Creating links for advance payment only or full amount.
          $advance_link = Url::fromRoute('payment_provider.payment.vip_mollie',
            array('cache_id' => $cache_id, 'payment_type' => 'advance'))->toString();

          $full_link = Url::fromRoute('payment_provider.payment.vip_mollie',
            array('cache_id' => $cache_id, 'payment_type' => 'full'))->toString();

          $payments['total_advance'] = number_format($payments['advance_payment'], 2);
          $payments['total_full'] = number_format($payments['booking_amount'], 2);

          $this->cacheFactory->set('payment'.$cache_id, [
            'pricing' => $pricing,
            'reservation' => $reservation,
            'reservation_information' => $reservationInformation,
            'room_node' => $node_room,
            'room_owner' => $room_owner,
          ], CacheBackendInterface::CACHE_PERMANENT);

          // Build a render array.
          return array(
            '#theme' => 'payments_breakdown_layout',
            '#title' => 'Payment Breakdown',
            '#content' => array(
              'advance_link' => $advance_link,
              'full_link' => $full_link,
              'payment_breakdown' => $payments,
              'reservation_information' => $reservationInformation,
              'room' => $node_room,
              'room_owner' => $room_owner,
            ),
          );

        }
        //If a room owner has no allowed mollie payment.
        return array(
          '#markup' => $this->t('Fill this form with your contact details for room owner to contact you.'),
          'form' => $this->formBuilder()->getForm(VipDirectPaymentContactForm::class),
        );
      }

    }
    return [
      "#markup" => $this->t("Sorry there is problem with room owner info please contact site administrator."),
    ];
  }

  /**
   * Controller for VIP payment section page.
   * @return string[]
   */
  public function paymentVip(): array
  {
    // Based on user selection, the payment will be created for advance only or Full amount.
    $cache_id = $this->requestStack->getCurrentRequest()->get('cache_id');
    $payment_type = $this->requestStack->getCurrentRequest()->get('payment_type');
    $payment_type = !($payment_type === 'full');

    // Lets load our calculated object.
    $paymentObject = $this->cacheFactory->get('payment'.$cache_id)->data ?? NULL;
    $pricing = $paymentObject['pricing'] ?? NULL;
    $reservation = $paymentObject['reservation'] ?? NULL;
    $reservation_information = $paymentObject['reservation_information'] ?? NULL;
    $room_node = $paymentObject['room_node'] ?? NULL;
    $room_owner = $paymentObject['room_owner'] ?? NULL;

    if($pricing instanceof Pricing) {

      // Creating redirect url of after payment.
      $redirect = $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost() .
        Url::fromRoute('payment_provider.payment.redirect',['cache_id'=>$cache_id])
          ->toString();
          //dd($redirect);

      // TODO: You can create web book url here and pass to CheckoutLink method.

      // Getting the checkout link of created payment.
      try{
        // This vip user accepts mollie payment, therefore we need the mollie id.
        $authorize_entity = \Drupal::entityTypeManager()->getStorage('oauth_authorize_token')->loadByProperties(['author' => $room_owner->id()]);
        $room_owner_mollie_id = null;
        $is_split = TRUE;
        if($authorize_entity && $authorize_entity = reset($authorize_entity)) {
          // Getting the org_id from mollie
          $room_owner_mollie_id = $authorize_entity->getOrganizationId();
        }
        else {
          $is_split = FALSE;
        }

        $checkout_link = $pricing->getCheckoutLink($payment_type, redirect_endpoint: $redirect, is_split_payment: $is_split,partner_org_id: $room_owner_mollie_id);
        // redirect if all good.
        (new RedirectResponse($checkout_link))->send();
        exit;
      }
        // Catch all error happened during payment create API
      catch (\Throwable $e) {
        \Drupal::logger('payment_provider')->error($e->getMessage());
        $this->messenger->addError('Sorry payment submission failed please contact administrator to assist you.');
      }
    }
    return array(
      '#markup' => "<p>" . $this->t('Sorry something went wrong contact administrator for help') . "</p>",
    );
  }

  /**
   * Controller receiver for redirect url.
   * @return string[]
   * @throws \Mollie\Api\Exceptions\ApiException
   */
  public function paymentStatusRedirect(): array {
    $this->killSwitch->trigger();
    $cache_id = $this->requestStack->getCurrentRequest()->get('cache_id');
    $paymentObject = $this->cacheFactory->get('payment'.$cache_id)->data ?? NULL;
    $pricing = $paymentObject['pricing'] ?? NULL;
    $reservation = $paymentObject['reservation'] ?? NULL;
    $reservation_information = $paymentObject['reservation_information'] ?? NULL;
    $room_node = $paymentObject['room_node'] ?? NULL;
    $room_owner = $paymentObject['room_owner'] ?? NULL;
    $payment_created = $this->cacheFactory->get('payment_provider_created_'.$cache_id)->data ?? NULL;

    if($pricing instanceof Pricing && $payment_created instanceof \Mollie\Api\Resources\Payment
      && $reservation instanceof Reservation && $room_owner instanceof User
      && $room_node instanceof Node) {
      $payment_created = $pricing->justPaymentMade($payment_created->id,true);

      if($payment_created->isPaid()) {

        $reservation_information->set('field_payment_status', 'paid');
        $reservation_information->save();
        // Sending email to location and organizer.
        $location_payment_received = $this->configFactory->get('reservation.notify_email_payment_received')->get('template_mail');
        $organizer_payment_received = $this->configFactory->get('reservation.paid_via_mollie_email')->get('template_mail');
        $token_service = \Drupal::token();

        // Replace tokens
        $location_payment_received = $token_service->replace($location_payment_received,
          [
            'user'=>  $this->currentUser,
            'reservation'=> $reservation,
            'node' => $room_node,
          ]
        );
        $organizer_payment_received = $token_service->replace($organizer_payment_received,
          [
            'user'=>  $this->currentUser,
            'reservation'=> $reservation,
            'node' => $room_node,
          ]
        );

        //Emails
        $mailManager = \Drupal::service('plugin.manager.mail');
        $module = 'zaal_condities';
        $key = 'reservation_mails';
        $langcode = $this->currentUser->getPreferredLangcode();

        // Making sure that organizer has email to received confirmation email.
        if($reservation->getAuthorEmail()) {
          $to = $reservation->getAuthorEmail();
          $params['body'] = Markup::create($organizer_payment_received);
          $params['subject'] = "Payment Received Confirmation";
          $mailManager->mail($module, $key, $to, $langcode, $params, NULL, TRUE);
        }

        // Make sure a room owner has an email to send confirmation email to.
        if($room_owner->getEmail()) {
          $to = $room_owner->getEmail();
          $params['body'] = Markup::create($location_payment_received);
          $params['subject'] = "Mollie payment confirmed";
          $mailManager->mail($module, $key, $to, $langcode, $params, NULL, TRUE);
        }

        try{
          // Creating invoice pdf and sending invoice emails.
          $invoicing = new Invoicing($reservation,$pricing,$payment_created);
          $invoicing->sendInvoices();
          $invoicing->createInvoiceOfPayment();
        }catch (\Throwable $exception){
          \Drupal::logger('payment_provider')->error($exception->getMessage().": ".$exception->getTraceAsString());
        }
        // Clean up caches
        $this->cacheFactory->delete('payment_provider_pricing_'.$cache_id);
        $this->cacheFactory->delete('payment_provider_created_'.$cache_id);
        $this->cacheFactory->delete('reservation_info_'.$cache_id);
        $this->cacheFactory->delete('room_owner_info_'.$cache_id);
        $this->cacheFactory->delete('room_node_info_'.$cache_id);

        $pricing->paymentInvoiceCreation($payment_created,$reservation,$room_node, $room_owner);

        $this->messenger->addMessage("Payment was successful");
        return array(
          '#markup' => "<p class='payment-status'>" . $this->t('Your payment for reservation id '.$reservation->id().', has been successfully received. This payment confirms your reservation. You can find the details of your reservation in your account under the "My Bookings" tab. Thank you') . "</p>",
        );
      }
      elseif ($payment_created->isFailed()) {
        $reservation_information->set('field_payment_status', 'failed');
        $reservation_information->save();
        $pricing->paymentInvoiceCreation($payment_created,$reservation,$room_node, $room_owner);
        $this->messenger->addError("Payment failed please contact administrator to assist you.");
      }
      elseif ($payment_created->isExpired()) {
        $reservation_information->set('field_payment_status', 'expired');
        $reservation_information->save();
        $payment_link = Url::fromRoute("payment_provider.payment.reservation", ['reservation_id' => $reservation->id()])->toString();
        $pricing->paymentInvoiceCreation($payment_created,$reservation,$room_node, $room_owner);
        $this->messenger->addError(Markup::create("Payment expired, please visit this page again to make payment .<a href='$payment_link'>Try again.</a>"));
      }
      elseif ($payment_created->isPending()) {
        $reservation_information->set('field_payment_status', 'pending');
        $reservation_information->save();
        $pricing->paymentInvoiceCreation($payment_created,$reservation,$room_node, $room_owner);
        $this->messenger->addError("Payment is pending, please contact administrator to assist you if you are facing issues.");
      }
      elseif ($payment_created->isCanceled()) {
        $reservation_information->set('field_payment_status', 'canceled');
        $pricing->paymentInvoiceCreation($payment_created,$reservation,$room_node, $room_owner);
        $this->messenger->addMessage("Payment cancelled successfully.");
      }

    }
    return array();
  }

  /**
   * Process the booking amount preview request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   */
  public function reservationAmountPreview(): JsonResponse {
    $request = \Drupal::request();
    $field_received = json_decode($request->getContent(), true);

    // Validate received data.
    $required_fields = array(
      'field_bezetting[0][value]',
      'field_date_booking[0][duration]',
      'room_service'
    );

    foreach ($field_received as $key => $value) {
      if(in_array($value['name'], $required_fields)) {
        if(empty($value['value'])) {
          return new JsonResponse(['error' => 'required field is empty'], 404);
        }
      }
    }

    $room_service = array_filter($field_received, function($value) {
      return $value['name'] == 'room_service';
    });
    $per_cal = array_filter($field_received, function($value) {
      return $value['name'] == 'field_per_person[value]';
    });
    $durations = array_filter($field_received, function($value) {
      return $value['name'] == 'field_date_booking[0][duration]';
    });
    $end_date = array_filter($field_received, function($value) {
      return $value['name'] == 'field_date_booking[0][end_value][date]';
    });
    $start_date = array_filter($field_received, function($value) {
      return $value['name'] == 'field_date_booking[0][value][date]';
    });
    $attend = array_filter($field_received, function($value) {
      return $value['name'] == 'field_bezetting[0][value]';
    });
    $start_time = array_filter($field_received, function($value) {
      return $value['name'] == 'field_date_booking[0][value][time]';
    });
    $end_time = array_filter($field_received, function($value) {
      return $value['name'] == 'field_date_booking[0][end_value][time]';
    });
    $per_hour_cal = array_filter($field_received, function($value) {
      return $value['name'] == 'field_per_hour_calc[value]';
    });
    $additional_services = array_filter($field_received, function($value) {
      return $value['name'] == 'additional';
    });

    if(!empty($durations) && !empty($room_service) && !empty($end_date) && !empty($start_date) && !empty($per_cal) && !empty($attend) && !empty($start_time) && !empty($end_time)) {
      $room_service = reset($room_service);
      $per_cal = reset($per_cal);
      $start_date = reset($start_date);
      $end_date = end($end_date);
      $durations = reset($durations);
      $attend = reset($attend);
      $start_time = reset($start_time);
      $end_time = reset($end_time);
      $per_hour_cal = reset($per_hour_cal);


      //return new JsonResponse([$per_hour_cal]);

      $room = Node::load($room_service['value']);
      $price = [];
      $reports = [];
      $calculated_price = [];
      $additional_services_list = [];
      // Making sure we have zaal node.
      if($room?->bundle() === 'zaal') {

        $room_owner = $room->getOwner();
        $room_type = null;
        $has_mollie = false;

        if($room_owner->hasRole('premium_zaal')) {
          $room_type = 'premium_zaal';
          if($room_owner->get('field_betaling_accepteren_via_mo')?->value === 'ja') {
            $has_mollie = true;
          }
        }
        else {
          $room_type = 'zaal_eigenaar';
          $room_type = true;
        }

        // Prices rules.
        $per_person_price = Pricing::findPatternPricing('i_person', $room->get('field_prijs_eenheid')->getValue())['price'] ?? 0;
        $per_hour_price = Pricing::findPatternPricing('per_hour', $room->get('field_prijs_eenheid')->getValue())['price'] ?? 0;
        $per_day_price = Pricing::findPatternPricing('inan_day', $room->get('field_prijs_eenheid')->getValue())['price'] ?? 0;

        // Convert to an actual price figure
        $per_person_price = !empty($per_person_price) ? $per_person_price / 100 : $per_day_price;
        $per_hour_price = !empty($per_hour_price) ? $per_hour_price / 100 : $per_hour_price;
        $per_day_price = !empty($per_day_price) ? $per_day_price / 100 : $per_day_price;

        // Extracting required values from reservation and node.
        $timing_info = array(
          "value" => strtotime($start_date['value'].' '.$start_time['value']),
          "end_value" => strtotime($end_date['value'].' '.$end_time['value']),
          "duration" => $durations['value'] === 'custom' ? 1439 : $durations['value'],
        );

        if($durations['value'] === 'custom') {
          $timing_info['duration'] = Pricing::calculateMinutesTimestamps($timing_info['value'],$timing_info['end_value']);
        }

        // fields to determine calculations
        // 1. field_per_person
        // 2. field_date_booking

        $is_per_person = $per_cal['value'] ?? NULL;
        $is_per_hour = $per_hour_cal['value'] ?? NULL;

        // The $is_per_person is set regardless that per day in $timing_info is set this
        // should be overridden and per-person calculate should happen.
        $attendees_total = (int) $attend['value'] ?? NULL;
        $hours_to_occupy_room = is_float(((int) $timing_info['duration']) / 60) ? ceil(((int) $timing_info['duration']) / 60) : ((int) $timing_info['duration']) / 60;

        if(!empty($is_per_person)) {
          // Checking if we have person price
          $person_price = Pricing::personPriceRule($attendees_total, $room_service['value']);
          $per_person_price = $person_price !== null ? $person_price/100 : $per_person_price;
          if($per_person_price) {
            $price = array(
              'amount' => $per_person_price * $attendees_total,
              'pattern' => 'field_per_person',
            );
            $reports = array(
              'price' => $per_person_price * $attendees_total,
              'message' => 'Total calculated using per person price of '.$per_person_price,
            );
          }
          else {
            $price = array(
              'amount' => $per_hour_price,
              'pattern' => 'field_uurprijs',
            );
            $reports = array(
              'price' => $per_hour_price * $hours_to_occupy_room,
              'message' => 'Total calculated using per day price of '.$per_hour_price. '. Note we are aware you chose per person unfortunately we dont have  per person price rules',
            );
          }
        }

        elseif($is_per_hour) {
          // Finding per hour price.
          if($per_hour_price && $durations['value'] !== 'custom') {
            $price = array(
              'amount' => $per_hour_price * $hours_to_occupy_room,
              'pattern' => 'field_uurprijs',
            );
            $reports = array(
              'price' => $per_hour_price * $hours_to_occupy_room,
              'message' => 'Total calculated by per hour pricing rule of '.$per_hour_price
            );
          }
          elseif($per_hour_price && $durations['value'] === 'custom' && $hours_to_occupy_room <= 24){
            $price = array(
              'amount' => $per_hour_price * $hours_to_occupy_room,
              'pattern' => 'field_uurprijs',
            );
            $reports = array(
              'price' => $per_hour_price * $hours_to_occupy_room,
              'message' => 'Total calculated by per hour pricing rule of '.$per_hour_price
            );
          }
          else {
            // So if hourly is not set, then go for per day
            if($per_day_price && $durations['value'] === 'custom') {
              $days = Pricing::calculateDaysBetweenTimestamps((int) $timing_info['value'],(int)$timing_info['end_value']);
              $price = array(
                'amount' => $per_day_price * $days,
                'pattern' => 'field_dagprijs',
              );
              $reports = array(
                'price' => $per_day_price * $days,
                'message' => 'Total is calculated by per day pricing rules of '.$per_day_price
              );
            }
          }
        }

        else {

          // Looking for per-day charges if duration is set more than or as 24 hour.
          if($per_day_price) {
            $days = Pricing::calculateDaysBetweenTimestamps((int) $timing_info['value'],(int)$timing_info['end_value']);
            $hours = Pricing::calculateHoursBetweenTimestamps((int) $timing_info['value'],(int)$timing_info['end_value']);
            if($timing_info['duration'] < 1439) {
              $price = array(
                'amount' => $per_hour_price * $days,
                'pattern' => 'field_uurprijs',
              );
              $reports = array(
                'price' => $per_hour_price * $hours,
                'message' => 'Total is calculated by per hour pricing rule of '.$per_hour_price
              );
            }
            else {
              $price = array(
                'amount' => $per_day_price * $days,
                'pattern' => 'field_dagprijs',
              );
              $reports = array(
                'price' => $per_day_price * $days,
                'message' => 'Total is calculated by per day pricing rule of '.$per_day_price
              );
            }
          }
          else {
            // We don't have per-day then let's default back to per hour
            if($per_hour_price) {
              $price = array(
                'amount' => number_format(($per_hour_price * $hours_to_occupy_room), 2),
                'pattern' => 'field_uurprijs',
              );
              $reports = array(
                'price' => number_format(($per_hour_price * $hours_to_occupy_room), 2),
                'message' => 'Total is calculated by per hour pricing rule of '.$per_hour_price . " for $hours_to_occupy_room as we dont have per day pricing"
              );
            }
          }
        }


        // Now let's calculate the platform fees.
        $calculated_price = Pricing::applyingPlatformFees($price, $room_type,$has_mollie);

        // Check additional services
        if(!empty($additional_services)) {
          $additional_services = reset($additional_services);
          $additional_services = $additional_services['value'];
          $storage_settings = $room->get('field_prijs_eenheid')->getValue();
          if($storage_settings) {
            $storage_settings = array_filter($storage_settings,function($setting){
              return $setting['pattern'] === 'services';
            });

            foreach ($additional_services as $key=>$additional_service) {
              $name = str_replace('_',' ',$key);
              $stored = array_filter($storage_settings,function($setting) use ($name) {
                return $setting['services'] === $name;
              });
              if(!empty($stored)) {
                $stored = reset($stored);
                $additional_services_list['services'][] = ['name'=>$name,'price'=>($stored['price'] / 100)  * intval($additional_service), 'count'=>$additional_service];
                $additional_services_list['total'] += ($stored['price'] / 100) * intval($additional_service);
              }
            }
            if(!empty($additional_services_list)) {
              $reports['price'] += $additional_services_list['total'];
            }
          }
        }
      }

      return new JsonResponse(['prices_cal' => $calculated_price,'prices'=>$price, 'reports'=>$reports, 'additional'=>$additional_services_list], 200);
    }
    return new JsonResponse(['error'=> 'something went wrong'], 404);
  }

}

