<?php

/**
 * @file
 * Contains \Drupal\payment_invoice\Form\PayKey\PayButtonOrderFieldFormatterForm
 */

namespace Drupal\payment_invoice\Form\PayKey;

//use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\FormBase;
//use Drupal\Core\Entity\EntityForm;
//use Drupal\reservation\ReservationTypeForm;
//use Drupal\Core\Entity\EntityTypeManagerInterface;
//use Psr\Log\LoggerInterface;
//use Drupal\reservation\ReservationManagerInterface;
//use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\reservation\ReservationInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Entity\EntityStorageException;
use Mollie\Api\Exceptions\ApiException as MollieApiException;
use Drupal\room_invoice\Entity\InvoicePayment;
use Drupal\Core\Url;

class PayButtonReservationForm extends FormBase {

  /**
   * A initial configuration.
   * Created in PayButtonReservationFieldFormatter.
   * @var array
   */
  protected $initial_configuration;

  /**
   * A reservation instance.
   * Contains a reservation object.
   * @var \Drupal\reservation\ReservationInterface
   */
  protected $reservation;

  /**
   * Assignment of the payment in the format of an arbitrary string.
   * Which will make it possible to sort payments of this type..
   * @var string
   */
  const PURPOSE_PAYMENT = 'booking reservation';

  /**
   * Constructs a new form confirm the order in reservation.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param \Drupal\reservation\ReservationInterface $reservation
   *   The reservation object.
   */
  public function __construct(array $configuration, ReservationInterface $reservation) {
    $this->initial_configuration = $configuration;
    $this->reservation = $reservation;
  }

  /**
   * {@inheritdoc}.
   */
  public function getFormId() {
    $form_id = 'pay_' . $this->reservation->getEntityTypeId();
    if ($this->reservation->getEntityType()->hasKey('bundle')) {
      $form_id .= '_' . $this->reservation->bundle();
    }
    $form_id .= '_' . $this->reservation->id();
    return $form_id;
  }

