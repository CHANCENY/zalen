<?php

namespace Drupal\payment_provider\Form;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\mailsystem\MailsystemManager;
use Drupal\node\Entity\Node;
use Drupal\reservation\Entity\Reservation;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * This is form is used to get magnus contact details for direct payment.
 * @class VipDirectPaymentContactForm.
 */

class VipDirectPaymentContactForm extends FormBase {

  /**
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  protected AccountProxyInterface $currentUser;

  /**
   * @var \Drupal\mailsystem\MailsystemManager
   */
  private MailsystemManager $mailsystem_manager;

  public function __construct(
    MessengerInterface $messenger,
    RequestStack $requestStack,
    AccountProxyInterface $currentUser,
    MailsystemManager $mailsystem_manager
  ) {
    $this->messenger = $messenger;
    $this->requestStack = $requestStack;
    $this->currentUser = $currentUser;
    $this->mailsystem_manager = $mailsystem_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('messenger'),
      $container->get('request_stack'),
      $container->get('current_user'),
      $container->get('plugin.manager.mail')
    );
  }

  /**
   * {@inheritdoc }
   */
  public function getFormId(): string {
    return 'payment_provider_vip_direct_payment_contact_form';
  }

  /**
   * {@inheritdoc }
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['vip_direct_payment_contact_form'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('VIP direct contact information'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#prefix' => '<div class="vip-direct-payment-contact-form">',
      '#suffix' => '</div>',
    );
    $form['vip_direct_payment_contact_form']['magnus_firstname'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('First name'),
      '#required' => TRUE
    );
    $form['vip_direct_payment_contact_form']['magnus_lastname'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Last name'),
      '#required' => TRUE
    );
    $form['vip_direct_payment_contact_form']['magnus_email_address'] = array(
      '#type' => 'email',
      '#title' => $this->t('Email address'),
      '#required' => TRUE
    );
    $form['vip_direct_payment_contact_form']['magnus_phone'] = array(
      '#type' => 'tel',
      '#title' => $this->t('Phone number'),
      '#required' => TRUE
    );
    $form['vip_direct_payment_contact_form']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc }
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $reservation = Reservation::load($this->requestStack->getCurrentRequest()->get('reservation_id',0));

    if($reservation) {
      $room_id = $reservation->get('entity_id')->getValue()[0]['target_id'] ?? null;
      $node = Node::load($room_id);

      /**@var \Drupal\user\Entity\User $room_owner **/
      $room_owner = $node->getOwner();

      /**@var $reservation_owner \Drupal\user\Entity\User **/
      $reservation_owner = $reservation->getOwner();

      $reservation_url = Url::fromRoute('entity.reservation.canonical', ['reservation'=>$reservation->id()])->toString();
      $reservation_url = trim($this->requestStack->getCurrentRequest()->getSchemeAndHttpHost(), '/') .'/' . trim($reservation_url, '/');

      $contact_info = $form_state->getValues();
      $params['body'] = "<p>
      Hello {$room_owner->getAccountName()}, <br>
      Organizer {$reservation_owner->getAccountName()} has submitted the contact information to get intouch with them for payment process.<br>
      <br>
      <strong>Firstname:&nbsp;</strong> {$contact_info['magnus_firstname']}<br>
      <strong>Lastname:&nbsp;</strong> {$contact_info['magnus_lastname']}<br>
      <strong>Email:&nbsp;</strong> {$contact_info['magnus_email_address']}<br>
      <strong>Phone:&nbsp;</strong> {$contact_info['magnus_phone']}<br>
      <br>
      <strong>NOTE: This is for reservation <a href='{$reservation_url}#reservation-{$reservation->id()}'>{$reservation->getSubject()}</a></strong>
    </p>";
      $params['subject'] = "Organizer Contact Information";
      $to = $room_owner->getEmail();
      $module = 'zaal_condities';
      $key = 'reservation_mails';
      $langcode = $this->currentUser->getPreferredLangcode();
      $result = [];
      if($to) {
        $result = $this->mailsystem_manager->mail($module, $key, $to, $langcode, $params, NULL, TRUE);
      }
      if ($result['result']) {
        // TODO: maybe if required save contact info
        $this->messenger->addMessage("Thank you for your contact information.");
      }else {
        $this->messenger->addError("There was a problem with your submission. Please try again.");
      }
    }
  }

}
