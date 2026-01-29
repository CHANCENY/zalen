<?php

namespace Drupal\reservation\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class OrganizerNotInstanceConfirmation extends FormBase {

  protected $messenger;
  /**
   * Constructs a new CustomFormExampleForm objects.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger.
   */
  public function __construct(ConfigFactoryInterface $config_factory, MessengerInterface $messenger) {
    $this->configFactory = $config_factory;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): LocationNotificationEmailForm|static {
    return new self(
      $container->get('config.factory'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc }
   */
  public function getFormId(): string {
    return 'not_instance_confirmation_form';
  }

  /**
   *{@inheritdoc }
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    // Loading previous template saved.
    $config = $this->configFactory->get('reservation.not_instance_booking_done_email');
    $template = $config->get('template_mail');
    $format = $config->get('template_mail_format');
    $form['field_wrapper'] = array(
      '#type' => 'details',
      '#title' => $this->t('Not Instance booking confirmation'),
      '#open' => TRUE,
    );
    $form['field_wrapper']['template'] = array(
      '#type' => 'text_format',
      '#title' => $this->t('Email for not instance booking confirmation'),
      '#default_value' => $template,
      '#format' => $format ?? 'basic_html',
      '#required' => TRUE,
    );

    $form['field_wrapper']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc }
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Getting submitted values
    $value = $form_state->getValue('template');
    $config = $this->configFactory->getEditable('reservation.not_instance_booking_done_email');
    if($config->set('template_mail',$value['value'])->save() && $config->set('template_mail_format', $value['format'])->save()) {
      $this->messenger->addMessage($this->t("Email template saved successfully."));
      return;
    }
    $this->messenger->addError($this->t('There was an error saving the email template.'));
  }
}
