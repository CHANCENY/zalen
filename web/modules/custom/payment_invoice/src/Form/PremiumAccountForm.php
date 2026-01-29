<?php

namespace Drupal\payment_invoice\Form;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Mollie\Api\Exceptions\ApiException as MollieApiException;
use Drupal\Core\Entity\EntityStorageException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\room_invoice\Entity\InvoicePayment;
use Drupal\payment_provider\PaymentProviderPluginManager;
use Drupal\payment_provider\Plugin\PaymentProvider\PaymentProviderMollie;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Types\PaymentStatus;
use Mollie\Api\Types\SubscriptionStatus;

/**
 * Class PremiumAccountForm.
 *
 * @package Drupal\payment_invoice\Form
 */
class PremiumAccountForm extends FormBase {

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
   * The connection method.
   * @var string CONNECTION_METHOD
   * Method used to connect to the payment provider to receive the subscription.
   */
  const CONNECTION_METHOD = 'profile';
  /**
   * The connection method for "first".
   * @var string CONNECTION_METHOD_FIRST
   * The method used to connect to the payment provider to receive the first payment.
   */
  const CONNECTION_METHOD_FIRST = 'profile';

  /**
   * The sequence type.
   * @var string SEQUENCE_TYPE
   * Type sequence payment. oneoff first recurring.
   */
  const SEQUENCE_TYPE = 'recurring';

  /**
   * Purpose of the payment.
   * @var string PURPOSE_PAYMENT
   * Any string value. Purpose of payment will allow to sort payments in the database.
   */
  const PURPOSE_PAYMENT = 'VIP account subscription';

  /**
   * Key for UserData object.
   * @var string VIP_STATUS_STORAGE_KEY
   * The key by which the data on the VIP status is found.
   */
  const VIP_STATUS_STORAGE_KEY = 'vip_status';


