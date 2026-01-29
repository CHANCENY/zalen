<?php

namespace Drupal\payment_provider\Controller;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\payment_invoice\Plugin\Invoicing;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Drupal\payment_provider\PaymentProviderPluginManager;
use Drupal\room_invoice\Entity\InvoicePayment;
use Mollie\Api\Exceptions\ApiException as MollieApiException;

/**
 * Class WebhookController.
 *
 * @package Drupal\payment_provider\Controller
 */
class MollieWebhookController extends ControllerBase
{

  /**
   * Request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The payment plugin manager used by this form..
   *
   * @var \Drupal\payment_provider\PaymentProviderPluginManager $managerPaymentProvider
   */
  protected $managerPaymentProvider;

  /**
   * The ID of the payment provider plugin used in this form.
   * @var string $providerId ID payment provider Mollie.
   */
  protected $providerId = 'mollie';

  /**
   * @see https://docs.mollie.com/overview/webhooks
   * @see https://docs.mollie.com/payments/status-changes Payments
   * @see https://docs.mollie.com/orders/status-changes Orders and order lines
   * @see https://docs.mollie.com/payments/recurring#payments-recurring-subscription-webhooks Subscriptions
   */
  private $status = [
    'payment' => 'Payments API',
    'order' => 'Orders API',
    'subscription' => 'Subscriptions API',
  ];
  protected $mollie; // mollie

