<?php

namespace Drupal\payment_provider\Plugin\PaymentProvider;

use Drupal\payment_provider\Plugin\PaymentProvider\MollieConfigValidator;
use Drupal\payment_provider\Plugin\PaymentProvider\PaymentProviderMollie;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;

/**
 * Class HelperFormSettingsField.
 */
class HelperFormSettingsField {
  use StringTranslationTrait;

  /**
   * The config validator for Mollie payment provider.
   * @var \Drupal\payment_provider\Plugin\PaymentProvider\MollieConfigValidator $mollieConfigValidator
   */
  private $mollieConfigValidator;

  /**
   * The plugin payment provider Mollie being used by this form.
   * @var \Drupal\payment_provider\Plugin\PaymentProvider\PaymentProviderMollie
   */
  protected $molliePaymentProvider;

  /**
   * Construct HelperFormSettingsField.
   * Mollie helper bilder form for field settings button payment.
   * @param \Drupal\payment_provider\Plugin\PaymentProvider\PaymentProviderMollie $molliePaymentProvider
   */
  public function __construct(PaymentProviderMollie $molliePaymentProvider) {
    $this->mollieConfigValidator = new MollieConfigValidator();
    $this->molliePaymentProvider = $molliePaymentProvider;
  }

  /**
   * Mollie Api Key.
   * @return bool
   * Set if.
   */
  public function hasTest(): bool {
    return 0;
  }

