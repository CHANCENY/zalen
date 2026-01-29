<?php

namespace Drupal\payment_provider\Form;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Url;
use Drupal\payment_provider\Plugin\Pricing\Pricing;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Form to be used for magnus user to opt for full payment or partial payment.
 * @class StandardPartnerPaymentForm
 */

class StandardPartnerPaymentForm extends FormBase {

  /**
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  private RequestStack $request;

  public function __construct(
    RequestStack $requestStack,
    protected $entityTypeManager,
    protected CacheBackendInterface $cacheFactory,
    MessengerInterface $messenger,
    protected KillSwitch $kill_switch,
  ) {
    $this->messenger = $messenger;
    $this->request = $requestStack;
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
    //      $container->get('event_dispatcher'),
    //      $container->get('plugin.manager.payment_provider')
    );
  }


  /**
   * {@inheritdoc }
   */
  public function getFormId(): string {
    return 'payment_provider_standard_partner_form';
  }

  /**
   * {@inheritdoc }
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $form['field_set_wrapper'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Standard Partner Form'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#prefix' => '<div class="payment-provider-standard-form">',
      '#suffix' => '</div>',
    );
    $form['field_set_wrapper']['select_payment_type'] = array(
      '#type' => 'select',
      '#title' => $this->t('Payment type'),
      '#description' => $this->t('Select full payment if you want to pay full amount.'),
      '#options'=>array(
        'full_payment' => $this->t('Full payment'),
        'partial_payment' => $this->t('Partial payment'),
      ),
    );
    $form['field_set_wrapper']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Continue with payment'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc }
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {

    $reservation_id = $this->request->getCurrentRequest()->get('reservation_id');
    // Getting a reservation node to work with.

    // Load reservation
    $reservation = $this->entityTypeManager->getStorage('reservation')->load($reservation_id);
    $node_room = $this->entityTypeManager->getStorage('node')->load($reservation->get('entity_id')->target_id ?? 0);
    $room_owner = $this->entityTypeManager->getStorage('user')->load($node_room?->getOwnerId());
    $reservationInformation = $reservation?->get('field_reservation_information')->referencedEntities();
    $reservationInformation = reset($reservationInformation);
    if($reservation && $node_room && $node_room->bundle() === 'zaal' && $reservationInformation) {

      // Calculation of breakdowns.
      $pricing = new Pricing($reservation, $node_room,$reservationInformation,'zaal_eigenaar');
      $cache_id = time();

      // Storing break down in cache.
      $payment_type = $form_state->getValue('select_payment_type');
      $payment_type = !($payment_type === 'full_payment');

      // This is redirect url after checkout.
      $redirect = $this->request->getCurrentRequest()->getSchemeAndHttpHost() .
        Url::fromRoute('payment_provider.payment.redirect',['cache_id'=>$cache_id])
          ->toString();

      $this->cacheFactory->set('payment'.$cache_id, [
        'pricing' => $pricing,
        'reservation' => $reservation,
        'reservation_information' => $reservationInformation,
        'room_node' => $node_room,
        'room_owner' => $room_owner,
      ], CacheBackendInterface::CACHE_PERMANENT);

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

        $checkout_link = $pricing->getCheckoutLink($payment_type, $redirect, is_split_payment: $is_split,partner_org_id: $room_owner_mollie_id);
        // redirect if all good.
        $redirect = new RedirectResponse($checkout_link);
        $redirect->send();

      }
        // Catch all error happened during payment create API
      catch (\Throwable $e) {
        \Drupal::logger('payment_provider')->error($e->getMessage());
        $this->messenger->addError('Sorry payment submission failed please contact administrator to assist you.');
      }
    }

  }

}