  /**
   * Form constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   Request stack.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   Event dispatcher.
   * @param \Drupal\payment_provider\PaymentProviderPluginManager $managerPaymentProvider
   *   Mollie connector.
   */
  public function __construct(RequestStack $requestStack, EventDispatcherInterface $eventDispatcher, ?PaymentProviderPluginManager $managerPaymentProvider = NULL) {
    $this->requestStack = $requestStack;
    $this->eventDispatcher = $eventDispatcher;
    $this->managerPaymentProvider = $managerPaymentProvider;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('event_dispatcher'),
      $container->get('plugin.manager.payment_provider')
    );
  }

  /**
   * {@inheritdoc}.
   */
  public function getFormId() {
    $form_id = 'paid_account_form';
    return $form_id;
  }

  /**
   * {@inheritdoc}.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $options = NULL) {

    /** @var \Drupal\Core\Session\AccountProxy $current_user */
    $currentUser = $this->currentUser();
    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = \Drupal::service('renderer');//\Drupal::service('renderer')->render($link),
    $provider = 'Mollie';


    if ($currentUser->hasPermission('administrator')) {
      $form['admin_info'] = ['#type' => 'markup', '#markup' => $this->t('Only sellers can create a subscription.'),];
      //return $form;//add
    };

    $form['user_greeting'] = ['#type' => 'markup', '#markup' => $this->t('Hi @name.', ['@name' => $currentUser->getDisplayName()]),];
    $form['page_description'] = ['#type' => 'html_tag', '#tag' => 'p', '#value' => $this->t('This VIP services page is an additional service for sellers, the functionality of which will help increase profitability, attract more orders and increase the average bill.'),];
    $form['page_info'] = ['#type' => 'html_tag', '#tag' => 'p', '#value' => $this->t('Who can purchase the service:<br>The special offer is available for companies registered on the site and connected to the payment provider "Mollie" with the ability to accept payments.'),];

    $form['pricing_title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $this->t('Pricing.'),
    ];

    // Check if the user has VIP status
    $vip_status = $this->getVipStatus($currentUser->id());

    if (isset($vip_status) && in_array($vip_status['current_status'], ['new', 'actual', 'pause'])) {

      switch ($vip_status['current_status']) {
        case 'new':
          $actions_pricing = [
            '#type' => 'markup',
            '#markup' => $this->t('The subscription has been created and is awaiting payment. Status subscription: @status.',[
              '@status' => $vip_status['subscription_status'],
            ]),
          ];
          break;
        case 'actual':
          $actions_pricing = [
            '#type' => 'markup',
            '#markup' => $this->t('VIP account is active. Status subscription: @status,<br> next payment date: @next.',[
              '@status' => $vip_status['subscription_status'],
              '@next' => $vip_status['nextPaymentDate'],
            ]),
          ];
          break;
        case 'pause':
          $actions_pricing = [
            '#type' => 'markup',
            '#markup' => $this->t('VIP account suspended, status subscription: @status.',[
              '@status' => $vip_status['subscription_status'],
            ]),
          ];
          break;
      };

    } else if ($form_state->has(['conf_form','can_accept_recur'])) {

      //Let's add a form for creating the first payment if the client does not have valid mandates.
      $can_accept_recur = $form_state->get(['conf_form', 'can_accept_recur']);
      if ($can_accept_recur == 'no_valid_mandate' || $can_accept_recur == 'no_mandate') {
        $actions_mandate['actions'] = array(
          'target_mandate_cent' => array(
            '#type' => 'submit',
            '#name' => 'mandate_cent',
            '#value' => $this->t('Get Mandate @provider 0.01 EUR', ['@provider' => $provider]),
            '#submit' => ['::handlerGetMandate'],
            '#attributes' => ['class' => ['actions_mandate_button'],],
          ),
          'target_mandate_free' => array(
            '#type' => 'submit',
            '#name' => 'mandate_free',
            '#value' => $this->t('Get Mandate @provider 0.00 EUR', ['@provider' => $provider]),
            '#submit' => ['::handlerGetMandate'],
            '#attributes' => ['class' => ['actions_mandate_button'],],
          ),
        );
        $actions_pricing = ['#type' => 'html_tag', '#tag' => 'p', '#value' => $this->t('Not possible to create a subscription without a valid mandate.'),];
      };

    } else {

      $actions_pricing['actions']['#type'] = 'actions';
      $actions_pricing['actions']['submit'] = array(
        '#type' => 'submit',
        '#name' => 'get_vip_basis',
        '#value' => $this->t('Get VIP via @provider', ['@provider' => $provider]),
        '#button_type' => 'primary',
        '#attributes' => ['class' => ['actions_pricing-button'],],
      );

    };

    $form['pricing_table'] = array(
      '#type' => 'table',
      '#caption' => $this->t('Pricing.'),
      '#header' => array(
        $this->t(''),
        $this->t('Standaard'),
        $this->t('VIP'),
      ),
      '#rows' => array(
        array($this->t('Company page'),'✔','✔',),
        array($this->t('Page per room'),'✔','✔',),
        array($this->t('Calendar per room'),'✔','✔',),
        array($this->t('Bookings per room'),$this->t('Commission basis'),$this->t('@sum fixed amount', ['@sum' => '€4.99']),),
        array($this->t('Recording search lists'),'✔','✔',),
        array($this->t('Registered rooms'),'✔','✔',),
        array($this->t('Province halls'),'✔','✔',),
        array($this->t('Additional offers'),'✔','✔',),
        array($this->t('Customer contact'),'🚫','✔',),
        array($this->t('Company video'),'🚫','✔',),
        array($this->t('Carousel recording'),'🚫','✔',),
        array($this->t('Popular venues'),'🚫','✔',),
      ),
      '#empty' => $this->t('There is no data'),
      '#responsive' => FALSE,
      '#sticky' => TRUE,
    );

    $form['pricing_table_description'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('<br>Fixed price @sum regardless of the total sum. No commission is due on direct payments.', ['@sum' => '€4.99']),
    ];

    $form['wrap_mandate'] = [
      '#type' => 'container',
      '#id' => 'wrap-mandate',
      '#attributes' => ['class' => ['mandate',],],
    ];
    if (isset($actions_pricing)) {
      $form['wrap_mandate'] += $actions_pricing;
    };
    if (isset($actions_mandate)) {
      $form['wrap_mandate'] += $actions_mandate;
      $form['wrap_mandate']['mandate_description'] = [
        '#type' => 'markup',
        '#markup' => $this->t('Free for credit card and PayPal payments only. Otherwise for any payments methods.'),
      ];
    };

    $form['extra_title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $this->t('Extra service.'),
    ];

    $form['extra_table'] = array(
      '#type' => 'table',
      '#caption' => $this->t('Extra service.'),
      '#header' => array(
        $this->t(''),
        $this->t('Banquet hall'),
        $this->t('Meeting hall'),
        $this->t('Concert hall'),
        $this->t('Commercial'),
      ),
      '#rows' => array(
        array('0','1','2','3','4',),
        array($this->t('Create page. business page format'),'€49','€49','€49','€49',
        ),
      ),
      '#empty' => $this->t('There is no data'),
      '#responsive' => FALSE,
      '#sticky' => TRUE,
    );

    $form['extra_table_description'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Extra price @sum description...', ['@sum' => '€4.99']),
    ];

    return $form;
  }

  /** {@inheritdoc} */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    //if ($this->currentUser()->isAnonymous()) {
    //  $form_state->setErrorByName('get_vip_basis', $this->t('Only registered users can get VIP status.'));
    //}
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    if ($form_state->getTriggeringElement()['#name'] !== 'get_vip_basis') {return;};

    /** @var \Drupal\Core\Session\AccountProxy $current_user */
    $currentUser = $this->currentUser();

    try {

      // Let's check have user pending payments for vip account.
      // Which have the status of new. And do not have the status of paid expired or another response from the payment provider.
      // Which means that this payment stream is open and it makes no sense to open a new one until the previous one ends.
      $invoices = $this->getInvoiceWithStatus(
        ['target_type_order' => 'user', 'attendees' => $currentUser->id(), 'purpose_payment' => 'VIP account subscription',],
        [InvoicePayment::STATUS_NEW_INVOIS,]
      );

      /** @var \Drupal\payment_provider\Plugin\PaymentProvider\PaymentProviderMollie
       * $molliePaymentProvider The payment provider Mollie object. */
      $molliePaymentProvider = $this->managerPaymentProvider->createInstance($this->providerId);

      if (is_array($invoices)) {

        // There is a started and an unfinished payment.
        // Need to check its status with the payment provider.
        // And then there will be no refunds.
        /** @var \Drupal\room_invoice\Entity\InvoicePayment $invoice */
        $invoice = $invoices[InvoicePayment::STATUS_NEW_INVOIS][0];
        /** @var \Mollie\Api\Resources\Subscription|null $check_subscription */
        if ($check_subscription = $molliePaymentProvider->checkSubscriptionStatus($invoice->payment_flows, $currentUser->id())) {
          if ($check_subscription->isActive()) {
            $invoice->setPaymentStatus($check_subscription->status);
            $invoice->setStreamStep($check_subscription->status . '<br>nextPaymentDate: ' . $check_subscription->nextPaymentDate);
            $invoice->setCurrentStep($invoice->getCurrentStep() + 1);
            $invoice->save();
            $this->messenger()->addWarning($this->t('The VIP status was started to be created earlier still is Active. Invoice:@id.',
            ['@id' => $invoice->id()]));
            return;
          } else if ($invoice->getChangedTime() + 3600 < (new DrupalDateTime)->getTimestamp()) {
            $this->messenger()->addWarning($this->t('The VIP status was started to be created recently still is not finished. Invoice:@id. Let\'s wait for a response from the payment provider',
            ['@id' => $invoice->id()]));
            return;
          };
        } else {
          $this->messenger()->addWarning($this->t('The VIP status was started to be created earlier still is not finished. Invoice:@id. We continued its creation without creating a new one.',
          ['@id' => $invoice->id()]));
        };
      };

      // Let's check if the user can pay for the VIP account through the API of the payment provider.
      if ($this->providerId == 'mollie') {
        // Recursive Mollie payments require a costumer with a valid mandate.
        $can_recurring = $molliePaymentProvider->canRecurringPayments($currentUser->id(), static::CONNECTION_METHOD);
        if (is_string($can_recurring)) {
          $this->messenger()->addError('At this stage, it was not possible to subscribe to a VIP account');
          $form_state->set('conf_form', ['can_accept_recur' => $can_recurring])->setRebuild(true);
          return;
        };
      };

      // Fill in the data for the subscription
      $data = array(
        'amount' => array(
          'currency' => 'EUR',
          'value' => '4.99',
        ),
        'interval' => '1 month',
        'startDate' => (new DrupalDateTime)->format('Y-m-d'),
        'description' => 'VIP account subscription',
        'metadata' => array(
          'user_id' => $currentUser->id(),
          'connection_method' => static::CONNECTION_METHOD,
          'invoice_id' => '(N/A)',
        ),
      );

      // If there is no invoice, create a new one
      if (empty($invoice)) {
        $invoice = InvoicePayment::create(
          array(
            'title' => 'Subscribe VIP user (ID'.$currentUser->id().') account',
            'date' => (new DrupalDateTime)->getTimestamp(),
            'recipient' => '1',
            'target_type_order' => 'user',
            'attendees' => $currentUser->id(),
            'money' => $data['amount']['value'],
            'currency' => $data['amount']['currency'],
            'payment_method' => $this->providerId . '-subscription',
            'connection_method' => static::CONNECTION_METHOD,
            'payment_mode' => $molliePaymentProvider->getValidator()->useTestMode() ? 'test' : 'live',
            'payment_status' => ['date'=>(new DrupalDateTime)->getTimestamp(),'meaning'=>InvoicePayment::STATUS_NEW_INVOIS],
            'sequence_type' => static::SEQUENCE_TYPE,
            'current_step' => '0',
            'streams_line_pitch' => 'invoice was created before creating payment flow',
            'purpose_payment' => static::PURPOSE_PAYMENT,
          )
        );

        // If the entity is new, we need validate.
        if ($valid = $invoice->invokeInvoiceValidation($invoice)) {return;};

      } else {
        // If an invoice already exists, we will update the price and payment method that may have changed.
        $invoice
        ->setAmountValue($data['amount']['value'] * 100)
        ->setPaymentCurrency($data['amount']['currency'])
        ->setPaymentMethod($this->providerId . '-subscription', $molliePaymentProvider->getValidator()->useTestMode() ? 'test' : 'live');
      };

      // Save invoice for get storage id. I think it should be included in the payment subscription.
      $invoice->save();
      $data['metadata']['invoice_id'] = $invoice->id();

      // Create a Subscription
      if (!$molliePaymentProvider->withPaymentClient(static::CONNECTION_METHOD)) {
        $this->getLogger('payment_invoice')->error('While subscribing to a VIP account by user:@id failed to create a payment client.', [
          '@id' => $currentUser->id(),
        ]);
        $this->messenger()->addError('Failed to create payment client.');
      };

      /** @var \Mollie\Api\Resources\Subscription $subscription */
      $subscription = $molliePaymentProvider->payWithClient('subscription', $data, [], $currentUser->id());
      if (!$subscription) {
        $this->getLogger('payment_invoice')->error('Failed to create subscription.');
        $this->messenger()->addError('Failed to create subscription.');
        return;
      } else if ($subscription->status !== 'active') {
        $this->getLogger('payment_invoice')->error('Subscription created for user:@id has status @status not "@active".', [
          '@id' => $currentUser->id(),
          '@status' => $subscription->status,
          '@active' => 'active',
        ]);
        $this->messenger()->addError('Subscription created has status @status.', [
          '@status' => $subscription->status,
        ]);
      };

      // Let's create or update the invoice.
      $invoice
      ->setCustomersId($subscription->customerId)
      ->setPaymentFlowsId($subscription->id)
      ->setStatusFlow($subscription->status)
      ->setStreamStep('invoice and payment flow created');

      // For the description, we first need to check the allowed formats for the current user
      $allowed_formats = $invoice->getDescriptionAllowedFormats($this->currentUser());
      $allowed_formats = in_array('basic_html', $allowed_formats) ? 'basic_html' : current($allowed_formats);
      $allowed_description = array(
        'currentUser_id' => $currentUser->id(),
        'connection_method' => static::CONNECTION_METHOD,
        'subscription_id' => $subscription->id,
        'customerId' => $subscription->customerId,
        'mandateId' => $subscription->mandateId ?: '(N/A)',
        'subscription_mode' => $subscription->mode,
        'createdAt' => $subscription->createdAt ?: '(N/A)',
        'status' => $subscription->status . ':' . InvoicePayment::STATUS_NEW_INVOIS,
        'times' => $subscription->times ?: '(N/A)',
        'timesRemaining' => $subscription->timesRemaining ?: '(N/A)',
        'interval' => $subscription->interval,
        'startDate' => $subscription->startDate ?: '(N/A)',
        'nextPaymentDate' => $subscription->nextPaymentDate ?: '(N/A)',
        'method' => $subscription->method ?: '(N/A)',
        'description' => $subscription->description,
      );
      $invoice->setDescriptionFromArray($allowed_description, $allowed_formats);

      // Finish saving the invoice
      $invoice->save();

      // Mark VIP status as created
      if (!$this->setVipStatus(
        $currentUser->id(),
        [
          'current_status' => 'new',
          'last_invois' => $invoice->id(),
          'subscription_id' => $subscription->id,
          'subscription_status' => $subscription->status,
          'mode' => $subscription->mode,
          'createdAt' => $subscription->createdAt,
          'paidAt' => '',
          'interval' => '1 month',
          'nextPaymentDate' => $subscription->nextPaymentDate,
        ]
      )) {
        $this->getLogger('payment_invoice')->error('Error recording VIP status, invoice: "'.$invoice->id().'".');
      };

      return;

    } catch (EntityStorageException $e) {
      $this->getLogger('payment_invoice')->error('Entity call failed: ' . htmlspecialchars($e->getMessage()) . '<br>' . $e->getTraceAsString());
    } catch (PluginNotFoundException | InvalidPluginDefinitionException $e) {
      $this->getLogger('payment_invoice')->error('Plugin call failed: ' . htmlspecialchars($e->getMessage()) . '<br>' . $e->getTraceAsString());
    } catch (MollieApiException $e) {
      $this->getLogger('payment_invoice')->error('API Mollie call failed: ' . htmlspecialchars($e->getMessage()) . '<br>' . $e->getTraceAsString());
    };

    return;
  }

  /**
   * Handler for receiving the first payment, the customer's mandate.
   */
  public function handlerGetMandate(array &$form, FormStateInterface $form_state) {

    $button = $form_state->getTriggeringElement();
    // Set the price and settings depending on the pressed button
    switch ($button['#name']) {
      case 'mandate_cent':
        $amount = '0.01';
        break;
      case 'mandate_free':
        $amount = '0.00';
        break;
      default:
        $this->getLogger('payment_provider')->error('When creating the first payment, a button with an unknown "#name" was pressed.');
        return;
    };

    /** @var \Drupal\payment_provider\Plugin\PaymentProvider\PaymentProviderMollie $molliePaymentProvider The payment provider Mollie object. */
    $molliePaymentProvider = $this->managerPaymentProvider->createInstance($this->providerId);

    /** @var \Drupal\Core\Routing\TrustedRedirectResponse $response */

    $response = $molliePaymentProvider->getRedirectCustomerFirstPayment(
      $amount,
      'For create VIP subscription',
      $this->currentUser()->id(),
      static::CONNECTION_METHOD_FIRST,
      'payment',
    );
    if ($response) {
      // Redirect to Mollie.
      $form_state->setResponse($response);
    };
    return;

  }

  /**
   * Get the user VIP status.
   * - (recommendation) Check by key "current" Possible values ["new", "actual", "pause" "expire", "was_no"].
   * @param string|int $user_id User ID.
   * @return array|null Returns array with data on success.
   */
  public function getVipStatus(string $user_id): ?array {

    /** @var \Drupal\user\UserData $userDdata */
    $userDdata = \Drupal::service('user.data');
    $values = $userDdata->get('vip_data', $user_id, static::VIP_STATUS_STORAGE_KEY);

    // We will return the data if it is inactive and will not be updated
    if ($values == null) {
      $values['current_status'] = 'was_no';
      return $values;
    } else if (isset($values['current_status']) && in_array($values['current_status'], ['expire', 'was_no'])) {
      return $values;
    };

    // We will return the data if they are relevant
    if (!empty($values['paidAt']) && $paidAt = strtotime($values['paidAt'])) {
      if (!empty($values['nextPaymentDate']) && $nextPaymentDate = strtotime($values['nextPaymentDate'])) {
        if (new DrupalDateTime < DrupalDateTime::createFromTimestamp($nextPaymentDate)) {
          return $values;
        };
      } else if (!empty($values['interval'])) {
        if ((new DrupalDateTime)->modify('+'.$values['interval']) < DrupalDateTime::createFromTimestamp($paidAt)) {
          return $values;
        };
      };
    };

    // Check if there is a new invoice.
    $need_update = false;

    // We should expect updates in invoices only if the subscription has one of the statuses (Pending, Active, Suspended).
    if (!empty($values['subscription_status']) && !in_array($values['subscription_status'], ['canceled', 'completed'])) {
      $invoices = InvoicePayment::loadLastInvoiceByProperties(['target_type_order' => 'user', 'attendees' => $user_id, 'purpose_payment' => static::PURPOSE_PAYMENT]);

      if (is_array($invoices) && count($invoices) > 0) {
        /** @var \Drupal\room_invoice\Entity\InvoicePayment $invoice */
        $invoice = current($invoices);

        if (!$values || $values['last_invois'] < $invoice->id()) {
          $value_new = ['last_invois' => $invoice->id(),];

          if ($invoice->getPaymentStatus() == 'paid') {
            $description = $invoice->getDescriptionToArray();
            if (isset($description['paidAt'])) {$value_new['paidAt'] = $description['paidAt'];};
          } else {
            $value_new = [
              'paidAt' => '',
              'subscription_status' => $invoice->getStatusFlow(),
            ];
            $description = $invoice->getDescriptionToArray();
            if (isset($description['interval'])) {$value_new['interval'] = $description['interval'];};
            if (isset($description['nextPaymentDate'])) {$value_new['nextPaymentDate'] = $description['nextPaymentDate'];};
          };

          unset($values['current_status']);
          $values = array_merge($values, $value_new);
          $need_update = true;
        };

      };

    };

    // Set current VIP status. ("new", "actual", "pause" "expire", "was_no")
    if (!empty($values['paidAt']) && $paidAt = strtotime($values['paidAt'])) {

      if (!empty($values['nextPaymentDate']) && $nextPaymentDate = strtotime($values['nextPaymentDate'])) {
        if (new DrupalDateTime < DrupalDateTime::createFromTimestamp($nextPaymentDate)) {
          $update_status = 'actual';
        } else {
          $update_status = 'pause';
        };
      } else if (!empty($values['interval'])) {
        if ((new DrupalDateTime)->modify('+'.$values['interval']) < DrupalDateTime::createFromTimestamp($paidAt)) {
          $update_status = 'actual';
        } else {
          $update_status = 'pause';
        };
      };

      // If the subscription is no longer renewed and the VIP status has expired.
      if (in_array($values['subscription_status'], ['canceled', 'completed']) && $update_status = 'pause') {
        $update_status = 'expire';
      };

    } else if (!empty($values['nextPaymentDate']) && $nextPaymentDate = strtotime($values['nextPaymentDate'])) {

      if (new DrupalDateTime < DrupalDateTime::createFromTimestamp($nextPaymentDate)) {
        $update_status = 'actual';
      } else if (in_array($values['subscription_status'], ['pending', 'suspended'])) {
        $update_status = 'pause';
      } else if (in_array($values['subscription_status'], ['canceled', 'completed'])) {
        $update_status = 'expire';
      };

    };

    if (empty($values['current_status']) && empty($update_status)) {
      $update_status = 'unknown';
    };

    if (empty($values['current_status']) || $values['current_status'] !== $update_status) {
      $values['current_status'] = $update_status;
      $need_update = true;
    };

    if ($need_update) {
      $this->setVipStatus($user_id, $values);
    };

    return $values;
  }

  /**
   * Searches for statuses in invoices.
   * @param array $properties Array properties for database query.
   * @param array $values Array containing the statuses to look for.
   * If given an empty array ([]) return all entity objects indexed by their ids
   * @param bool $first (optional) Stop at the first element found, TRUE by default.
   * @return array|null
   * Returns array with key status, containing found matches on success, NULL otherwise
   */
  public function getInvoiceWithStatus(array $properties, array $values = [], bool $first = true): ?array {
    /**
     * @var \Drupal\Core\Entity\EntityInterface[] $invoices
     * An array of entity objects indexed by their ids.
     */
    $invoices = \Drupal::entityTypeManager()->getStorage('invoice_payment')->loadByProperties($properties);
    if ($values === []) {return $invoices;};
    $data = [];
    if (is_array($invoices) && count($invoices) > 0) {
      /** @var \Drupal\room_invoice\Entity\InvoicePayment $invoice */
      foreach ($invoices as $invoice) {
        $searches = $invoice->getPaymentStatus();
        foreach ($values as $value) {
          if ($searches == $value) {
            $data[$value][] = $invoice;
            if ($first) {return $data;};
          };
        };
      };
    };
    return empty($data) ? NULL : $data;
  }

  /**
   * Set the user VIP status.
   * - (recommendation) Check by key "current" Possible values ["new", "actual", "expire"].
   * @param string|int $user_id User ID.
   * @param array $values array values to save.
   * @return bool Returns TRUE on success.
   */
  public function setVipStatus(string $user_id, array $values): bool {
    $key = 'vip_status';
    /** @var \Drupal\user\UserData $userDdata */
    $userDdata = \Drupal::service('user.data');
    $userDdata->set('vip_data', $user_id, $key, $values);
    return true;
  }



}