  /**
   * RedirectController constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   Request stack.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   Event dispatcher.
   * @param \Drupal\payment_provider\PaymentProviderPluginManager $managerPaymentProvider
   *   Mollie connector.
   */
  public function __construct(
    RequestStack $requestStack,
    EventDispatcherInterface $eventDispatcher,
    PaymentProviderPluginManager $managerPaymentProvider
  ) {
    $this->requestStack = $requestStack;
    $this->eventDispatcher = $eventDispatcher;
    $this->managerPaymentProvider = $managerPaymentProvider;

    $this->mollie = new \Mollie\Api\MollieApiClient();
    $this->mollie->setApiKey("test_wubDmDr9RdmJn3TKrQgHpvxz7Jn9vD");
    $this->mollie->setAccessToken("access_C6n43U46F7gPpw523k2hkGjnDeVKybzbdR2znxUK");

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('request_stack'),
      $container->get('event_dispatcher'),
      $container->get('plugin.manager.payment_provider')
    );
  }

  /**
   * Allows modules to react to payment status updates.
   * This webhook is called by Mollie when the status of a payment changes.
   *
   * @param string $context_id
   *   The ID of the context that requested the payment.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Response that will inform Mollie whether the event was processed correctly.
   */
  public function invokeStatusChangePayments(string $context_id = ''): Response
  {

    $transaction_id = $this->requestStack->getCurrentRequest()->get('id');

    try {

      /** @var \Drupal\payment_provider\Plugin\PaymentProvider\PaymentProviderMollie $molliePaymentProvider The payment provider Mollie object. */
      // $molliePaymentProvider = $this->managerPaymentProvider->createInstance($this->providerId);

      // /**
      //  * @var \Drupal\Core\Entity\EntityInterface[] $invois
      //  * An array of entity objects indexed by their ids.
      //  */
      // $invois = \Drupal::entityTypeManager()->getStorage('invoice_payment')
      //   ->loadByProperties(['transaction_id' => $transaction_id]);

      // if (count($invois) == 1) {
      //   /** @var \Drupal\room_invoice\Entity\InvoicePayment $invois */
      //   $invois = current($invois);
      // } else {
      //   $this->getLogger('payment_invoice')->error('A request was made in the payment webhook for an unknown transaction_id:' . $transaction_id);
      //   return new Response('', 204);
      // }
      // ;

      $payment = $this->mollie->payments->get($transaction_id);

      // if ($invois->getPaymentStatus() !== $payment->status) {
      //   $invois->setPaymentStatus($payment->status);
      //   $invois->save();
      // }
      // ;

      if ($payment->isPaid() && !$payment->hasRefunds() && !$payment->hasChargebacks()) {
        // The payment is paid and isn't refunded or charged back.
        // At this point can start the process of delivering the product to the customer.

        // $payments = \Drupal::entityTypeManager()
        //   ->getStorage('payment')
        //   ->loadByProperties([
        //     'bundle' => 'payment_on_mollie',
        //   ]);
        // // Get the first payment entity.
        // $payment = reset($payments);

        $paymentStatusManager = \Drupal::service('plugin.manager.payment.status');
        $payment->setPaymentStatus($paymentStatusManager->createInstance('payment_success')); //
        $payment->save();

      } elseif ($payment->isOpen()) {
        // The payment is open.
      } elseif ($payment->isPending()) {
        // The payment is pending.
      } elseif ($payment->isFailed()) {
        // The payment has failed.
      } elseif ($payment->isExpired()) {
        // The payment is expired.
      } elseif ($payment->isCanceled()) {
        //The payment has been canceled.
      } elseif ($payment->hasRefunds()) {
        // The payment has been (partially) refunded. The status of the payment is still "paid"
      } elseif ($payment->hasChargebacks()) {
        // The payment has been (partially) charged back. The status of the payment is still "paid"
      }
      ;

      $data = [
        '@tr' => $transaction_id,
        '@mode' => $payment->mode,
        '@status' => $payment->status,
        '@seq' => $payment->sequenceType,
        '@user_id' => $payment->metadata->user_id,
        '@customerId' => $payment->customerId,
        '@des' => $payment->description,
        '@purpose' => $payment->metadata->purpose,
      ];
      $this->getLogger('payment_provider')->notice('Transaction (@tr) in "Subscriptions" webhook. Mode(@mode) Status(@status) sequenceType(@seq) user_id(@user_id) customerId(@customerId) description(@des) Purpose(@purpose)', $data);

      $print = \Drupal\payment_provider\Plugin\PaymentProvider\PaymentProviderMollie::intPrintTest($payment, __FILE__, __FUNCTION__, __LINE__);

    } catch (EntityStorageException $e) {
      $this->getLogger('payment_provider')->error('Entity call failed: ' . $e->getMessage());
    } catch (PluginNotFoundException | InvalidPluginDefinitionException $e) {
      $this->getLogger('payment_provider')->error('Plugin call failed: ' . $e->getMessage());
    } catch (MollieApiException $e) {
      $this->getLogger('payment_provider')->error('API Mollie call failed: ' . htmlspecialchars($e->getMessage()));
      return new Response('', 500);
    }
    ;

    return new Response('', 200);
  }

  /**
   * Allows modules to react to order status updates.
   * This webhook is called by Mollie when the status of a order changes.
   *
   * @param string $context_id
   *   The ID of the context that requested the order.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Response that will inform Mollie whether the event was processed correctly.
   */
  public function invokeStatusChangeOrders(string $context_id = ''): Response
  {

    $transaction_id = $this->requestStack->getCurrentRequest()->get('id');

    try {

      /** @var \Drupal\payment_provider\Plugin\PaymentProvider\PaymentProviderMollie $molliePaymentProvider The payment provider Mollie object. */
      $molliePaymentProvider = $this->managerPaymentProvider->createInstance($this->providerId);
      $client = $molliePaymentProvider->getPaymentAdapter()->getClient();
      $order = $client->orders->get($transaction_id);
      $orderId = $order->id; //ord_kEn1PlbGa

      if ($order->isPaid() || $order->isAuthorized()) {
        //The order is paid or authorized.
        //At this point you'd probably want to start the process of delivering the product to the customer.
      } elseif ($order->isCanceled()) {
        //The order is canceled.
      } elseif ($order->isExpired()) {
        //The order is expired.
      } elseif ($order->isCompleted()) {
        //The order is completed.
      } elseif ($order->isPending()) {
        //The order is pending.
      }
      ;

    } catch (EntityStorageException $e) {
      $this->getLogger('payment_provider')->error('Entity call failed: ' . $e->getMessage() . '<br>' . $e->getTraceAsString());
    } catch (PluginNotFoundException | InvalidPluginDefinitionException $e) {
      $this->getLogger('payment_provider')->error('Plugin call failed: ' . $e->getMessage() . '<br>' . $e->getTraceAsString());
    } catch (MollieApiException $e) {
      $this->getLogger('payment_provider')->error('API Mollie call failed: ' . htmlspecialchars($e->getMessage()) . '<br>' . $e->getTraceAsString());
      return new Response('', 500);
    }
    ;

    $this->getLogger('payment_provider')->error('Unknown transaction: ' . $transaction_id . ' in Orders webhook.');
    return new Response('', 200);
  }

  /**
   * Allows modules to react to subscription status updates.
   * This webhook is called by Mollie when the status of a subscription changes.
   *
   * @param string $context_id
   *   The ID of the context that requested the subscription.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Response that will inform Mollie whether the event was processed correctly.
   */
  public function invokeStatusChangeSubscriptions(string $context_id = ''): Response
  {


    $transaction_id = $this->requestStack->getCurrentRequest()->get('id');

    try {

      /** @var \Drupal\payment_provider\Plugin\PaymentProvider\PaymentProviderMollie $molliePaymentProvider The payment provider Mollie object. */
      $molliePaymentProvider = $this->managerPaymentProvider->createInstance($this->providerId);
      $client = $molliePaymentProvider->getPaymentAdapter()->getClient();

      /** @var \Mollie\Api\Resources\Payment */
      $payment = $client->payments->get($transaction_id);

      // We do not know the transaction number in advance.
      // But we can get the subscription id and find out who the payment belongs to.
      $subscription_id = $payment->subscriptionId;

      /**
       * @var \Drupal\Core\Entity\EntityInterface[] $invoices
       * An array of entity objects indexed by their ids.
       */
      $invoices = InvoicePayment::loadLastInvoiceByProperties(['payment_flows' => $subscription_id]);
      if (!empty($invoices)) {
        /** @var \Drupal\room_invoice\Entity\InvoicePayment $invoice */
        $invoice = current($invoices);
      } else {
        $this->getLogger('payment_invoice')->error('A request was made in the subscriptions webhook for an unknown transaction_id:' .
          $transaction_id . '. subscription_id:' . $subscription_id ?: '(N/A)' . '.');
        return new Response('', 200);
      }
      ;

      if ($invoice->getStreamStep() !== 0) {
        $invoice = $invoice->createDuplicate();
      }
      ;

      $invoice->setAmountValue($payment->amount->value * 100);
      $invoice->setPaymentCurrency($payment->amount->currency);
      $invoice->setPaymentStatus($payment->status);
      $invoice->setTransactionId($transaction_id);
      $invoice->setCurrentStep(strval($invoice->getCurrentStep() + 1));
      $invoice->setStreamStep('change in webhook payment:' . $payment->status);
      $description = $invoice->getDescriptionToArray();
      if (isset($payment->mandateId)) {
        $description['mandateId'] = $payment->mandateId;
      }
      ;
      if (isset($payment->paidAt)) {
        $description['paidAt'] = $payment->paidAt;
      }
      ;
      if (isset($payment->status)) {
        $description['status'] = $payment->status;
      }
      ;
      if (isset($payment->method)) {
        $description['method'] = $payment->method;
      }
      ;

      if (!$payment->isPaid() || ($payment->hasRefunds() || $payment->hasChargebacks())) {
        //$customers = $client->customers->get($invoice->customers_id);
        //$subscription = $customers->getSubscription($subscription_id);
        $subscription = $client->subscriptions->getForId($payment->customerId, $subscription_id);
        $invoice->setStatusFlow($subscription->status);
        if (isset($subscription->times)) {
          $description['times'] = $subscription->times;
        }
        ;
        if (isset($subscription->timesRemaining)) {
          $description['timesRemaining'] = $subscription->timesRemaining;
        }
        ;
        if (isset($subscription->interval)) {
          $description['interval'] = $subscription->interval;
        }
        ;
        if (isset($subscription->nextPaymentDate)) {
          $description['nextPaymentDate'] = $subscription->nextPaymentDate;
        }
        ;
      }
      ;

      $invoice->setDescriptionFromArray($description);
      $invoice->save();

      if ($payment->isPaid() && !$payment->hasRefunds() && !$payment->hasChargebacks()) {
        // The payment is paid and isn't refunded or charged back.
        // At this point can start the process of delivering the product to the customer.
      } elseif ($payment->isOpen()) {
        // The payment is open.
      } elseif ($payment->isPending()) {
        // The payment is pending.
      } elseif ($payment->isFailed()) {
        // The payment has failed.
      } elseif ($payment->isExpired()) {
        // The payment is expired.
      } elseif ($payment->isCanceled()) {
        //The payment has been canceled.
      } elseif ($payment->hasRefunds()) {
        // The payment has been (partially) refunded. The status of the payment is still "paid"
      } elseif ($payment->hasChargebacks()) {
        // The payment has been (partially) charged back. The status of the payment is still "paid"
      }
      ;

      $data = [
        '@tr' => $transaction_id,
        '@mode' => $payment->mode,
        '@status' => $payment->status,
        '@seq' => $payment->sequenceType,
        '@user_id' => $payment->metadata->user_id,
        '@customerId' => $payment->customerId,
        '@des' => $payment->description,
        '@purpose' => $payment->metadata->purpose,
      ];
      $this->getLogger('payment_provider')->notice('Transaction (@tr) in "Subscriptions" webhook. Mode(@mode) Status(@status) sequenceType(@seq) user_id(@user_id) customerId(@customerId) description(@des) Purpose(@purpose)', $data);

      $print = \Drupal\payment_provider\Plugin\PaymentProvider\PaymentProviderMollie::intPrintTest($payment, __FILE__, __FUNCTION__, __LINE__);
      if ($subscription) {
        $print = \Drupal\payment_provider\Plugin\PaymentProvider\PaymentProviderMollie::intPrintTest($subscription, __FILE__, __FUNCTION__, __LINE__);
      }
      ;

    } catch (EntityStorageException $e) {
      $this->getLogger('payment_provider')->error('Entity call failed: ' . $e->getMessage() . '<br>' . $e->getTraceAsString());
    } catch (PluginNotFoundException | InvalidPluginDefinitionException $e) {
      $this->getLogger('payment_provider')->error('Plugin call failed: ' . $e->getMessage() . '<br>' . $e->getTraceAsString());
    } catch (MollieApiException $e) {
      $this->getLogger('payment_provider')->error('API Mollie call failed: ' . htmlspecialchars($e->getMessage()) . '<br>' . $e->getTraceAsString());
      return new Response('', 500);
    }
    ;

    return new Response('', 200);
  }

  /**
   * This webhook is called by Mollie when to react to events that are other.
   * @param string $pay
   * The context by which we determine the type of payment.
   * - Allowed values: "payment" or "order".
   * @param string $context
   * The context by which we determine the purpose of the payment.
   * @return \Symfony\Component\HttpFoundation\Response
   * Response that will inform Mollie whether the event was processed correctly.
   */
  public function invokeOtherEvents(string $pay = '', string $context = ''): Response
  {

    $transaction_id = $this->requestStack->getCurrentRequest()->get('id');

    try {

      /** @var \Drupal\payment_provider\Plugin\PaymentProvider\PaymentProviderMollie $molliePaymentProvider The payment provider Mollie object. */
      $molliePaymentProvider = $this->managerPaymentProvider->createInstance($this->providerId);
      $client = $molliePaymentProvider->getPaymentAdapter()->getClient();


//      dump($context); die();
      if ($context = 'first') {
        if ($pay == 'payment') {
          $payments = $client->payments;
          $payment = $payments->get($transaction_id);
          $purpose = $payment->metadata->purpose;
          if ($purpose === 'VIP Yearly Subscription' && $payment->status === 'paid'){
            $date = date('Y-m-d');
            $one_year_later = date('Y-m-d', strtotime('+1 year', strtotime($date)));
            $user = User::load($payment->metadata->user_id);
            $user->set('roles',['premium_zaal']);
            $user->set('field_vip_abonnement_vervaldatum', $one_year_later);
            $user->set('field_bericht_voor_rolupdate', 'User Role VIP successfully assigned to you.');
            $user->save();
           // Invoicing::oneTimePaymentInvoice($payment);

          } elseif ($purpose === 'Standard User Upgrade' && $payment->status === 'paid'){
            $user = User::load($payment->metadata->user_id);
            $user->set('roles', ['zaal_eigenaar']);
            $user->set('field_bericht_voor_rolupdate', 'User Role Partner successfully assigned to you.');
            $user->save();
          }
          $data = [
            '@tr' => $transaction_id,
            '@mode' => $payment->mode,
            '@status' => $payment->status,
            '@seq' => $payment->sequenceType,
            '@user_id' => $payment->metadata->user_id,
            '@customerId' => $payment->customerId,
            '@des' => $payment->description,
            '@purpose' => $payment->metadata->purpose,
          ];
          $currentDate = (new \Drupal\Core\Datetime\DrupalDateTime)->getTimestamp();

          $invoice = InvoicePayment::create(
            array(
              'title' => 'First payment user (ID' . $payment->metadata->user_id . ')',
              'date' => $currentDate,
              'recipient' => '1',
              'author' => $payment->metadata->user_id,
              'target_type_order' => 'user',
              'attendees' => $payment->metadata->user_id,
              'money' => $payment->amount->value,
              'currency' => $payment->amount->currency,
              'payment_method' => $this->providerId . '-first-payment',
              'connection_method' => \Drupal\payment_invoice\Form\PremiumAccountForm::CONNECTION_METHOD,
              'payment_mode' => $payment->mode,
              'payment_status' => ['date' => $currentDate, 'meaning' => $payment->status],
              'transaction_id' => $transaction_id,
              'sequence_type' => $payment->sequenceType,
              'customers_id' => $payment->customerId,
              'purpose_payment' => $purpose,
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

          // Sending invoice for payment.
          try{
            Invoicing::oneTimePaymentInvoice($payment);
          }catch (\Throwable $e){
            \Drupal::logger('payment_provider')->error('Invoice payment failed: ' . $e->getMessage());
          }

          $this->getLogger('payment_provider')->notice('Transaction (@tr) in "Other" webhook. Mode(@mode) Status(@status) sequenceType(@seq) user_id(@user_id) customerId(@customerId) description(@des) Purpose(@purpose)', $data);

          $print = \Drupal\payment_provider\Plugin\PaymentProvider\PaymentProviderMollie::intPrintTest($payment, __FILE__, __FUNCTION__, __LINE__);

        } else if ($pay = 'order') {
          $payments = $client->orders;
        } else {
          $this->getLogger('payment_provider')->error('Unknown transaction: (' . $transaction_id . ') and payment method pay:(' . htmlspecialchars($pay) . ') in Other webhook. context:(' . htmlspecialchars($context) . ').');
        }
        ;

      } else {
        $this->getLogger('payment_provider')->error('Unknown transaction: (' . $transaction_id . ') and context in Other webhook. With parameters - pay:(' . htmlspecialchars($pay) . ') context:(' . htmlspecialchars($context) . ').');
      }

    } catch (EntityStorageException $e) {
      $this->getLogger('payment_provider')->error('Entity call failed: ' . $e->getMessage() . '<br>' . $e->getTraceAsString());
    } catch (PluginNotFoundException | InvalidPluginDefinitionException $e) {
      $this->getLogger('payment_provider')->error('Plugin call failed: ' . $e->getMessage() . '<br>' . $e->getTraceAsString());
    } catch (MollieApiException $e) {
      $this->getLogger('payment_provider')->error('API Mollie call failed: ' . htmlspecialchars($e->getMessage()) . '<br>' . $e->getTraceAsString());
      return new Response('', 500);
    }
    ;

    return new Response('', 200);
  }




}
