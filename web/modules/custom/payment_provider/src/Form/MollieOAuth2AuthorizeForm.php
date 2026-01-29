<?php

namespace Drupal\payment_provider\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\payment_provider\PaymentProviderPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\payment_provider\PaymentProviderPluginManager;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * @see https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Form%21FormBase.php/class/FormBase/8.8.x
 */

class MollieOAuth2AuthorizeForm extends FormBase {

  /**
   * The payment plugin manager used by this form.
   * @var \Drupal\payment_provider\PaymentProviderPluginManager $managerPaymentProvider
   * Plugin manager for load Mollie OAuth2 authorize
   */
  protected $managerPaymentProvider;

  /**
   * The ID of the payment provider plugin used in this form.
   * @var string $providerId ID payment provider Mollie.
   */
  protected $providerId = 'mollie';

  /** {@inheritdoc} */
  public function getFormId() {
    return 'mollie_authorize_form';
  }

  /**
   * Construct MollieOAuth2AuthorizeForm.
   * @param \Drupal\payment_provider\PaymentProviderPluginManager $managerPaymentProvider
   *   Plugin manager.
   */
  public function __construct(PaymentProviderPluginManager $managerPaymentProvider) {
    $this->managerPaymentProvider = $managerPaymentProvider;
  }

  /** {@inheritdoc} */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.payment_provider')
    );
  }

  /** {@inheritdoc} */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // page authorize/mollie
    //$test = $this->molliePaymentProvider->getOAuth2Authorize()->infoOwnerMollieOAuth2();

    /** @var \Drupal\Core\Session\AccountProxyInterface Proxy object current user */
    $currentUser = $this->currentUser();

    if ($currentUser->hasPermission('administrator')) {
      $form['account'] = [
        '#type' => 'container',
        '#title' => $this->t('Connection Mollie account'),
        'check' => [
          '#markup' => '<p>' . $this->t('@user is an administrator.', ['@user' => $currentUser->getDisplayName()]) . '</p>',
        ],
        'instruction' => [
          '#markup' => '<p>' . $this->t('Connection to the account of the payment provider is carried out through the API plugin.') . '</p>',
        ],
      ];
      return $form;
    };

    /** @var \Drupal\payment_provider\Plugin\PaymentProvider\PaymentProviderMollie
     * $molliePaymentProvider The payment provider Mollie object. */
    $molliePaymentProvider = $this->managerPaymentProvider->createInstance($this->providerId);
    $provider = $molliePaymentProvider->getId();
    $label = $molliePaymentProvider->getProviderLabel();

    /** @var \Symfony\Component\HttpFoundation\Request The currently active request object. */
    $currentRequest = $this->getRequest();

    //Let's check if there is a "code" parameter in the get request
    if ($currentRequest->query->get('code')) {
      $token = $molliePaymentProvider->getOAuth2Authorize()->processingOAuth2Redirect();
      if($token){ user_redirection_after_authenticate(); }
    };

    // If not "code" parameter in the get request
    if (empty($token)) {
      /** @var \Drupal\Core\Entity\EntityStorageInterface $authorizeOAuth2 A storage instance. */
      $authorizeOAuth2 = \Drupal::entityTypeManager()->getStorage('oauth_authorize_token');

      /** @var \Drupal\Core\Entity\EntityInterface[] $token An array of entity objects indexed by their ids. */
      $token = $authorizeOAuth2->loadByProperties(['provider' => $provider, 'author' => $currentUser->id()]);
    };

    //$token = null;//test
    // If not token, we will provide a button for obtaining a token, otherwise we will display information about the token
    if (empty($token)) {
      $checkProvider = $this->t('Hi @user you are not yet authorize via @provider', ['@user' => $currentUser->getDisplayName(), '@provider' => $label]);
      $codeSnippet = $this->t('You scopes is not assigned');
      $form['actions']['#type'] = 'actions';
      $form['actions']['#weight'] = 0;
      $form['actions']['submit'] = array(
        '#type' => 'submit',
        '#value' => $this->t('Get authorize via @provider', ['@provider' => $label]),
        '#button_type' => 'primary',
        '#attributes' => [
          'class' => [$provider . '-button'],
        ],
      );
    } else {
      $token = reset($token);
      $checkProvider = $this->t('Hi @user you are have authorize via @provider', ['@user' => $currentUser->getDisplayName(), '@provider' => $label]);
      // $codeSnippet = $token->scopes->view();
      if ($token && !is_string($token) && $token->scopes) {
        $codeSnippet = $token->scopes->view();
    } else {
        $codeSnippet = $this->t('You scopes is not assigned');
    }
    };

    $form['account'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Mollie account'),
      'check' => [
        '#markup' => $checkProvider,
      ],
      'instruction' => [
        '#markup' => '<p>' . $this->t('Authorize via @provider will allow you to accept payments.', ['@provider' => $label]) . '</p>',
      ],
      'codesnippet' => [
        empty($token) ? '#markup' : 'data' => $codeSnippet,
      ],
    ];

    // Next, need to check if the current user has the ability to accept payments and withdraw money.
    // see https://docs.mollie.com/reference/v2/onboarding-api/get-onboarding-status
    // see https://docs.mollie.com/reference/v2/onboarding-api/submit-onboarding-data
    if ($token) {

      // Get "onboarding" data about whether the current user can accept payments
      $values = $molliePaymentProvider->getUserExtraInfo($currentUser->id(), 'onboarding');
      if (!$values) {
        $values = $this->refreshRegistrationStatus();
      };

      // Prepare the data for the form
      if (isset($values['status'])) {
        switch ($values['status']) {
          case 'needs-data':
            $status_molie['link'] = Link::fromTextAndUrl($this->t('Mollie Connect'), Url::fromUri('https://docs.mollie.com/connect/onboarding', ['query'=>[],'fragment'=>'step-5-wait-for-your-customer-to-complete-the-onboarding',]))->toString();
            $status_mollie['message'] = $values['canReceivePayments'] ?
            $this->t('You can start receiving payments. Before Mollie can pay out to your bank, please provide some additional information.  @link.', [
              '@link' => $status_molie['link'],
            ]) :
            $this->t('Before you can receive payments, Mollie needs more information. @link.', [
              '@link' => $status_molie['link'],
            ]);
            break;
          case 'in-review':
            $status_mollie['message'] = $values['canReceivePayments'] ?
            $this->t('You can start receiving payments. Mollie is verifying your details to enable settlements to your bank.') :
            $this->t('Mollie has all the required information and is verifying your details.');
            break;
          case 'complet':
            $status_mollie['message'] = $this->t('Setup is complete!');
            break;
        };
      } else {
        $status_mollie['message'] = $this->t('Failed to get Mollie\'s status. No customer registration data.');
      };

      $status_mollie['check'] = isset($values['checkIin']) ? DrupalDateTime::createFromTimestamp($values['checkIin'])->format('Y-m-d H:i:s') : '';

      // We create a part of the form - the registration status.
      $form['registration_status'] = [
        '#prefix' => '<div id="registration-status">',
        '#suffix' => '</div>',
      ];

      $form['registration_status']['data'] = [
        '#type' => 'html_tag',
        '#title' => $this->t('Mollie customer registration status'),
        '#tag' => 'p',
        '#value' => $status_mollie['message'],
        'date_check' => ['#markup' => $this->t(' date of check: @date', ['@date' => $status_mollie['check'],]),],
      ];

      if ($form_state->get(['step','refresh_check']) !== true) {
        $form['registration_status']['refresh_check'] = [
          '#type' => 'submit',
          '#value' => $this->t('Refresh status @provider', ['@provider' => $label]),
          '#submit' => [[$this, 'handler_refresh_status']],
          '#attributes' => [
            'class' => [$provider . '-button'],
          ],
          '#ajax' => [
            'callback' => [$this, 'ajax_refresh_status'],
            'wrapper' => 'registration-status',
            'progress' => [
              'type' => 'throbber',
              'message' => $this->t('Updating information.'),
            ],
          ],
        ];
      };

      if ($form_state->get(['step','send_completed']) == true) {
        $form['send_data'] = [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t($form_state->get(['step','send_data_info'])),
        ];
      } else if ($form_state->get(['step','send_completed']) !== true && isset($values['status']) && $values['status'] == 'needs-data') {
        $form['send_data'] = $this->getSendDataForm();
      };

    };

    return $form;
  }

  /** {@inheritdoc} */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    if (!$this->currentUser()->isAuthenticated()) {
      $form_state->setErrorByName('submit', $this->t('You need to register or log into an existing account.'));
    };

    $triggering_element = $form_state->getTriggeringElement();

    if (in_array('send', $triggering_element['#parents'])) {
      // Let's check if there is at least one value in the form.
      // Don't send an empty form to Mollie
      $seller_data = $form_state->getValues();
      $seller_data = array_diff_key($seller_data, ['refresh_check'=>'','send'=>'','form_build_id'=>'','form_token'=>'','form_id'=>'','op'=>'',]);
      $seller_data = array_filter($seller_data);
      if (!$seller_data) {
        $form_state->setErrorByName('send_data', $this->t('You must fill in at least one value.'));
      };
    };

  }

  /** {@inheritdoc} */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    /** @var \Drupal\payment_provider\Plugin\PaymentProvider\PaymentProviderMollie $molliePaymentProvider */
    $molliePaymentProvider = $this->managerPaymentProvider->createInstance($this->providerId);

    /** @var \Drupal\payment_provider\Plugin\PaymentProvider\MollieOAuth2Authorize $provider */
    $provider = $molliePaymentProvider->getOAuth2Authorize();
    $response = $provider->getOAuth2Redirect();
     if ($response) {
      $form_state->setResponse($response);
    }else {
      \Drupal::messenger()->addError("Please retry connecting with mollie");
    }
    return;
  }

  /** Mollie registration status update button handler. */
  public function handler_refresh_status(array &$form, FormStateInterface $form_state) {
    $form_state->set(['step', 'refresh_check'], true);
    $form_state->setRebuild();
    return $this->refreshRegistrationStatus();
  }

  /** Ajax handler for updating registration status data in Mollie. */
  public function ajax_refresh_status(array &$form, FormStateInterface $form_state) {
    return $form['registration_status'];
  }

  /** Updates the Mollie registration status. */
  private function refreshRegistrationStatus() {

    /** @var \Drupal\payment_provider\Plugin\PaymentProvider\PaymentProviderMollie
     * $molliePaymentProvider The payment provider Mollie object. */
    $molliePaymentProvider = $this->managerPaymentProvider->createInstance($this->providerId);
    $user_id = $this->currentUser()->id();

    // We will receive the registration data of the seller
    $client = $molliePaymentProvider->getPaymentOAuth2Adapter();
    $client_status = $client->registrationSellerGetStatusConnection($user_id);

    // Let's write down the received data.
    $values = [];
    $date_check = new DrupalDateTime;
    $values['checkIin'] = $date_check->getTimestamp();
    if ($client_status instanceof \Mollie\Api\Resources\Onboarding) {
      $values += [
        'name' => $client_status->name,
        'signedUpAt' => $client_status->signedUpAt,
        'status' => $client_status->status,
        'canReceivePayments' => $client_status->canReceivePayments,
        'canReceiveSettlements' => $client_status->canReceiveSettlements,
      ];
    };

    if ($molliePaymentProvider->setUserExtraInfo($user_id, 'onboarding', $values)) {
      $this->messenger()->addStatus($this->t('Status updated'));
    };

    return $values;
  }

  /** Handler button for send onboarding data in Mollie. */
  public function handler_send_onboarding(array &$form, FormStateInterface $form_state) {

    $user_id = $this->currentUser()->id();
    $seller_data = [];

    // We will receive the data entered by the user from the form.
    $data = $form_state->getValues();

    // Fill array data to send.
    $seller_data = [];
    if ($data['nameOrg']) {$seller_data['organization']['name'] = $data['nameOrg'];};
    if ($data['streetAndNumber']) {$seller_data['organization']['address']['streetAndNumber'] = $data['streetAndNumber'];};
    if ($data['postalCode']) {$seller_data['organization']['address']['postalCode'] = $data['postalCode'];};
    if ($data['city']) {$seller_data['organization']['address']['city'] = $data['city'];};
    if ($data['country']) {$seller_data['organization']['address']['country'] = $data['country'];};
    if ($data['registrationNumber']) {$seller_data['organization']['registrationNumber'] = $data['registrationNumber'];};
    if ($data['vatNumber']) {$seller_data['organization']['vatNumber'] = $data['vatNumber'];};
    if ($data['vatRegulation']) {$seller_data['organization']['vatRegulation'] = $data['vatRegulation'];};
    if ($data['nameProfile']) {$seller_data['profile']['name'] = $data['nameProfile'];};
    if ($data['url']) {$seller_data['profile']['url'] = $data['url'];};
    if ($data['email']) {$seller_data['profile']['email'] = $data['email'];};
    if ($data['descriptionProfile']) {$seller_data['profile']['description'] = $data['descriptionProfile'];};
    if ($data['phone']) {$seller_data['profile']['phone'] = $data['phone'];};
    if ($data['businessCategory']) {$seller_data['profile']['businessCategory'] = $data['businessCategory'];};

    // Let's send the data.
    /** @var \Drupal\payment_provider\Plugin\PaymentProvider\PaymentProviderMollie
     * $molliePaymentProvider object Payment provider Mollie. */
    $molliePaymentProvider = $this->managerPaymentProvider->createInstance($this->providerId);
    $client = $molliePaymentProvider->getPaymentOAuth2Adapter();
    $send_data = $client->registrationSellerSendDataConnection($user_id, $seller_data, false);

    // Note for the form building that we submitted the data.
    if ($send_data) {
      $form_state->set(['step', 'send_data_info'], 'Information: Your data has been sent.');
    } else {
      $form_state->set(['step', 'send_data_info'], 'Information: Data sending error.');
    };

    $form_state->set(['step', 'send_completed'], true);
    $form_state->setRebuild();

    return;
  }
  /** Ajax handler for send onboarding data in Mollie. */
  public function ajax_send_onboarding(array &$form, FormStateInterface $form_state) {
    return $form['send_data'];
  }

  /**
   * Form for sending registration data to Mollie.
   * @return array Form registration
   */
  public function getSendDataForm(): array {

    $form['send_data'] = [
      '#type' => 'details',
      '#open' => true,
      '#title' => $this->t('You can submit your Mollie connection details using the form below.'),
      '#prefix' => '<div id="send-data">',
      '#suffix' => '</div>',
      'send_data_info' => [
        '#markup' => $this->t('Information that the you (merchant) has entered in their dashboard will not be overwritten. Parameters: Please note that even though all parameters are optional, at least one of them needs to be provided in the request.'),
      ],

      'organization' => [
        '#type' => 'details',
        '#title' => $this->t('Data of the organization you want to provide.'),
        'description' => [
          '#markup' => $this->t('Your organization for Mollie.'),
        ],
        'nameOrg' => [
          '#type' => 'textfield',
          '#title' => $this->t('Name'),
          '#size' => 60,
          '#maxlength' => 128,
          '#placeholder' => $this->t('example: Mollie B.V.'),
          '#description' => [
            '#markup' => $this->t('Name of the organization.'),
          ],
          //'#pattern' => 'some-prefix-[A-z]+',
        ],
        'address' => [
          '#type' => 'fieldset',
          '#title' => $this->t('Address of the organization:'),
          'streetAndNumber' => [
            '#type' => 'textfield',
            '#title' => $this->t('Street and number'),
            '#size' => 60,
            '#maxlength' => 64,
            '#placeholder' => $this->t('example: Keizersgracht 126'),
            '#description' => $this->t('The street name and house number of the organization. If an address is provided, this field is required.'),
            '#states' => [
              'required' => [
                [':input[name="city"]' => ['!value' => ''],],
                'or',
                [':input[name="country"]' => ['filled' => true],],
              ],
            ],
          ],
          'postalCode' => [
            '#type' => 'textfield',
            '#title' => $this->t('Postal code'),
            '#size' => 60,
            '#maxlength' => 16,
            '#placeholder' => $this->t('example: 1015 CW'),
            '#description' => $this->t('The postal code of the organization. If an address is provided, this field is required for countries with a postal code system.'),
          ],
          'city' => [
            '#type' => 'textfield',
            '#title' => $this->t('The city of the organization'),
            '#size' => 60,
            '#maxlength' => 32,
            '#placeholder' => $this->t('example: Amsterdam'),
            '#description' => $this->t('The city of the organization. If an address is provided, this field is required.'),
            '#states' => [
              'required' => [
                [':input[name="streetAndNumber"]' => ['filled' => true],],
                'or',
                [':input[name="country"]' => ['filled' => true],],
              ],
            ],
          ],
          'country' => [
            '#type' => 'textfield',
            '#title' => $this->t('Country'),
            '#size' => 60,
            '#maxlength' => 2,
            '#placeholder' => $this->t('example: NL'),
            '#description' => $this->t('The country of the address in @link format. If an address is provided, this field is required.',
              ['@link' => Link::fromTextAndUrl('ISO 3166-1 alpha-2', Url::fromUri('https://en.wikipedia.org/wiki/ISO_3166-1_alpha-2', ['attributes' => ['target' => '_blank'],]))->toString(),]),
            '#states' => [
              'required' => [
                [':input[name="streetAndNumber"]' => ['filled' => true],],
                'or',
                [':input[name="city"]' => ['filled' => true],],
              ],
            ],
          ],
        ],
        'registrationNumber' => [
          '#type' => 'textfield',
          '#title' => $this->t('Registration number'),
          '#size' => 60,
          '#maxlength' => 16,
          '#placeholder' => $this->t('example: 30204462'),
          '#description' => $this->t('The Chamber of Commerce registration number of the company.'),
        ],
        'vatNumber' => [
          '#type' => 'textfield',
          '#title' => $this->t('The VAT number'),
          '#size' => 60,
          '#maxlength' => 16,
          '#placeholder' => $this->t('example: NL815839091B01'),
          '#description' => $this->t('The VAT number of the company, if based in the European Union. The VAT number will be checked with the @VIES service by Mollie.',
            ['@VIES' => Link::fromTextAndUrl('VIES', Url::fromUri('http://ec.europa.eu/taxation_customs/vies', ['attributes' => ['target' => '_blank'],]))->toString(),]),
        ],
        'vatRegulation' => [
          '#type' => 'textfield',
          '#title' => $this->t('VAT regulation'),
          '#size' => 60,
          '#maxlength' => 16,
          '#placeholder' => $this->t('example: shifted'),
          '#description' => $this->t('The organization\'s VAT regulation, if based in the European Union. Either <b>"<code>shifted</code>"</b> (VAT is shifted) or <b>"<code>dutch</code>"</b> (Dutch VAT rate) is accepted.'),
        ],
      ],

      'profile' => [
        '#type' => 'details',
        '#title' => $this->t('Data of the payment profile you want to provide.'),
        'description' => [
          '#markup' => $this->t('Your payment profile for Mollie:'),
        ],
        'nameProfile' => [
          '#type' => 'textfield',
          '#title' => $this->t('The profile name'),
          '#size' => 60,
          '#maxlength' => 32,
          '#placeholder' => $this->t('example: Mollie'),
          '#description' => $this->t('The profile name should reflect the trade name or brand name of the profile\'s website or application.'),
        ],
        'url' => [
          '#type' => 'textfield',
          '#title' => $this->t('The URL to the profile\'s website or application'),
          '#size' => 60,
          '#maxlength' => 32,
          '#placeholder' => 'example: https://www.mollie.com',
          '#description' => $this->t('The URL to the profile\'s website or application. The URL must be compliant to @RFC3986 with the exception that we only accept URLs with <b>"<code>http://</code>"</b> or <b>"<code>https://</code>"</b> schemes and domains that contain a TLD. URLs containing an <b>"<code>@</code>"</b> are not allowed.',
            ['@RFC3986' => Link::fromTextAndUrl('RFC3986', Url::fromUri('https://tools.ietf.org/html/rfc3986', ['attributes' => ['target' => '_blank'],]))->toString(),]),
        ],
        'email' => [
          '#type' => 'textfield',
          '#title' => $this->t('The e-mail'),
          '#size' => 60,
          '#maxlength' => 32,
          '#placeholder' => 'example: info@mollie.com',
          '#description' => $this->t('The email address associated with the profile\'s trade name or brand.'),
        ],
        'descriptionProfile' => [
          '#type' => 'textarea',
          '#title' => $this->t('Description'),
          '#rows' => 7,
          '#maxlength' => 1024,
          '#placeholder' => $this->t('Enter description...'),
          '#description' => $this->t('A description of what kind of goods and/or products will be offered via the payment profile.'),
        ],
        'phone' => [
          '#type' => 'textfield',
          '#title' => $this->t('The phone number'),
          '#size' => 60,
          '#maxlength' => 32,
          '#placeholder' => 'example: +31208202070',
          '#description' => $this->t('The phone number associated with the profile\'s trade name or brand. Must be in the @E.164 format.',
            ['@E.164' => Link::fromTextAndUrl('E.164', Url::fromUri('https://en.wikipedia.org/wiki/E.164', ['attributes' => ['target' => '_blank'],]))->toString(),]),
        ],
        'businessCategory' => [
          '#type' => 'textfield',
          '#title' => $this->t('Business category'),
          '#size' => 60,
          '#maxlength' => 32,
          '#placeholder' => 'example: MONEY_SERVICES',
          '#description' => [
            '#markup' => $this->t('The industry associated with the profile\'s trade name or brand. Please refer to the documentation of the business @category for more information on which values are accepted.',
              ['@category' => Link::fromTextAndUrl('category', Url::fromUri('https://docs.mollie.com/overview/common-data-types', ['fragment' => 'business-category', 'attributes' => ['target' => '_blank'],]))->toString(),]),
          ],
        ],
      ],

      'send' => [
        '#type' => 'submit',
        '#value' => $this->t('Send data to @provider', ['@provider' => $this->providerId]),
        '#submit' => [[$this, 'handler_send_onboarding']],
        '#attributes' => [
          'class' => [$this->providerId . '-button'],
        ],
        '#ajax' => [
          'callback' => [$this, 'ajax_send_onboarding'],
          'wrapper' => 'send-data',
          'progress' => [
            'type' => 'throbber',
            'message' => $this->t('Updating information.'),
          ],
        ],
        ],
    ];

    return $form['send_data'];
  }




}
