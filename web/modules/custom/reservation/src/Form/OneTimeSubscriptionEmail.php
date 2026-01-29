<?php

namespace Drupal\reservation\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class OneTimeSubscriptionEmail extends FormBase {

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

  public function getFormId() {
    return "one_time_subscription_email";
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    // Loading previous template saved.
    $config = $this->configFactory?->get('reservation.one_time_subscription_email');
    $template = $config?->get('template_mail');
    $format = $config?->get('template_mail_format');
    $form['field_wrapper'] = array(
      '#type' => 'details',
      '#title' => $this->t('Invoice Email to location'),
      '#open' => FALSE,
    );
    $form['field_wrapper']['template'] = array(
      '#type' => 'text_format',
      '#title' => $this->t('Invoice Email to location'),
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

  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Getting submitted values
    $value = $form_state->getValue('template');
    $config = $this->configFactory->getEditable('reservation.one_time_subscription_email');
    if($config->set('template_mail',$value['value'])->save() && $config->set('template_mail_format', $value['format'])->save()) {
      $this->messenger->addMessage($this->t("Email template saved successfully."));
      return;
    }
    $this->messenger->addError($this->t('There was an error saving the email template.'));
  }

}

