<?php

namespace Drupal\payment_provider\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\payment_provider\PaymentProviderPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class MollieConfigForm.
 */
class MollieConfigForm extends ConfigFormBase {

  /**
   * plugin Mollie payment provider.
   * @var \Drupal\payment_provider\PaymentProviderPluginInterface
   */
  protected $molliePaymentProvider;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   * The factory for configuration objects.
   * @param \Drupal\payment_provider\Plugin\PaymentProvider\PaymentProviderMollie $molliePaymentProvider
   * plugin Mollie payment provider
   */
  public function __construct(ConfigFactoryInterface $configFactory, PaymentProviderPluginInterface $molliePaymentProvider) {
    parent::__construct($configFactory);
    $this->molliePaymentProvider = $molliePaymentProvider;
  }
  /** {@inheritdoc} */
  public static function create(ContainerInterface $container) {
    return new static($container->get('config.factory'), $container->get('plugin.manager.payment_provider')->createInstance('mollie'));
  }

  /** {@inheritdoc} */
  public function getFormId() {
    return 'mollie_provider_config_form';
  }

  /** {@inheritdoc} */
  public function getEditableConfigNames() {
    return ['mollie.config'];
  }

  /** {@inheritdoc} */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Code snippet shown on configuration form.
    $codeSnippet = '<code>$key[\'mollie.settings\'] = [<br>
      &nbsp;&nbsp;\'live_key\' => \'live_YouRMollIeLIVeAPIkeY\',<br>
      &nbsp;&nbsp;\'test_key\' => \'test_YouRMollIetEStAPIkeY\',<br>
      &nbsp;&nbsp;\'access_token\' => \'youROrgaNIsatIOnacCEsstOkeN\',<br>
    ];</code>';

    /** @var \Drupal\payment_provider\Plugin\PaymentProvider\PaymentProviderMollie $settings */
    $settings = $this->molliePaymentProvider;
    $config = $settings->getValidator();

    // Check configured keys.
    $checkMarkup = '<p>';
    $checkMarkup .= $this->generateCheckMarkup($config->hasLiveApiKey(), 'Live API key') . '<br>';
    $checkMarkup .= $this->generateCheckMarkup($config->hasTestApiKey(), 'Test API key (optional, unless test mode is enabled)') . '<br>';
    $checkMarkup .= $this->generateCheckMarkup($config->has('access_token'), 'Organisation access token (optional)') . '<br><br>';
    $checkMarkup .= $this->t('You need to configure at least a live API key.') . '</p>';


    $form['account'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Mollie account'),
      'check' => ['#markup' => $checkMarkup,],
      'instruction' => [
        '#markup' => '<p>' . $this->t(
          'For security reasons your Mollie credentials cannot be managed here. Add your Mollie credentials to the payment_provider.key.mollie.api.php file:'
        ) . "</p>$codeSnippet",
      ],
    ];

    if ($config->hasTestApiKey()) {
      $form['test_mode'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Use Mollie in test mode'),
        '#default_value' => $this->config('mollie.config')->get('test_mode') ?? 0,
        '#return_value' => 1,
      ];

      $form['webhook_base_url'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Webhook base URL'),
        //https://docs.mollie.com/reference/v2/payments-api/create-payment
        '#description' => $this->t('Use a service like <a href="https://lornajane.net/posts/2015/test-incoming-webhooks-locally-with-ngrok">ngrok</a> when testing locally. This is not required but Mollie\'s webhook will not work without it when testing on a non public domain. Leave empty to use the domain of your website in test mode.'),
        '#default_value' => $this->config('mollie.config')->get('webhook_base_url') ?? '',
        '#states' => [
          'visible' => [
            ':input[name="test_mode"]' => ['checked' => TRUE],
          ],
        ],
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /** {@inheritdoc} */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('mollie.config')
      ->set('test_mode', $form_state->getValue('test_mode'))
      //->set('webhook_base_url', $form_state->getValue('webhook_base_url'))
      ->save();
  }

  /**
   * Returns markup for a config check.
   * @param bool $hasSetting
   *   True if the config exists, false otherwise.
   * @param string $title
   *   Title to display for the config.
   * @return string
   *   Markup for the config check.
   */
  protected function generateCheckMarkup(bool $hasSetting, string $title): string {
    $markup = $hasSetting ? '&#10003;&nbsp;' : '&#10007;&nbsp;';

    if ($hasSetting) {
      $markup .= $this->t('@title is configured.', ['@title' => $title]);
    }
    else {
      $markup .= $this->t('@title is not configured.', ['@title' => $title]);
    }

    return $markup;
  }

}
