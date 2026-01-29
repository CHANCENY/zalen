<?php declare(strict_types = 1);

namespace Drupal\zaal_condities\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\FormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\payment_provider\PaymentProviderPluginManager;

/**
 * Provides a zaal_condities form.
 */
final class CapacitySelectionForm extends FormBase {

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
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'zaal_condities_capacity_selection';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $form['notice'] = [
      '#markup' => '<div class="messages messages--warning">This is a yearly subscription, and once it expires, you should need to renew it.</div><br>',
    ];

    $form['markup'] = [
      '#markup' => $this->t("Upgrade to VIP Membership to unlock exclusive privileges and enjoy a seamless
      experience on our events room booking platform. By becoming a VIP Member, you gain all the benefits of our
      Standard Membership, including the ability to add locations, rooms, and manage reservations effortlessly.
      Additionally, as a VIP Member, you have the exclusive advantage of bypassing platform commission fees when
      accepting payments directly from your guests for bookings made through our platform. This means you can
      keep 100% of your earnings from bookings processed independently. However, if you choose to utilize our
      platform's payment processing services, a fixed fee of €4.99 will be deducted for each transaction.
      This nominal fee ensures the continued improvement and maintenance of our platform, guaranteeing a reliable and
      efficient booking experience for all users. Upgrade to VIP Membership today to maximize your earning potential
      and elevate your events hosting capabilities to the next level."),
    ];

    $form['location_capacity'] = [
      '#type' => 'select',
      '#title' => $this->t('Select a capacity'),
      '#options' => [
        '99.00'=>'20: €99/year',
        '199.00'=>'21-50: €199/year',
        '299.00'=>'51-100: €299/year',
        '399.00'=>'101-250: €399/year',
        '499.00'=>'251-500: €499/year',
        '799.00'=>'501-1000: €799/year',
        '999.00'=>'+1000: €999/year',
      ],
      '#required' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Submit'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $cap_amount = $form_state->getValue('location_capacity') ?? '99.00';
    /** @var \Drupal\payment_provider\Plugin\PaymentProvider\PaymentProviderMollie $molliePaymentProvider The payment provider Mollie object. */
    $molliePaymentProvider = $this->managerPaymentProvider->createInstance($this->providerId);

    /** @var \Drupal\Core\Routing\TrustedRedirectResponse $response */

    $response = $molliePaymentProvider->getRedirectCustomerPayment(
      $cap_amount,
      'VIP Yearly Subscription',
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

}