  /**
   * {@inheritdoc}.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $options = NULL) {

    if (!$this->isCorrectUser()) {return [];};

    if (empty($this->reservation->get($this->initial_configuration['field'])->getValue()) &&
    $this->initial_configuration['currentUserID'] == $this->initial_configuration['ownerID']) {
      $form['actions']['#type'] = 'actions';
      $form['actions']['submit'] = array('#type' => 'submit', '#value' => $this->t('Confirm the order on: @cost @currency', [
        '@cost' => $this->initial_configuration['price_to_pay']['cost'],
        '@currency' => $this->initial_configuration['price_to_pay']['currency'],
      ]),);
    } else if ($this->initial_configuration['currentUserID'] == $this->initial_configuration['ownerID'] ||
    $this->initial_configuration['currentUserID'] == $this->initial_configuration['customerID']) {
      $form['actions']['#type'] = 'actions';
      $form['actions']['submit'] = array('#type' => 'submit', '#value' => $this->t('Pay now (@cost @currency)', [
        '@cost' => $this->initial_configuration['price_to_pay']['cost'],
        '@currency' => $this->initial_configuration['price_to_pay']['currency'],
      ]),);
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($triggering_element = $form_state->getTriggeringElement()) {
      if ($this->currentUser->id() !== $this->initial_configuration['currentUserID']) {
        $button_name = $triggering_element['#name'] ?? 'op';
        $form_state->setErrorByName($button_name, $this->t('You don\'t have enough rights.'));
      };
    };
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    
    // Just in case. Check the current user.
    if (!$this->isCorrectUser()) {return;};

    // Processing the button if the current user is the owner.
    if ($this->initial_configuration['ownerID']) {

      if (empty($this->reservation->get($this->initial_configuration['field'])->getValue())) {
        // Add approval time for order in timestamp format
        $current_time = strval((new \Drupal\Core\Datetime\DrupalDateTime())->getTimestamp());
        $this->reservation->set($this->initial_configuration['field'],$current_time,FALSE)->save();
        $this->messenger()->addStatus($this->t('Thank you for @name, status reservation @number has been updated', array(
          '@name' => $this->reservation->getAuthorName(),
          '@number' => $this->reservation->id() . ' "' . $this->reservation->getSubject() . '"',
        )));
      } else {
        $this->messenger()->addWarning($this->t('Sorry @name, but the reservation status @number is already confirmed', array(
          '@name' => $this->reservation->getAuthorName(),
          '@number' => $this->reservation->id() . ' "' . $this->reservation->getSubject() . '"',
        )));
      };

      // Handle the button if the current user is a client (customer).
    } else if ($this->initial_configuration['customerID']) {

      // Selected Payment provider
      // Let's check if the selected payment provider is available.
      $managerPaymentProvider = \Drupal::service('plugin.manager.payment_provider');
      if (!$managerPaymentProvider->hasDefinition($this->initial_configuration['payment_settings']['payment_provider'])) {
        $this->logger('payment_invoice')->warning('An error occurred while clicking the "Pay" button. Payment provider ID:"@id" unavailable.', [
          '@id' => $this->initial_configuration['payment_settings']['payment_provider'],
          ]);
        $this->messenger()->addWarning($this->t('The payment provider is not available.'));
        return;
      };
      $pluginPaymentProvider = $managerPaymentProvider->createInstance($this->initial_configuration['payment_settings']['payment_provider']);

      // Preparing data that will be useful for us to create an invoice and a payment request to a payment provider.
      $data = [
        'current_time' => new \Drupal\Core\Datetime\DrupalDateTime,
        'current_user' => $this->currentUser()->id(),
        'room' => $this->reservation->getReservationedEntity(),
        'target_type' => 'reservation',
        'target_ID' => $this->reservation->id(),
        'target_title' => $this->reservation->getSubject(),
        'target_owner' => $this->reservation->getOwnerId(),
      ];
      $data['room_id'] = $data['room']->id();
      $data['beneficiary'] = $data['room']->getOwnerId();
      $data['description'] = [
        'buyer_id' => $data['current_user'],
        'reservation_id' => $data['target_ID'],
        'page_room_id' => $data['room_id'],
        'owner_room_id' => $data['beneficiary'],
      ];

      // Create invois. Let's check if there is an invoice with payment for this reservation.
      // If not, then create it.
      // If there is, then load it (the case when the first payment attempt was unsuccessful).
      /**
       * @var \Drupal\Core\Entity\EntityInterface[] $invois
       * An array of entity objects indexed by their ids.
       * @see https://www.drupal.org/docs/drupal-apis/entity-api/working-with-the-entity-api
       */
      $invois = \Drupal::entityTypeManager()->getStorage('invoice_payment')->loadByProperties(['target_type_order' => 'reservation', 'attendees' => $data['target_ID']]);
      if (empty($invois)) {
        $invois = InvoicePayment::create(
          array(
            'title' => 'Booking in reservation (ID'.$data['target_ID'].')',
            'date' => $data['current_time']->getTimestamp(),
            'recipient' => $data['beneficiary'],
            'target_type_order' => 'reservation',
            'attendees' => $data['target_ID'],
            'money' => $this->initial_configuration['price_to_pay']['total'],
            'currency' => $this->initial_configuration['price_to_pay']['currency'],
            'payment_method' => $this->providerId . '-payment',
            'connection_method' => $this->initial_configuration['payment_settings']['payment_parameter']['authorization'],
            'payment_mode' => $pluginPaymentProvider->getValidator()->useTestMode() ? 'test' : 'live',
            'payment_status' => ['date'=>$data['current_time']->getTimestamp(),'meaning'=>InvoicePayment::STATUS_NEW_INVOIS],
            //'transaction_id' => '', // available later
            'purpose_payment' => static::PURPOSE_PAYMENT,
            //'description' => '', // NEED formats available to anonymous 'restricted_html' 'plain_text'
          )
        );

        // If the entity is new, we will validate it.
        $validate_invois = $invois->invokeInvoiceValidation($invois);
        if ($validate_invois) {
          $this->logger('room_invoice')->error('An error occurred while clicking the "Pay" button. Reservation ID: @reserv.', ['@reserv' => $data['target_ID']]);
          $this->messenger()->addError($this->t('An error occurred while creating payment to the reservation ID: @reserv.', ['@reserv' => $data['target_ID']]));
          return;
        };

      } else {
        $invois = current($invois);
      };

      // Let's update the price (if the invoice was created earlier), it maybe changed.
      /** @var \Drupal\room_invoice\Entity\InvoicePayment $invois */
      $invois
      ->setAmountValue($this->initial_configuration['price_to_pay']['total'])
      ->setPaymentCurrency($this->initial_configuration['price_to_pay']['currency'])
      ->setPaymentMethod(
        $this->initial_configuration['payment_settings']['payment_provider'] . '-' . $this->initial_configuration['payment_settings']['payment_method'],
        $pluginPaymentProvider->getValidator()->useTestMode() ? 'test' : 'live'
      );
      // If the seller does not have VIP status, we will add a commission.
      if (!$this->hasVipStatus($data['beneficiary'])) {
        // If need to add a commission.
        if (isset($this->initial_configuration['payment_settings']['payment_parameter']['payment_config']['applicationFee']['use']) &&
        $this->initial_configuration['payment_settings']['payment_parameter']['payment_config']['applicationFee']['use'] == '0') {
          $commission['value'] = $this->initial_configuration['payment_settings']['payment_parameter']['payment_config']['applicationFee']['amount']['value'];
          $commission['currency'] = $this->initial_configuration['payment_settings']['payment_parameter']['payment_config']['applicationFee']['amount']['currency'];
          $commission['description'] = 'VIP status is not active. Added booking fee.';
          $commission['description'] .= $this->initial_configuration['payment_settings']['payment_parameter']['payment_config']['applicationFee']['description'];
          $data['description']['commission_value'] = $commission['value'];
          $data['description']['commission_currency'] = $commission['currency'];
        };
      };