  /**
   * Returns an array of the configuration form..
   *
   * @param array $form
   *   The form where the settings form is being included in.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the (entire) configuration form.
   * @param string $selected_method
   *   The Payment method.
   * @param array $init_settings
   *   The Initial settings for form.
   *
   * @return array
   * Form for config the payment method for the field.
   */
  public function getFieldConfigForm(array $form, FormStateInterface $form_state, $selected_method, $init_settings = []): array {

    $provider = $this->molliePaymentProvider;
    $element = [];

    // Reset form data if a new payment method is pressed
    if ($triggering_element = $form_state->getTriggeringElement()) {
      if (in_array('payment_method', $triggering_element['#parents'])) {
        $init_settings = [];
        $parents = array_slice($form_state->getTriggeringElement()['#parents'], 0, -1);
        $reset_values = $form_state->getValue(array_merge($parents, ['payment_parameter',]));
        $reset_values = array_intersect_key($reset_values,array_flip(['authorization',]));
        $form_state->setValue(array_merge($parents, ['payment_parameter',]), $reset_values);
      };
    };

    // Connection method parameters, access
    $element['payment_parameter']['authorization_title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => 'Authorization method.',
    ];
    $element['payment_parameter']['authorization'] = [
      '#type' => 'radios',
      '#title' => $this->t('Select your Mollie authorization method for this payment'),
      '#description' => $this->t('Payment can be made to your account or to the account of your clients using the authorization methods (in this case, you can set a commission fee)'),
      '#multiple' => FALSE,
      '#options' => [
        'profile' => $this->t('API keys - Profile key used. (Pay to your account).'),
      ],
      '#default_value' => $init_settings['authorization'] ?? '',
      '#ajax' => [
        'callback' => [static::class, 'ajax_show_support_oauth'],
        'event' => 'change',
        //'wrapper' => 'method-authorization',
        'method' => 'replaceWith',
        'effect' => 'fade',
        'progress' => ['type' => 'throbber', 'message' => $this->t('Loading...'),],
      ],
    ];
    if (isset($provider) && $provider->isSupportOAuth2()) {
      $element['payment_parameter']['authorization']['#options'] += [
        'organization' => $this->t('Organization access tokens - Organization key via OAuth2 are used. (Pay to your account).'),
        'app' => $this->t('App access tokens - Keys provided by sellers via OAuth2 are used. (Pay to the account of your sellers).'),
      ];
    };

    // Description about delegating payments use oauth2
    $element['payment_parameter']['use_OAuth2'] = [
      '#type' => 'details',
      '#title' => $this->t('About delegating payments use OAuth2.'),
      '#description' => $this->t('
      - <strong>Application fees:</strong></br>
      <b>Enabling application fees.</b></br>
      In order to enable charging application fees with your app, you must first register to become an app developer. This can be done from the Dashboard. Then, <i>contact</i> our support department to have charging application fees on your account enabled.</br>
      <b>Maximum application fees.</b></br>
      <em>in Payments API.</em></br>
      The maximum application fee per payment is the amount of the payment - (1.21 x (0.29 + (0.05 x the amount of the payment))). The minimum is €0.01.</br>
      <em>in Orders API.</em>
      The maximum application fee per payment is 10% of the total amount, up to a maximum of €2.00. If a higher maximum is required for your business, you can request this via Mollie\'s <i>customer service</i> or your account manager at Mollie.</br>
      <b>Recurring</b></br>
      Application fees are both supported on recurring payment and on subscriptions.</br>
      <b>Multicurrency</b></br>
      Application fees are supported on all payments regardless of currency. However, the application fee itself must always be created in EUR. For example, you can charge a €1.00 application fee on a US $10.00 payment.</br>
      - <strong>Splitting payments:</strong></br>
      This feature is currently in closed beta. Please contact our partner management team if you are interested in testing this functionality with us.</br>
      <b>Refunds and chargebacks:</b></br>
      - <b>Refunding a payment with application fees.</b></br>
      When using Application fees, the connected merchant account is in full control of the payment, and any refunds and chargebacks are also processed on their account.</br>
      As a platform, you can create refunds on behalf of the connected account by using the Refunds API with the connected account\'s permission. Refunding previously charged application fees is not possible, however.
      '),
    ];
    $element['payment_parameter']['use_OAuth2']['page'] = [
      '#type' => 'link',
      '#title' => $this->t('Mollie Connect'),
      '#url' => \Drupal\Core\Url::fromUri('https://docs.mollie.com/connect/getting-started', ['query'=>[],'fragment'=>'working-with-access-tokens',]),
      '#attributes' => ['target' => '_blank', 'class'=> 'mollie-button'],
    ];

    // This will handle the first run. Whether the form will be loaded after installing the module.
    // When there are no parameters in the $form_state or settings.
    $form_current_variables = $form_state->getValue(['settings', 'payment_parameter']);
    $form_current_variables = $form_current_variables ?? $init_settings;
    if ($form_current_variables == null) {
      $element['payment_parameter']['payment_config']['info'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('The connection method is not specified for the payment provider.'),
      ];
      return $element['payment_parameter'];
    };

    // We prepare the form depending on selected_method - (payment, order, payment_link, captures, subscription).
    if (!$selected_method) {
      return $element['payment_parameter'];
    } else if ($selected_method == 'payment') {
      //Payments API v2
      //https://docs.mollie.com/reference/v2/payments-api/create-payment
      $element['payment_parameter']['payment_config']['title'] = [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => 'Create payment. Payments API v2. Parameters:',
      ];
      $element['payment_parameter']['payment_config']['use_amount'] = [
        '#type' => 'radios',
        '#title' => $this->t('No.1 How to calculate the price and currency?'),
        '#description' => $this->t('The price can be calculated in code or set statically.<br>
        If calculated in code. It must implement this functionality.'),
        '#multiple' => FALSE,
        '#options' => ['code' => $this->t('Calculate in code.'), 'static' => $this->t('Set statically.'),],
        '#default_value' => $init_settings['payment_parameter']['payment_config']['use_amount'] ?? 'code',
        '#attributes' => [
          'name' => 'use_amount',
        ],
      ];
      //amount:amount object:REQUIRED [currency:string:REQUIRED, value:string:REQUIRED]
      $element['payment_parameter']['payment_config']['amount'] = [
        '#markup' => 'NOTE! The amount object (currency and value) will be set up in the code.',
      ];
      $element['payment_parameter']['payment_config']['amount']['value'] = [
        '#type' => 'number',
        '#title' => $this->t('"value":string:REQUIRED. Enter fees (0.00 - 10 000.00)'),
        '#description' => $this->t(
          'A string containing the exact amount you want to charge in the given currency.<br>
          "+" Make sure to send the right amount of decimals.<br>
          "+" Non-string values are not accepted.'
        ),
        '#default_value' => $init_settings['payment_parameter']['payment_config']['amount']['value'] ?? '0.00',
        '#min' => '0',
        '#max' => '10000',
        '#step' => '0.01',
        '#states' => [
          'disabled' => [':input[name="use_amount"]' => ['value' => 'code'],],
        ],
      ];
      $element['payment_parameter']['payment_config']['amount']['currency'] = [
        '#type' => 'select',
        '#title' => $this->t('"currency":string:REQUIRED. Currency code'),
        '#description' => $this->t(
          'An ISO 4217 currency code.<br>
          "+" The currencies supported depend on the payment methods that are enabled on your account.'
        ),
        '#options' => [
          'EUR' => 'EUR',
          'CHF' => 'CHF',
          'GBP' => 'GBP',
          'NOK' => 'NOK',
        ],
        '#default_value' => $init_settings['payment_parameter']['payment_config']['amount']['currency'] ?? 'EUR',
        '#states' => [
          'disabled' => [':input[name="use_amount"]' => ['value' => 'code'],],
        ],
      ];
      $element['payment_parameter']['payment_config']['use_description'] = [
        '#type' => 'radios',
        '#title' => $this->t('No.2 How to generate a payment description?'),
        '#description' => $this->t('The maximum length is 255 characters. At the checkout we can see about 5 first words.'),
        '#multiple' => FALSE,
        '#options' => ['code' => $this->t('From the code.'), 'settings' => $this->t('From current settings.'), 'merge' => $this->t('Merge both.')],
        '#default_value' => $init_settings['payment_parameter']['payment_config']['use_description'] ?? 'code',
        '#attributes' => [
          'name' => 'use_description',
        ],
      ];
      //description:string:REQUIRED
      $element['payment_parameter']['payment_config']['description'] = [
        '#type' => 'textarea',
        '#title' => $this->t('"description":string:REQUIRED. The description of the payment you are creating.'),
        '#description' => $this->t(
          'The description of the payment you are creating.<br>
          This will be shown to your customer on their card or bank statement when possible.<br>
          We truncate the description automatically according to the limits of the used payment method.<br>
          The description is also visible in any exports you generate.<br>
          "+" We recommend you use a unique identifier so that you can always link the payment to the order in your back office.<br>
          This is particularly useful for bookkeeping.<br>
          "+" The maximum length of the description field differs per payment method, with the absolute maximum being 255 characters.<br>
          The API will not reject strings longer than the maximum length but it will truncate them to fit.'
        ),
        '#placeholder' => 'Start typing here, max length is 255 characters..',
        '#default_value' => $init_settings['payment_config']['description'] ?? '',
        '#required' => TRUE,
        '#rows' => 3,
        '#maxlength_js' => TRUE,
        '#maxlength' => 254,
        '#states' => [
          'disabled' => [':input[name="use_description"]' => ['value' => 'code'],],
        ],
      ];
      //redirectUrl:string:REQUIRED
      //The URL your customer will be redirected to after the payment process.
      //It could make sense for the "redirectUrl" to contain a unique identifier – like your order ID – so you can show the right page referencing the order when your customer returns.
      //The parameter can be omitted for recurring payments ("sequenceType: recurring") and for Apple Pay payments with an "applePayPaymentToken".
      $element['payment_parameter']['payment_config']['use_redirectUrl'] = [
        '#type' => 'radios',
        '#title' => $this->t('No.3 How to generate a redirectUrl?'),
        '#description' => $this->t('It could make sense for the redirectUrl to contain a unique identifier – like your order ID.'),
        '#multiple' => FALSE,
        '#options' => ['code' => $this->t('Generate in code.'), 'custom' => $this->t('Custom, from current settings.'),],
        '#default_value' => $init_settings['payment_parameter']['payment_config']['use_redirectUrl'] ?? 'code',
        '#attributes' => [
          'name' => 'use_redirectUrl',
        ],
      ];
      $element['payment_parameter']['payment_config']['redirectUrl'] = [
        '#type' => 'path',
        '#title' => $this->t('"redirectUrl":string:REQUIRED. Redirect path'),
        '#description' => $this->t('Path to redirect the user to after payment.<br>
        "+" For example, type "/about" to redirect to that page. Use a relative path with a slash in front.'),
        '#convert_path' => \Drupal\Core\Render\Element\PathElement::CONVERT_NONE,//CONVERT_URL,
        '#default_value' => $init_settings['payment_parameter']['payment_config']['redirectUrl'] ?? '',
        '#validate_path' => FALSE,//TRUE,
        '#required' => FALSE,//TRUE,
        '#states' => [
          'disabled' => [':input[name="use_redirectUrl"]' => ['value' => 'code'],],
        ],
      ];
      //webhookUrl:string:OPTIONAL
      //Set the webhook URL, where we will send payment status updates to.
      //The "webhookUrl" is optional, but without a webhook you will miss out on important status changes to your payment.
      //The "webhookUrl" must be reachable from Mollie\'s point of view, so you cannot use "localhost".
      //If you want to use webhook during development on "localhost", you must use a tool like ngrok to have the webhooks delivered to your local machine.
      $element['payment_parameter']['payment_config']['use_webhookUrl'] = [
        '#type' => 'radios',
        '#title' => $this->t('No.4 How to generate a webhookUrl?'),
        '#description' => $this->t('You need to understand which webhook handles which type of payment.<br>
        "+" General settings are in the API.<br>
        "+" It is also available in test mode to install a webhook in the admin panel.<br>
        "+" If you set the webhook here, it will only process this field.'),
        '#multiple' => FALSE,
        '#options' => ['disable' => $this->t('Disable, do not use.'), 'code' => $this->t('From API code.'), 'custom' => $this->t('Custom, from current settings.'),],
        '#default_value' => $init_settings['payment_parameter']['payment_config']['use_webhookUrl'] ?? 'code',
        '#attributes' => [
          'name' => 'use_webhookUrl',
        ],
      ];
      $element['payment_parameter']['payment_config']['webhookUrl'] = [
        '#type' => 'url',
        '#title' => $this->t('"webhookUrl":string:OPTIONAL. Set the webhook URL to process transactions from a payment provider.'),
        '#description' => $this->t('The webhookUrl is optional, but without a webhook you will miss out on important status changes to your payment.<br>
        "+" The webhookUrl must be reachable from Mollie\'s point of view, so you cannot use localhost.<br>
        "+" If you want to use webhook during development on localhost, you must use a tool like ngrok to have the webhooks delivered to your local machine.'),
        '#default_value' => $init_settings['payment_parameter']['payment_config']['webhookUrl'] ?? '',
        '#placeholder' => 'https://example.org/webhook',
        '#disabled' => true,
        '#states' => [
          'enabled' => [':input[name="use_webhookUrl"]' => ['value' => 'custom'],],
        ],
      ];
      //locale:string:OPTIONAL
      $element['payment_parameter']['payment_config']['locale'] = [
        '#type' => 'select',
        '#title' => $this->t('No.5 "locale":string:OPTIONAL. Set the language on the hosted payment pages.'),
        '#description' => $this->t(
          'Allows you to preset the language to be used in the hosted payment pages shown to the consumer.<br>
          "+" Setting a locale is highly recommended and will greatly improve your conversion rate.<br>
          "+" When this parameter is omitted, the browser language will be used instead if supported by the payment method.<br>
          "+" You can provide any <b>"<code>xx_XX</code>"</b> format ISO 15897 locale, but our hosted payment pages currently only support the following languages:<br>
          "+" Possible values:<br>
          en_US nl_NL nl_BE fr_FR fr_BE de_DE de_AT de_CH es_ES ca_ES pt_PT it_IT nb_NO sv_SE fi_FI da_DK is_IS hu_HU pl_PL lv_LV lt_LT'
        ),
        '#options' => [
          'en_US' => 'en_US',
          'nl_NL' => 'nl_NL',
          'nl_BE' => 'nl_BE',
          'fr_FR' => 'fr_FR',
          'fr_BE' => 'fr_BE',
          'de_DE' => 'de_DE',
          'de_AT' => 'de_AT',
          'de_CH' => 'de_CH',
          'es_ES' => 'es_ES',
          'ca_ES' => 'ca_ES',
          'pt_PT' => 'pt_PT',
          'it_IT' => 'it_IT',
          'nb_NO' => 'nb_NO',
          'sv_SE' => 'sv_SE',
          'fi_FI' => 'fi_FI',
          'da_DK' => 'da_DK',
          'is_IS' => 'is_IS',
          'hu_HU' => 'hu_HU',
          'pl_PL' => 'pl_PL',
          'lv_LV' => 'lv_LV',
          'lt_LT' => 'lt_LT',
        ],
        '#empty_option' => '- select -',
        '#default_value' => $init_settings['payment_config']['locale'] ?? '',
      ];
      //method:string|array:OPTIONAL
      $element['payment_parameter']['payment_config']['method'] = [
        '#type' => 'select',
        '#title' => $this->t('No.6 "method":tring|array:OPTIONAL. Set a payment method screen is shown.'),
        '#description' => $this->t(
          'Normally, a payment method screen is shown.<br>
          However, when using this parameter, you can choose a specific payment method and your customer will skip the selection screen and is sent directly to the chosen payment method.<br>
          The parameter enables you to fully integrate the payment method selection into your website.<br>
          "+" You can also specify the methods in an array.<br>
          By doing so we will still show the payment method selection screen but will only show the methods specified in the array.<br>
          For example, you can use this functionality to only show payment methods from a specific country to your customer <b>"<code>["bancontact", "belfius"]</code>"</b>.<br>
          "+" Possible values:<br>
          applepay bancontact banktransfer belfius creditcard directdebit eps giftcard giropay ideal kbc mybank paypal paysafecard przelewy24 sofort<br>
          Note!<br>
          If you are looking to create payments with the Klarna Pay now, Klarna Pay later, Klarna Slice it, or voucher payment methods, please use Create order instead.'
        ),
        '#options' => [
          'applepay' => 'applepay',
          'bancontact' => 'bancontact',
          'banktransfer' => 'banktransfer',
          'belfius' => 'belfius',
          'creditcard' => 'creditcard',
          'directdebit' => 'directdebit',
          'eps' => 'eps',
          'giftcard' => 'giftcard',
          'giropay' => 'giropay',
          'ideal' => 'ideal',
          'kbc' => 'kbc',
          'mybank' => 'mybank',
          'paypal' => 'paypal',
          'paysafecard' => 'paysafecard',
          'przelewy24' => 'przelewy24',
          'sofort' => 'sofort',
        ],
        '#empty_option' => '- select -',
        '#default_value' => $init_settings['payment_config']['method'] ?? '',
      ];
      //restrictPaymentMethodsToCountry:string:OPTIONAL
      $element['payment_parameter']['payment_config']['use_restrictPaymentMethodsToCountry'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => '<b>No.7  "restrictPaymentMethodsToCountry".</b><br>
        Not implemented. (This is a message from Tatyana - I think we don\'t need this parameter.)<br>
        For digital goods in most jurisdictions, you must apply the VAT rate from your customer\'s country.<br>
        Choose the VAT rates you have used for the order to ensure your customer\'s country matches the VAT country.<br>
        Use this parameter to restrict the payment methods available to your customer to those from a single country.<br>
        If available, the credit card method will still be offered, but only cards from the allowed country are accepted.',
      ];
      $element['payment_parameter']['payment_config']['restrictPaymentMethodsToCountry'] = [
        '#type' => 'textfield',
        '#title' => $this->t('"restrictPaymentMethodsToCountry":string:OPTIONAL. Set restrict payment methods per country.'),
        '#description' => $this->t('Not implemented.'),
        '#placeholder' => 'Not implemented',
        '#disabled' => true,
        '#maxlength' => 64,
        '#size' => 64,
        '#default_value' => $init_settings['payment_parameter']['payment_config']['restrictPaymentMethodsToCountry'] ?? '',
      ];
      //metadata:mixed:OPTIONAL
      $element['payment_parameter']['payment_config']['use_metadata'] = [
        '#type' => 'radios',
        '#title' => $this->t('No.8 How to generate a metadata?'),
        '#description' => $this->t('Maximum 1kB. This is displayed in the mollie dashboard and in the transaction via the API.'),
        '#multiple' => FALSE,
        '#options' => ['code' => $this->t('From the code.'), 'settings' => $this->t('From current settings.'), 'merge' => $this->t('Merge both.')],
        '#default_value' => $init_settings['payment_parameter']['payment_config']['use_metadata'] ?? 'code',
        '#attributes' => [
          'name' => 'use_metadata',
        ],
      ];
      $element['payment_parameter']['payment_config']['metadata'] = [
        '#type' => 'textarea',
        '#title' => $this->t('"metadata":mixed:OPTIONAL. Provide any data you like.'),
        '#description' => $this->t(
          'Provide any data you like, for example a string or a JSON object.<br>
          We will save the data alongside the payment.<br>
          "+" Whenever you fetch the payment with our API, we will also include the metadata.<br>
          "+" You can use up to approximately 1kB.'
        ),
        '#placeholder' => 'Start typing here..',
        '#rows' => 5,
        '#default_value' => $init_settings['payment_config']['metadata'] ?? '',
      ];



    } else if (in_array($selected_method, ['order', 'payment_link', 'captures',])) {
      //not implemented
      $element['payment_parameter']['payment_config']['title'] = [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => 'Configuration of the selected payment method.',
      ];
      $element['payment_parameter']['payment_config']['help'] = [
        '#type' => 'markup',
        '#markup' => '<p>' . $this->t('This method is not implemented.') . '</p>',
      ];
    } else if ($selected_method == 'subscription') {
      // Subscriptions API
      // https://docs.mollie.com/reference/v2/subscriptions-api/create-subscription
      //1.1. Настройка первого платежа. (Нужен для Мгновенной зарядки по требованию.)
      //После успешного завершения первого платежа со счета или карты клиента сразу же будет взиматься оплата по требованию или периодически посредством подписок.
      // recurring 2 variant
      //2.1. Мгновенная зарядка по требованию - этодает возможность делать запросы когда нам нужно на разные сумы.
      //2.2 Периодическая оплата подписок - это простая периодичность мы выставляем периоды времени и суму.
      //Например, просто указав amount и interval, вы можете создать бесконечную подписку, чтобы взимать ежемесячную плату, пока вы не отмените подписку.
      //Или вы можете использовать times параметр, чтобы взимать плату только ограниченное количество раз, например, чтобы разделить большую транзакцию на несколько частей.
      $element['payment_parameter']['payment_config']['title'] = [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => 'Create subscription. Subscriptions API v2. Parameters:',
      ];
      //customerId
      $element['payment_parameter']['payment_config']['create_customer'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => 'NOTE! Before creating recurring payments, you need to create a <b>"<code>customerId</code>"</b> (Customers API v2).<br>
        "+" For example, by simply specifying an "<code>amount</code>" and an "<code>interval</code>", you can create an endless subscription to charge a monthly fee, until you cancel the subscription.<br>
        "+" Or, you could use the times parameter to only charge a limited number of "<code>times</code>", for example to split a big transaction in multiple parts.',
      ];
      $element['payment_parameter']['payment_config']['type'] = [
        '#type' => 'radios',
        '#title' => $this->t('Use recurring payment?'),
        '#description' => $this->t('With subscriptions, you can schedule recurring payments to take place at regular intervals without user involvement.<br>
        Important! Need "customerId" for the user, and which must have active mandate - "mandateId".<br>
        "+" For this, can set up the "first payment" with an amount of 0.01 or 0.00 EUR to receive "mandateId".<br>
        Types of recurring payments.<br>
        "+" recurring - These are periodic payments that can be set on demand for different amounts and without a specific time interval (not implemented).<br>
        "+" subscriptions - For simple regular recurring payments with constant amounts the Subscriptions API. Subscription payments will be spawned automatically at the specified frequency, and will show up in your Dashboard.<br>
        Some examples:<br>
        "+" By simply specifying an "amount" and an "interval", you can create an endless subscription to charge a monthly fee, until you cancel the subscription.<br>
        "+" Use the "times" parameter to only charge a limited number of times, for example to split a big transaction in multiple parts.'),
        '#multiple' => FALSE,
        '#options' => ['disabled' => $this->t('Disabled.'), 'recurring' => $this->t('Pay recurring.'), 'subscriptions' => $this->t('Pay subscriptions.'),],
        '#default_value' => $init_settings['payment_parameter']['payment_config']['type'] ?? 'disabled',
      ];
      //amount:amount object:REQUIRED [currency:string:REQUIRED(ISO 4217 code), value:string:REQUIRED]
      $element['payment_parameter']['payment_config'] += $this->elementAmount();
      $element['payment_parameter']['payment_config']['use_amount'] += [
        '#default_value' => $init_settings['payment_parameter']['payment_config']['use_amount'] ?? 'code',
      ];
      $element['payment_parameter']['payment_config']['amount']['value'] += [
        '#default_value' => $init_settings['payment_parameter']['payment_config']['amount']['value'] ?? '0.00',
      ];
      $element['payment_parameter']['payment_config']['amount']['currency'] += [
        '#default_value' => $init_settings['payment_parameter']['payment_config']['amount']['currency'] ?? 'EUR',
      ];
      //times:integer:OPTIONAL
      $element['payment_parameter']['payment_config']['times'] = [
        '#type' => 'number',
        '#title' => $this->t('"times":integer:OPTIONAL. Total number of charges. Leave empty for an ongoing.'),
        '#description' => $this->t(
          'Total number of charges for the subscription to complete.<br>
          "+" Leave empty for an ongoing subscription.'
        ),
        '#default_value' => $init_settings['payment_parameter']['payment_config']['times'] ?? '',
        '#min' => '0',
        '#max' => '10',
        '#step' => '1',
      ];
      //interval:string:REQUIRED
      $element['payment_parameter']['payment_config']['interval'] = [
        '#type' => 'textfield',
        '#title' => $this->t('"interval":string:REQUIRED. Interval to wait between charges.'),
        '#description' => $this->t(
          'Interval to wait between charges, for example string "<code>1 month</code>" or "<code>14 days</code>". in code example: interval="1 month", interval="1 day", interval="2 weeks"...<br>
          "+" Possible values: "<code>... months</code>" "<code>... weeks</code>" "<code>... days</code>".<br>
          "+" The maximum interval is 1 year ("<code>12 months</code>", "<code>52 weeks</code>" or "<code>365 days</code>").'
        ),
        '#required' => TRUE,
        '#placeholder' => '1 month',
        '#maxlength' => 20,
        '#size' => 20,
        '#default_value' => $init_settings['payment_parameter']['payment_config']['interval'] ?? '',
      ];
      //startDate:date:OPTIONAL
      $element['payment_parameter']['payment_config']['startDate'] = [
        '#type' => 'textfield',
        '#title' => $this->t('"startDate":date/string:OPTIONAL. The start date of the subscription in YYYY-MM-DD format.'),
        '#description' => $this->t(
          'The start date of the subscription in <b>"<code>YYYY-MM-DD</code>"</b> format. In code example: startDate="2018-04-30"...<br>
          "+" This is the first day on which your customer will be charged.<br>
          "+" When this parameter is not provided, the current date will be used instead.'
        ),
        '#placeholder' => (new DrupalDateTime)->format('Y-m-d'),
        '#maxlength' => 20,
        '#size' => 20,
        '#default_value' => $init_settings['payment_parameter']['payment_config']['startDate'] ?? '',
      ];
      //description:string:REQUIRED
      $element['payment_parameter']['payment_config']['description'] = [
        '#type' => 'textarea',
        '#title' => $this->t('"description":string:REQUIRED. A description unique per subscription.'),
        '#description' => $this->t(
          'A description unique per subscription. This will be included in the payment description.'
        ),
        '#required' => TRUE,
        '#placeholder' => 'Start typing here..',
        '#rows' => 5,
        '#resizable' => 'both',//"none", "vertical", "horizontal", or "both" (defaults to "vertical")
        '#default_value' => $init_settings['payment_parameter']['payment_config']['description'] ?? '',
      ];
      //method:string:OPTIONAL
      $element['payment_parameter']['payment_config']['method'] = [
        '#type' => 'select',
        '#title' => $this->t('"method":string:OPTIONAL. The payment method used for this subscription.'),
        '#description' => $this->t(
          'The payment method used for this subscription, either forced on creation or <code>null</code> if any of the customer\'s valid mandates may be used.<br>
          "+" Please note that this parameter can not set together with <b>"<code>mandateId</code>"</b>.<br>
          "+" Possible values: "<code>creditcard</code>" "<code>directdebit</code>" "<code>paypal</code>" "<code>null</code>"<br>
          "+" Using PayPal Reference Transactions is only possible if PayPal has activated this feature on your merchant-account.'
        ),
        '#options' => ['null' => 'null', 'creditcard' => 'creditcard', 'directdebit' => 'directdebit', 'paypal' => 'paypal',],
        '#empty_option' => '- select -',
        '#default_value' => $init_settings['payment_parameter']['payment_config']['method'] ?? '',
      ];
      //mandateId:string:OPTIONAL
      $element['payment_parameter']['payment_config']['mandateId'] = [
        '#type' => 'textfield',
        '#title' => $this->t('"mandateId":string:OPTIONAL. The mandate used for this subscription.'),
        '#description' => $this->t(
          'The mandate used for this subscription.<br>
          "+" Please note that this parameter can not set together with <b>"<code>method</code>"</b>.'
        ),
        '#maxlength' => 20,
        '#size' => 20,
        '#default_value' => $init_settings['payment_parameter']['payment_config']['mandateId'] ?? '',
      ];
      //webhookUrl:string:OPTIONAL
      $element['payment_parameter']['payment_config']['metadata'] = [
        '#type' => 'textarea',
        '#title' => $this->t('"metadata":mixed:OPTIONAL. Provide any data you like.'),
        '#description' => $this->t(
          'Provide any data you like, and we will save the data alongside the subscription.<br>
          Whenever you fetch the subscription with our API, we will also include the metadata.<br>
          You can use up to 1kB of JSON.'
        ),
        '#placeholder' => 'Start typing here..',
        '#rows' => 5,
        '#default_value' => $init_settings['payment_parameter']['payment_config']['metadata'] ?? '',
      ];
      //metadata:mixed:OPTIONAL
      $element['payment_parameter']['payment_config']['metadata'] = [
        '#type' => 'textarea',
        '#title' => $this->t('"metadata":mixed:OPTIONAL. Provide any data you like.'),
        '#description' => $this->t(
          'Provide any data you like, for example a string or a JSON object.<br>
          We will save the data alongside the payment.<br>
          "+" Whenever you fetch the payment with our API, we will also include the metadata.<br>
          "+" You can use up to approximately 1kB.'
        ),
        '#placeholder' => 'Start typing here..',
        '#rows' => 5,
        '#default_value' => $init_settings['payment_parameter']['payment_config']['metadata'] ?? '',
      ];
      //If using Access token parameters for organization access tokens or OAuth app.
      //profileId:string:REQUIRED FOR ACCESS TOKENS - The website profile\'s unique identifier, for example pfl_3RkSN1zuPE.
      //testmode:boolean:OPTIONAL - Set this to true to create a test mode subscription.
      //applicationFee:object:OPTIONAL - Adding an application fee allows you to charge the merchant for each payment in the subscription and transfer these amounts to your own account.
      //(application fee available in: Create payment, Create order, or Create subscription.)
      // https://docs.mollie.com/connect/application-fees#how-to-create-an-application-fee
      $element['payment_parameter']['payment_config']['application_fee_info'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => 'NOTE! Access token parameters:<br>
          "+" If you are using organization access tokens or are creating an OAuth app, you have to specify which profile you are creating a subscription for using the <b>"<code>profileId</code>"</b> parameter.<br>
          "+" Organizations can have multiple profiles for each of their websites. See Profiles API for more information.<br>
          And you can add <b>"<code>applicationFee</code>"</b>:<br>
          "+" Adding an application fee allows you to charge the merchant for each payment in the subscription and transfer these amounts to your own account.',
      ];

    } else {

      $element['payment_parameter']['payment_config']['config']['title'] = [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => 'Configuration of the selected payment method.',
      ];
      $element['payment_parameter']['payment_config']['help'] = [
        '#markup' => '<p>' . $this->t('Your choice is undefined or not implemented. Additional options are not available.') . '</p>',
      ];

    };



    // Extra options =====================================================
    // Parameters for recurring payments
    //sequenceType:string:REQUIRED FOR RECURRING
    //customerId:string:CONDITIONAL
    //mandateId:string:CONDITIONAL
    if (!empty($selected_method) && in_array($selected_method, ['payment',])) {
      $element['payment_parameter']['payment_config']['recurring']['title'] = [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => 'Parameters for recurring payments.',
      ];
      $element['payment_parameter']['payment_config']['recurring']['info'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => 'Recurring payments are created through the Payments API by providing a sequenceType.<br>
        "+" For the recurring sequence type, you have to provide either a customerId or mandateId to indicate which account or card you want to charge.<br>
        "+" See guide on Recurring for more information.',
      ];
      $element['payment_parameter']['payment_config']['recurring']['use'] = [
        '#type' => 'radios',
        '#title' => $this->t('Use recurring parameters'),
        '#description' => $this->t('It may be better to create a subscription or payment on request?'),
        '#multiple' => FALSE,
        '#options' => ['0' => $this->t('No'), '1' => $this->t('Yes'),],
        '#default_value' => $init_settings['payment_parameter']['payment_config']['recurring']['use'] ?? '0',
        '#attributes' => [
          'name' => 'use_recurring',
        ],
      ];
      $element['payment_parameter']['payment_config']['recurring_config'] = [
        '#type' => 'fieldset',
        '#states' => [
          'disabled' => [
            ':input[name="use_recurring"]' => [['value' => '0'],],
          ],
          'visible' => [
            ':input[name="use_recurring"]' => [['value' => '1'],],
          ],
        ],
        '#prefix' => '<div id="parameters-recurring">',
        '#suffix' => '</div>',
      ];
      $element['payment_parameter']['payment_config']['recurring_config'] += $this->partRecurringPayments($form, $form_state);
    } else {
      $element['payment_parameter']['payment_config']['recurring_config'] = [
        '#markup' => '<p>' . $this->t('Recurring payments are not available for this payment type.') . '</p>',
        '#prefix' => '<div id="parameters-recurring">',
        '#suffix' => '</div>',
      ];
    };

    //Payment method-specific parameters
    if (!empty($selected_method) && in_array($selected_method, ['payment',])) {
      $element['payment_parameter']['payment_config']['specific']['title'] = [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => 'Payment method-specific parameters.',
      ];
      $element['payment_parameter']['payment_config']['specific']['info'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => 'If you specify the method parameter, optional parameters may be available for the payment method.<br>
        "+" If no method is specified, you can still send the optional parameters and it will apply them when the consumer selects the relevant payment method.<br>
        Not implemented.',
      ];
      $element['payment_parameter']['payment_config']['specific']['use'] = [
        '#type' => 'radios',
        '#title' => $this->t('Use method-specific parameters'),
        '#multiple' => FALSE,
        '#options' => ['0' => $this->t('No'), '1' => $this->t('Yes'),],
        '#default_value' => $init_settings['payment_parameter']['payment_config']['specific']['use'] ?? '0',
        '#attributes' => [
          'name' => 'use_specific',
        ],
      ];
      $element['payment_parameter']['payment_config']['method_specific'] = [
        '#type' => 'fieldset',
        '#states' => [
          'disabled' => [
            ':input[name="use_specific"]' => [['value' => '0'],],
          ],
          'visible' => [
            ':input[name="use_specific"]' => [['value' => '1'],],
          ],
        ],
        '#prefix' => '<div id="parameters-specific">',
        '#suffix' => '</div>',
      ];
      $element['payment_parameter']['payment_config']['method_specific'] += $this->partMethodSpecific($form, $form_state);
    } else {
      $element['payment_parameter']['payment_config']['method_specific'] = [
        '#markup' => '<p>' . $this->t('Method-specific payments are not available for this payment type.') . '</p>',
        '#prefix' => '<div id="parameters-specific">',
        '#suffix' => '</div>',
      ];
    };

    // Access token parameters
    //profileId:string:REQUIRED FOR ACCESS TOKENS
    //testmode:boolean:OPTIONAL
    if (!empty($selected_method) && in_array($selected_method, ['payment',])) {
      $element['payment_parameter']['payment_config']['access']['title'] = [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => 'Access token parameters.',
      ];
      $element['payment_parameter']['payment_config']['access']['info'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => 'If you are using organization access tokens or are creating an OAuth app, you have to specify which profile you are creating the payment for using the profileId parameter.<br>
        "+" Organizations can have multiple profiles for each of their websites. See Profiles API for more information.<br>
        "+" For these authentication methods the optional testmode parameter is available as well to enable test mode.<br>
        "+" Not implemented.',
      ];
      $element['payment_parameter']['payment_config']['access']['use'] = [
        '#type' => 'radios',
        '#title' => $this->t('Use access token parameters'),
        '#description' => $this->t('Maybe it\'s better to adjust the parameters in the API or main settings?'),
        '#multiple' => FALSE,
        '#options' => ['0' => $this->t('No'), '1' => $this->t('Yes'),],
        '#default_value' => $init_settings['payment_parameter']['payment_config']['access']['use'] ?? '0',
        '#attributes' => [
          'name' => 'use_access',
        ],
      ];
      $element['payment_parameter']['payment_config']['access_token'] = [
        '#type' => 'fieldset',
        '#states' => [
          'disabled' => [
            ':input[name="use_access"]' => [['value' => '0'],],
          ],
          'visible' => [
            ':input[name="use_access"]' => [['value' => '1'],],
          ],
        ],
        '#prefix' => '<div id="parameters-access">',
        '#suffix' => '</div>',
      ];

      if (!empty($form_current_variables['authorization']) && $form_current_variables['authorization'] !== 'profile') {
        $element['payment_parameter']['payment_config']['access_token'] += $this->partAaccessToken($form, $form_state);
      } else {
        $element['payment_parameter']['payment_config']['access_token'] += [
          '#markup' => $this->t('Access token parameters available when using organization access tokens or are creating an OAuth app.'),
        ];
      };

    } else {
      $element['payment_parameter']['payment_config']['access_token'] = [
        '#markup' => '<p>' . $this->t('Access token parameters are not available for this payment type.') . '</p>',
        '#prefix' => '<div id="parameters-access">',
        '#suffix' => '</div>',
      ];
    };

    // Mollie Connect parameters
    //applicationFee:object:OPTIONAL = [amount:amount object:REQUIRED = [currency:string:REQUIRED, value:string:REQUIRED], description:string:REQUIRED]
    //routing:array:OPTIONAL = [ amount = [currency, value], destination = [type, organizationId], releaseDate,]
    //подключенная учетная запись сохраняет полную ответственность и контролирует платеж,
    //а ваша платформа только вычитает комиссию.
    //Они создаются путем передачи дополнительных параметров в: Создать платеж, Создать заказ или Создать подписку.
    if (!empty($selected_method) && in_array($selected_method, ['payment', 'order', 'subscription',])) {
      $element['payment_parameter']['payment_config']['connect']['title'] = [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => 'Mollie Connect parameters',
      ];
      $element['payment_parameter']['payment_config']['connect']['info'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => 'With Mollie Connect you can charge fees on payments that are processed through your app, either by defining an application fee or by splitting the payment.<br>
        To learn more about the difference, please refer to the Mollie Connect overview.',
      ];
      $element['payment_parameter']['payment_config']['connect']['use'] = [
        '#type' => 'radios',
        '#title' => $this->t('Use recurring parameters'),
        '#description' => $this->t('It may be better to create a subscription or payment on request?'),
        '#multiple' => FALSE,
        '#options' => [
          'disable' => $this->t('Disable not use'),
          'applicationFee' => $this->t('Use applicationFee'),
          'routing' => $this->t('Use routing'),
        ],
        '#default_value' => $init_settings['payment_config']['connect']['use'] ?? 'disable',
        '#attributes' => [
          'name' => 'use_connect',
        ],
      ];
      $element['payment_parameter']['payment_config']['applicationFee'] = [
        '#type' => 'fieldset',
        '#states' => [
          'enabled' => [
            ':input[name="use_connect"]' => [['value' => 'applicationFee'],],
          ],
          'visible' => [
            ':input[name="use_connect"]' => [['value' => 'applicationFee'],],
          ],
        ],
        '#prefix' => '<div id="parameters-fee">',
        '#suffix' => '</div>',
      ];
      $element['payment_parameter']['payment_config']['routing'] = [
        '#type' => 'fieldset',
        '#states' => [
          'enabled' => [
            ':input[name="use_connect"]' => [['value' => 'routing'],],
          ],
          'visible' => [
            ':input[name="use_connect"]' => [['value' => 'routing'],],
          ],
        ],
        '#prefix' => '<div id="parameters-routing">',
        '#suffix' => '</div>',
      ];

      if (!empty($form_current_variables['authorization']) && $form_current_variables['authorization'] == 'app') {

        $element['payment_parameter']['payment_config']['applicationFee'] += $this->partApplicationFee($form, $form_state, $init_settings);
        $element['payment_parameter']['payment_config']['routing'] += $this->partRouting($form, $form_state);

      } else {
        $element['payment_parameter']['payment_config']['applicationFee'] += [
          '#markup' => '<p>' . $this->t(
            'Receiving application fees available when using OAuth app.<br>
            "+" They are created by passing additional parameters to the Create payment, Create order, or the Create subscription endpoint.'
            ) . '</p>',
        ];
        $element['payment_parameter']['payment_config']['routing'] += [
          '#markup' => '<p>' . $this->t('Receiving routing unavailable.') . '</p>',
        ];
      };

    } else {
      $element['payment_parameter']['payment_config']['connect'] = [
        '#markup' => '<p>' . $this->t('Mollie Connect parameters are not available for this payment type.') . '</p>',
        '#prefix' => '<div id="parameters-access">',
        '#suffix' => '</div>',
      ];
    };

    //QR codes
    if (!empty($selected_method) && in_array($selected_method, ['payment',])) {
      $element['payment_parameter']['payment_config']['qr']['title'] = [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => 'QR codes parameters.',
      ];
      $element['payment_parameter']['payment_config']['qr']['info'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => 'To create a payment with a QR code embedded in the API response, explicitly set the payment method and call the API endpoint with an include request for include=details.qrCode in the query string.<br>
        "+" QR codes can be generated for iDEAL, Bancontact and bank transfer payments.<br>
        "+" Refer to the Get payment reference to see what the API response looks like when the QR code is included.',
      ];
      $element['payment_parameter']['payment_config']['qr_codes'] = [
        '#prefix' => '<div id="parameters-qr">',
        '#suffix' => '</div>',
      ];
      $element['payment_parameter']['payment_config']['qr_codes'] += $this->partQRcodes($form, $form_state);
    } else {
      $element['payment_parameter']['payment_config']['qr_codes'] = [
        '#markup' => '<p>' . $this->t('QR codes parameters are not available for this payment type.') . '</p>',
      ];
    };


    return $element['payment_parameter'];
  }







  /**
   * Returns part of the form - parameters for recurring payments.
   * @return array
   *   The part of the form definition for the field settings.
   */
  public function partRecurringPayments(array $form, FormStateInterface $form_state) {

    $element['payment_parameter']['payment_config']['recurring_config']['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h3',
      '#value' => 'Receiving recurring payment. Enabling recurring payments. Parameters:',
    ];
    $element['payment_parameter']['payment_config']['recurring_config']['info'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => 'NOTE!<br>
      "+" The recurring payment will be either pending or active depending on whether the customer has a pending or valid mandate.<br>
      "+" If the customer has no mandates an error is returned.<br>
      "+" Should then set up a "first payment" for the customer.',
    ];

    //sequenceType:string:REQUIRED FOR RECURRING
    $element['payment_parameter']['payment_config']['recurring_config']['sequenceType'] = [
      '#type' => 'select',
      '#title' => $this->t('"sequenceType":string:REQUIRED FOR RECURRING. Indicate if use which type of payment this is in a recurring sequence.'),
      '#description' => $this->t(
        'Indicate which type of payment this is in a recurring sequence.<br>
        "+" If set to <b>"<code>first</code>"</b>, a first payment is created for the customer, allowing the customer to agree to automatic recurring charges taking place on their account in the future.<br>
        "+" If set to <b>"<code>recurring</code>"</b>, the customer\'s card is charged automatically.<br>
        "+" Defaults to <b>"<code>oneoff</code>"</b>, which is a regular non-recurring payment.<br>
        "+" Possible values:<br>
        oneoff first recurring<br>
        "+" For PayPal payments, recurring is only possible if PayPal has activated Reference Transactions on your merchant account.<br>
        Check if you account is eligible via our Methods API with parameter sequenceType set to first.<br>
        Your account is eligible if PayPal is returned in the method list.'
      ),
      '#options' => [
        'oneoff' => 'oneoff',
        'first' => 'first',
        'recurring' => 'recurring',
      ],
      '#empty_option' => '- select -',
      '#default_value' => $init_settings['payment_parameter']['payment_config']['recurring_config']['sequenceType'] ?? '',
      '#states' => [
        'disabled' => [
          ':input[name="use_recurring"]' => [['value' => '0'],],
        ],
        'visible' => [
          ':input[name="use_recurring"]' => [['value' => '1'],],
        ],
      ],

    ];
    //customerId:string:CONDITIONAL
    $element['payment_parameter']['payment_config']['recurring_config']['customerId'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('"customerId":string:CONDITIONAL. Use the ID of the customer for whom the payment is being created.'),
      '#description' => $this->t(
        'IMPORTANT! First, we need to create a unique customer using the Customers API.<br>
        The ID of the customer for whom the payment is being created.<br>
        This is used primarily for recurring payments, but can also be used on regular payments to enable single-click payments.<br>
        Either this field or the <b>"<code>mandateId</code>"</b> field needs to be provided for payments with the <b>"<code>recurring</code>"</b> sequence type.'
      ),
      '#return_value' => 'customerId',
      '#default_value' => $init_settings['payment_parameter']['payment_config']['recurring_config']['customerId'] ?? '',
      '#states' => [
        'unchecked' => [
          ':input[name="settings[payment_parameter][payment_config][recurring_config][mandateId]"]' => [['checked' => true],],
        ],
        'disabled' => [
          [
            ':input[name="settings[payment_parameter][payment_config][recurring_config][sequenceType]"]' => [['value' => ''],],
            'and',
            ':input[name="use_recurring"]' => [['value' => '1'],],
          ],
          'or',
          [':input[name="use_recurring"]' => [['value' => '0'],],],
        ],
        'visible' => [
          ':input[name="use_recurring"]' => [['value' => '1'],],
        ],
      ],
    ];
    //mandateId:string:CONDITIONAL
    $element['payment_parameter']['payment_config']['recurring_config']['mandateId'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('"mandateId":string:CONDITIONAL. Use the ID of the mandate.'),
      '#description' => $this->t(
        'IMPORTANT! First, we need to create a unique customer. Once the "customerId " is complete, there will be a customer mandate that we can access via the Mandates API. After, we too.. can also create other mandates and use Mandates API.<br>
        When creating recurring payments, the ID of a specific mandate can be supplied to indicate which of the consumer\'s accounts should be credited.<br>
        Either this field or the <b>"<code>customerId</code>"</b> field needs to be provided for payments with the <b>"<code>recurring</code>"</b> sequence type.'
      ),
      '#return_value' => 'mandateId',
      '#default_value' => $init_settings['payment_parameter']['payment_config']['recurring_config']['mandateId'] ?? '',
      '#states' => [
        'disabled' => [
          [
            ':input[name="settings[payment_parameter][payment_config][recurring_config][sequenceType]"]' => [['value' => ''],],
            'and',
            ':input[name="use_recurring"]' => [['value' => '1'],],
          ],
          'or',
          [':input[name="use_recurring"]' => [['value' => '0'],],],
        ],
        'visible' => [
          ':input[name="use_recurring"]' => [['value' => '1'],],
        ],
      ],
    ];

    return $element['payment_parameter']['payment_config']['recurring_config'];
  }

  /**
   * Returns part of the form - parameters for method-specific payments.
   * @return array
   *   The part of the form definition for the field settings.
   */
  public function partMethodSpecific(array $form, FormStateInterface $form_state) {
    $element['payment_parameter']['payment_config']['method_specific']['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h3',
      '#value' => 'Set up a specific settings for payment method. Enabling method-specific. Parameters:',
    ];
    $element['payment_parameter']['payment_config']['method_specific']['info'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => 'NOTE!<br>
      "+" It is additional settings for payment method as Bank transfer, Credit card, PayPal...<br>
      Not implemented. Using default mollie settings.',
    ];

    return $element['payment_parameter']['payment_config']['method_specific'];
  }

  /**
   * Returns part of the form - access token parameters.
   * @return array
   *   The part of the form definition for the field settings.
   */
  public function partAaccessToken(array $form, FormStateInterface $form_state) {
    $element['payment_parameter']['payment_config']['access_token']['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h3',
      '#value' => 'Set up a access token parameters. Enabling access token. Parameters:',
    ];
    $element['payment_parameter']['payment_config']['access_token']['info'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => 'NOTE!<br>
      Can connect to the API in 3 ways authentication:<br>
      1. use API keys. We can create multiple "Website profiles" each of which will have:<br>
      - address: "www.example.org"<br>
      - Live API key (example: "live_abc123zzzzzzzzzzzzzzzzzzzzzzzz"). Connecting for real payments without using OAuth2.0<br>
      - Test API key (example: "test_abc123zzzzzzzzzzzzzzzzzzzzzzzz"). Connection for test payments without using OAuth2.0<br>
      - Profile ID (example: "pfl_abc123zzzz"). This setting is used to connect through an organization access token using OAuth2.0<br>
      2. use Organization access tokens.<br>
      - We can create multiple keys with different access permissions. (example: "access_abc123zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz")<br>
      - And to make it clear for which website it is used, need to specify "Profile ID".<br>
      3. use App access tokens. In this case, we act as an intermediary.<br>
      - We create applications.<br>
      - We ask the seller to give our app permission to accept payments on his behalf (we get an access token).<br>
      - And with the help of App access tokens we accept payments. (example: "access_abc123zzzzzzzzzzzzzzzzzzzzzzzz").<br>
      - In this case, we also specify the "Profile ID" to make it clear which website of ours is making the payment.',
    ];

    //profileId:string:REQUIRED FOR ACCESS TOKENS
    $element['payment_parameter']['payment_config']['access_token']['profileId'] = [
      '#type' => 'textfield',
      '#title' => $this->t('"profileId":string:REQUIRED FOR ACCESS TOKENS. The website profile\'s unique identifier.'),
      '#description' => $this->t('We take this parameter from the API of the payment plugin. (for example "pfl_3RkSN1zuPE")'),
      '#maxlength' => 20,
      '#size' => 20,
      '#default_value' => $init_settings['payment_parameter']['payment_config']['access_token']['profileId'] ?? '',
      '#disabled' => true,
    ];
    //testmode:boolean:OPTIONAL
    $element['payment_parameter']['payment_config']['access_token']['testmode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('<b>"testmode":boolean:OPTIONAL. Set this to true to make this payment a test payment.</b>'),
      '#description' => $this->t('We take this parameter from the general settings of the payment plugin.'),
      '#default_value' => $init_settings['payment_parameter']['payment_config']['access_token']['testmode'] ?? '',
      '#return_value' => 'test',
      '#disabled' => true,
    ];

    return $element['payment_parameter']['payment_config']['access_token'];
  }

  /**
   * Returns part of the form - for applicationFee parameters.
   * @return array
   *   The part of the form definition for the field settings.
   */
  public function partApplicationFee(array $form, FormStateInterface $form_state, $init_settings) {

    $element['payment_parameter']['payment_config']['applicationFee']['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h3',
      '#value' => 'Receiving application fees. Enabling application fees. Parameters:',
    ];
    $element['payment_parameter']['payment_config']['applicationFee']['info'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => 'NOTE!<br>
      "+" Adding an application fee allows you to charge the merchant for the payment and transfer this to your own account.<br>
      "+" The application fee is deducted from the payment.<br>
      "+" The amount the app wants to charge, e.g. <code>{"currency":"EUR", "value":"10.00"}</code> if the app would want to charge €10.00.<br>
      Read more about maximum application fees.',
    ];
    $element['payment_parameter']['payment_config']['applicationFee']['use'] = [
      '#type' => 'radios',
      '#title' => $this->t('Use application fees parameters'),
      '#description' => $this->t('Payment can be made to your account or to the account of your clients using the authorization methods (in this case, you can set a commission fee)'),
      '#multiple' => FALSE,
      '#options' => ['your' => $this->t('Pay to your account.'), 'seller' => $this->t('Pay to the account of your sellers.'),],
      '#default_value' => $init_settings['payment_config']['applicationFee']['use'] ?? 'your',
      '#attributes' => [
        'name' => 'use_fee',
      ],
    ];

    //https://docs.mollie.com/connect/application-fees
    //applicationFee:object:OPTIONAL [amount:amount object:REQUIRED [currency:string:REQUIRED, value:string:REQUIRED], description:string:REQUIRED]
    $element['payment_parameter']['payment_config']['applicationFee']['amount']['value'] = [
      '#type' => 'number',
      '#title' => $this->t('"value":string:REQUIRED. Enter fees (0.00 - 10 000.00)'),
      '#description' => $this->t(
        'A string containing the exact amount you want to charge in the given currency.<br>
        "+" Make sure to send the right amount of decimals.<br>
        "+" Non-string values are not accepted.'
      ),
      '#default_value' => $init_settings['payment_config']['applicationFee']['amount']['value'] ?? '0.00',
      '#min' => '0',
      '#max' => '10000',
      '#step' => '0.01',
      '#states' => [
        'disabled' => [':input[name="use_fee"]' => ['value' => 'your'],],
      ],
    ];
    $element['payment_parameter']['payment_config']['applicationFee']['amount']['currency'] = [
      '#type' => 'radios',
      '#title' => $this->t('"currency":string:REQUIRED. Currency code'),
      '#description' => $this->t(
        'An ISO 4217 currency code.<br>
        "+" For application fees, this must always be <b>"<code>EUR</code>"</b> regardless of the currency of the payment, order or subscription.'
      ),
      '#options' => ['EUR' => 'EUR',],
      '#default_value' => $init_settings['payment_config']['applicationFee']['amount']['currency'] ?? 'EUR',
      '#states' => [
        //'visible' => [':input[name="settings[payment_parameter][connect]"]' => ['value' => '1'],],
        'disabled' => [':input[name="use_fee"]' => ['value' => 'your'],],
      ],
    ];
    $element['payment_parameter']['payment_config']['applicationFee']['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('"description":string:REQUIRED. The description of the application fee.'),
      '#description' => $this->t(
        'The description of the application fee.<br>
        "+" This will appear on settlement reports to the merchant and to you.<br>
        "+" The maximum length is 255 characters.'
      ),
      '#placeholder' => 'Start typing here, max length is 255 characters..',
      '#default_value' => $init_settings['payment_config']['applicationFee']['description'] ?? '',
      '#rows' => 3,
      '#maxlength_js' => TRUE,
      '#maxlength' => 254,
      '#states' => [
        'disabled' => [':input[name="use_fee"]' => ['value' => 'your'],],
      ],
    ];

    return $element['payment_parameter']['payment_config']['applicationFee'];
  }

  /**
   * Returns part of the form - parameters for recurring payments.
   * @return array
   *   The part of the form definition for the field settings.
   */
  public function partRouting(array $form, FormStateInterface $form_state) {
    $element['payment_parameter']['payment_config']['routing']['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h3',
      '#value' => 'Receiving routing. Enabling routing. Parameters:',
    ];
    $element['payment_parameter']['payment_config']['routing']['info'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => 'NOTE!<br>
      "+" This functionality is currently in closed beta.<br>
      "+" Please contact mollie partner management team if you are interested in testing this functionality with them.<br>
      Not implemented.',
    ];

    return $element['payment_parameter']['payment_config']['routing'];
  }

  /**
   * Returns part of the form - QR codes parameters.
   * @return array
   *   The part of the form definition for the field settings.
   */
  public function partQRcodes(array $form, FormStateInterface $form_state) {
    $element['payment_parameter']['payment_config']['qr_codes']['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h3',
      '#value' => 'Set up a QR codes parameters. Enabling QR codes. Parameters:',
    ];
    $element['payment_parameter']['payment_config']['qr_codes']['info'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => 'NOTE!<br>
      "+" This endpoint allows you to include additional information by appending the following values via the include querystring parameter. "http...?include=details.qrCode".<br>
      Not implemented.',
    ];

    return $element['payment_parameter']['payment_config']['qr_codes'];
  }

  /**
   * Returns element of the form - amount.
   * @return array
   *   The element of the form definition for the field settings.
   */
  public function elementAmount() {
    //amount:amount object:REQUIRED [currency:string:REQUIRED, value:string:REQUIRED]
    $element['payment_parameter']['payment_config']['use_amount'] = [
      '#type' => 'radios',
      '#title' => $this->t('No.1 How to calculate the price and currency?'),
      '#description' => $this->t('The price can be calculated in code or set statically.<br>
      If calculated in code. It must implement this functionality.'),
      '#multiple' => FALSE,
      '#options' => ['code' => $this->t('Calculate in code.'), 'static' => $this->t('Set statically.'),],
      '#attributes' => [
        'name' => 'use_amount',
      ],
    ];
    $element['payment_parameter']['payment_config']['amount'] = [
      '#markup' => 'NOTE! The amount object (currency and value) will be set up in the code.',
    ];
    $element['payment_parameter']['payment_config']['amount']['value'] = [
      '#type' => 'number',
      '#title' => $this->t('"value":string:REQUIRED. Enter fees (0.00 - 10 000.00)'),
      '#description' => $this->t(
        'A string containing the exact amount you want to charge in the given currency.<br>
        "+" Make sure to send the right amount of decimals.<br>
        "+" Non-string values are not accepted.'
      ),
      '#min' => '0',
      '#max' => '10000',
      '#step' => '0.01',
      '#states' => [
        'disabled' => [':input[name="use_amount"]' => ['value' => 'code'],],
      ],
    ];
    $element['payment_parameter']['payment_config']['amount']['currency'] = [
      '#type' => 'select',
      '#title' => $this->t('"currency":string:REQUIRED. Currency code'),
      '#description' => $this->t(
        'An ISO 4217 currency code.<br>
        "+" The currencies supported depend on the payment methods that are enabled on your account.'
      ),
      '#options' => [
        'EUR' => 'EUR',
        'CHF' => 'CHF',
        'GBP' => 'GBP',
        'NOK' => 'NOK',
      ],
      '#states' => [
        'disabled' => [':input[name="use_amount"]' => ['value' => 'code'],],
      ],
    ];

    return $element['payment_parameter']['payment_config'];
  }

  /**
   * Ajah callback for the settings form of the button pay.
   *
   * Called from \Drupal\payment_provider\Plugin\PaymentProvider\HelperFormSettingsField,
   * setting for field parameters.
   * To add the fee settings to payment configuration for a payment provider for a button.
   *
   * @param array $form
   *   The form where the settings form is being included in.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the (entire) configuration form.
   *
   * @return array
   *   The part of the form definition for the field settings.
   */
  public static function ajax_show_support_oauth(array $form, FormStateInterface $form_state) {
    $parents = array_slice($form_state->getTriggeringElement()['#parents'], 0, -1);
    $parents_count = count($parents);
    $element = $form;
    for ($i=0;$i<$parents_count;$i++) {
      if (array_key_exists($parents[$i], $element)) {
        $element = $element[$parents[$i]];
      } else {
        break;
      };
    };

    $response = new AjaxResponse();

    if (isset($element['payment_config']['access_token'])) {
      $response->addCommand(new ReplaceCommand('#parameters-access', $element['payment_config']['access_token']));
    };
    if (isset($element['payment_config']['applicationFee'])) {
      $response->addCommand(new ReplaceCommand('#parameters-fee', $element['payment_config']['applicationFee']));
    };
    if (isset($element['payment_config']['routing'])) {
      $response->addCommand(new ReplaceCommand('#parameters-routing', $element['payment_config']['routing']));
    };

    if (empty($response->getCommands())) {
      return;
    };
    return $response;
  }







}
