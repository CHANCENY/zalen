<?php declare(strict_types = 1);

namespace Drupal\zaal_condities\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\payment_provider\PaymentProviderPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a zaal_condities form.
 */
final class StandardPaymentForm extends FormBase {

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
  const PURPOSE_PAYMENT = 'Standard User Upgrade';

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
    return 'zaal_condities_standard_payment';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $form['markup'] = [
      '#markup' => $this->t("Upgrade to our Standard Membership for €79 to unlock exclusive privileges tailored to
       enhance your experience within our events room booking platform. As a Standard Member, you gain the ability to
       add locations and rooms, empowering you to showcase your venue offerings to potential guests. Furthermore,
       your upgraded status enables you to manage reservations seamlessly, providing a streamlined booking process for
       events and parties hosted at your venue. The €79 charge supports the ongoing maintenance and development of our
       platform, ensuring a robust and user-friendly experience for all members. Upgrade today to leverage the full
       potential of our platform and elevate your events hosting capabilities.")
    ];

    $form['payment_standard'] = [
      '#type' => 'hidden',
      '#title' => $this->t('Pay €79 for Become Standard'),
      '#required' => TRUE,
      '#default_value' => '79.00',
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Pay €79'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $cap_amount = $form_state->getValue('payment_standard');
    /** @var \Drupal\payment_provider\Plugin\PaymentProvider\PaymentProviderMollie $molliePaymentProvider The payment provider Mollie object. */
    $molliePaymentProvider = $this->managerPaymentProvider->createInstance($this->providerId);

    /** @var \Drupal\Core\Routing\TrustedRedirectResponse $response */

    $response = $molliePaymentProvider->getRedirectCustomerPayment(
      $cap_amount,
      'Standard User Upgrade',
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