      // Save invoice for get storage id.
      $invois->save();

      // Creation of a payment through a payment provider.
      if (!$pluginPaymentProvider->withPaymentClient($this->initial_configuration['payment_settings']['payment_parameter']['authorization'], $this->initial_configuration['customerID'])) {
        return;
      };

      if ($this->initial_configuration['payment_settings']['payment_method'] == 'payment') {
        // Preparing payment parameters
        $payment_data = [
          'amount' => [
            'currency' => $this->initial_configuration['price_to_pay']['currency'],
            'value' => strval($this->initial_configuration['price_to_pay']['cost']),
          ],
          'description' => 'Invois No.'.$invois->id().'. Payment for booking "'.$data['target_title'].'".',
          'redirectUrl' => Url::fromRoute(
            'room_invoice.checkout_invoice',
            ['context_id' => $invois->id(),],
            ['https' => $this->getRequest()->getScheme() == 'https' ? TRUE : FALSE, 'absolute' => TRUE,]
          )->toString(),
          'metadata' => ['invoice' => $invois->id(), 'current_user' => $data['current_user'], 'reservation' => $data['target_ID'],],
        ];
        // If include a commission.
        if (isset($commission)) {
          $payment_data['applicationFee'] = array(
            'amount' => [
              'currency' => $commission['currency'],
              'value' => $commission['value'],
            ],
            'description' => $commission['description'],
          );
        };
      } else {
        $this->messenger()->addError($this->t('Booking payment only supports payments.'));
        return;
      };

      try {
        // Create payment via API.
        /** @var \Mollie\Api\Resources\Payment|null $payment */
        $payment = $pluginPaymentProvider->payWithClient(
          $this->initial_configuration['payment_settings']['payment_method'],
          $payment_data,
          $this->initial_configuration['payment_settings']['payment_parameter']
        );
        
        // For text format, we first need to check "allowed_formats" for the current user.
        $allowed_formats = $invois->getDescriptionAllowedFormats($this->currentUser());
        $allowed_formats = in_array('basic_html', $allowed_formats) ? 'basic_html' : current($allowed_formats);
        $data['description']['transaction_id'] = $payment->id;
        $invois->setDescriptionFromArray($data['description'], $allowed_formats);

        // Save invoice with transaction id.
        $invois->setTransactionId($payment->id)->save();

        // Redirect to Mollie.
        $response = new TrustedRedirectResponse($payment->getCheckoutUrl(), '303');
        $form_state->setResponse($response);
      }
      catch (EntityStorageException $e) {
        $this->getLogger('payment_invoice')->error('Entity call failed: ' . $e->getMessage() . '<br>' . $e->getTraceAsString());
      } catch (MollieApiException $e) {
        $this->getLogger('payment_invoice')->error('API Mollie call failed: ' . htmlspecialchars($e->getMessage()) . '<br>' . $e->getTraceAsString());
      };

    };

    return;
  }

  /**
   * Helper function. Check the current user.
   * @return bool TRUE if correct current user.
   */
  public function isCorrectUser(): bool {
    if ($this->initial_configuration['currentUserID'] == $this->currentUser()->id()) {
      return TRUE;
    };
    return FALSE;
  }

  /**
   * Helper function. Check Vip status for user.
   * @param string|int $user_id User ID.
   * @return bool TRUE if has Vip user.
   */
  public function hasVipStatus($user_id): bool {
    /** @var \Drupal\user\UserData $userDdata */
    $userDdata = \Drupal::service('user.data');
    // Possible values ["new", "actual", "pause" "expire", "was_no"]
    $values = $userDdata->get('vip_data', $user_id, \Drupal\payment_invoice\Form\PremiumAccountForm::VIP_STATUS_STORAGE_KEY);
    if (isset($values) && $values['current_status'] = 'actual') {
      return TRUE;
    };
    return FALSE;
  }



}
