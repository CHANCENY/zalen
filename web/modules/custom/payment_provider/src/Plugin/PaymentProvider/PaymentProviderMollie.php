<?php

/**
 * @file
 * class plugin payment provider Mollie.
 */

namespace Drupal\payment_provider\Plugin\PaymentProvider;

use Drupal\payment_provider\Annotation\PaymentProvider;
use Drupal\payment_provider\PaymentProviderPluginBase;
use Drupal\payment_provider\Plugin\PaymentProvider\MollieConfigValidator;
use Drupal\payment_provider\Plugin\PaymentProvider\MollieOAuth2Authorize;
use Drupal\payment_provider\Plugin\PaymentProvider\MolliePaymentClient;
use Drupal\payment_provider\Plugin\PaymentProvider\HelperFormSettingsField;
use Drupal\Core\Url;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\BaseResource;
use Mollie\Api\Exceptions\ApiException as MollieApiException;

/**
 * @PaymentProvider(
 *   id="mollie",
 * )
 */
class PaymentProviderMollie extends PaymentProviderPluginBase
{

    use LoggerChannelTrait;
    use MessengerTrait;

    /**
     * Available payment methods Mollie.
     * @const array
     */
    const AVAILABLE_PAYMENT_METHODS = [
        'payment' => 'Payments API v2: Create payment. (Support for authorization via profile, organization and app keys.)',
        'order' => '(not implemented) Orders API v2: Create orders - have specific parameters suitable for stores (multiple items, delivery, etc.). (support via profile, organization, app.)',
        'payment_link' => '(not implemented) Payment links API v2: Create payment link. (support via profile, organization, app.)',
        'captures' => '(not implemented) Captures API v2: Reserve funds (only with Klarna Pay). (support via profile, organization, app.)',
        'subscription' => 'Recurring + Subscriptions API v2: Schedule recurring payments. (support via profile, organization, app.)',
        'pay_reservation' => 'It is - Test! Payments reservation with OAuth2 and fees.',
    ];
    /**
     * Mollie support authorize via OAuth2.
     * @const bool
     */
    const SUPPORT_OAUTH2 = TRUE;

    /**
     * Mollie config validator.
     * @var \Drupal\payment_provider\Plugin\PaymentProvider\MollieConfigValidator
     */
    protected $validator;
    /**
     * Mollie OAuth2 authorize.
     * @var \Drupal\payment_provider\Plugin\PaymentProvider\MollieOAuth2Authorize
     */
    protected $authorizeOAuth2;

    /**
     * Mollie payment adapter for client.
     * @var \Drupal\payment_provider\Plugin\PaymentProvider\MolliePaymentClient
     */
    protected $paymentAdapter;
    /**
     * Mollie payment adapter for client with OAuth2.
     * @var \Drupal\payment_provider\Plugin\PaymentProvider\MolliePaymentClient
     */
    protected $paymentOAuth2Adapter;

    /**
     * Mollie payment client.
     * @var \Mollie\Api\MollieApiClient $withClient
     */
    protected $withClient;
    /**
     * Attributes Mollie payment client.
     * @var array $withClientAttributes
     * Attributes for Mollie payment client that are set when creating an object "withClient".
     */
    protected $mollie;// mollie
    protected $withClientAttributes = [];

    /** {@inheritdoc} By default, it writes all these variables to the plugin local properties of the same name. */
    public function __construct(array $configuration, $plugin_id, $plugin_definition)
    {
        parent::__construct($configuration, $plugin_id, $plugin_definition);
        $this->validator = new MollieConfigValidator;

        $this->mollie = new \Mollie\Api\MollieApiClient();
        $this->mollie->setApiKey("test_wubDmDr9RdmJn3TKrQgHpvxz7Jn9vD");
        $this->mollie->setAccessToken("access_C6n43U46F7gPpw523k2hkGjnDeVKybzbdR2znxUK");

    }

    /** {@inheritdoc} */
    public function getProviderLabel()
    {
        return 'Mollie';
    }

    /**
     * Initial status when creating a new invoice.
     * @var string
     */
    const STATUS_NEW_INVOIS = 'new';

    /**
     * Returns Mollie config validator.
     * @return \Drupal\payment_provider\Plugin\PaymentProvider\MollieConfigValidator|null
     */
    public function getValidator(): ?MollieConfigValidator
    {
        return $this->validator;
    }
    /**
     * Returns Mollie OAuth2 Authorize object.
     * @return \Drupal\payment_provider\Plugin\PaymentProvider\MollieOAuth2Authorize|null
     */
    public function getOAuth2Authorize(): ?MollieOAuth2Authorize
    {
        if (!isset($this->authorizeOAuth2)) {
            $this->authorizeOAuth2 = new MollieOAuth2Authorize;
        }
        ;
        return $this->authorizeOAuth2;
    }

    /**
     * Returns Mollie Payment Client object.
     * @return \Drupal\payment_provider\Plugin\PaymentProvider\MolliePaymentClient|null
     */
    public function getPaymentAdapter(): ?MolliePaymentClient
    {
        if (!isset($this->paymentAdapter)) {
            $this->paymentAdapter = new MolliePaymentClient;
        }
        ;
        return $this->paymentAdapter;
    }
    /**
     * Returns Mollie Payment Client with OAuth2 object.
     * @return \Drupal\payment_provider\Plugin\PaymentProvider\MolliePaymentClient|null
     */
    public function getPaymentOAuth2Adapter(): ?MolliePaymentClient
    {
        if (!isset($this->paymentOAuth2Adapter)) {
            $this->paymentOAuth2Adapter = new MolliePaymentClient($this->getOAuth2Authorize());
        }
        ;
        return $this->paymentOAuth2Adapter;
    }

    /**
     * Returns Mollie Payment Сlient object.
     * @param string $type Type connectivity Payment Client.
     * - Allowed values: "profile", "organization", "app".
     * @param string|int $user_id If the connection for the seller.
     * User id for with create Client
     * @return bool True if \Mollie\Api\MollieApiClient was created.
     * Created object or null in case of failure.
     */
    public function withPaymentClient(string $type, $user_id = null): bool
    {
        switch ($type) {
            case 'profile':
                $this->withClientAttributes = ['type' => 'profile',];
                $this->withClient = (new MolliePaymentClient)->getClient();
                break;
            case 'organization':
                $this->withClientAttributes = ['type' => 'organization',];
                $this->withClient = (new MolliePaymentClient($this->getOAuth2Authorize()))->getOrganizationClient();
                break;
            case 'app':
                if (!$user_id) {
                    return FALSE;
                }
                ;
                $this->withClientAttributes = ['type' => 'app', 'userId' => $user_id];
                $this->withClient = (new MolliePaymentClient($this->getOAuth2Authorize()))->getClientOAuth2($user_id);
                break;
            default:
                return FALSE;
        }
        ;
        return TRUE;
    }
    /**
     * Returns Mollie Payment Client object.
     * @param string $payment_method
     * Payment method. Must be available in methods Payment Client.
     * @param array $payment_data
     * An array with mutable values is usually generated in the field at the time of payment creation.
     * - For payment it is: ["amount"=>["currency"=>"string","value"=>"string",] ,"description"=>"string", "redirectUrl"=>"string", ?access_token+"profileId"=>"string",]
     * - For subscription it is: ["amount"=>["currency"=>"string","value"=>"string",] ,"description"=>"string", "interval"=>"string", ?access_token+"profileId"=>"string",]
     * @param array $payment_settings
     * Array with settings usually configured in the settings fields.
     * @param string|int|null $user_id_cst
     * If the connection for create a subscription.
     * User id of the API customer's, for with preparation Client.
     * @return \Mollie\Api\Resources\BaseResource|null from \Mollie\Api\MollieApiClient
     * Example Payment, not Clon. Created object or null in case of failure.
     */
    public function payWithClient(string $payment_method, array $payment_data, array $payment_settings = [], $user_id_cst = null): ?BaseResource
    {


        $data = $payment_data;
        switch ($payment_method) {
            case 'payment':
                $path_webhook = Url::fromRoute(
                    'payment_provider.mollie_webhook.payments',
                    [],
                    [
                        'https' => \Drupal::request()->getScheme() == 'https' ? TRUE : FALSE,
                        'absolute' => TRUE,
                    ]
                )->toString();
                $data['webhookUrl'] = $path_webhook;
                $data['webhookUrl'] = "https://ea2d-59-144-173-81.ngrok-free.app/en/payment_provider/mollie/webhook/payments";
                // if (in_array($this->withClientAttributes['type'], ['organization', 'app'])) {
                //     // REQUIRED for Access Tokens. The website profile's unique identifier, example (pfl_3RkSN1zuPE).
                //     $data['profileId'] = $this->validator->get('profile_id');
                //     // Use test mode?
                //     if ($this->validator->useTestMode()) {
                //         $data['testmode'] = TRUE;
                //     }
                //     ;
                // }
                $data += ['method' => null, 'locale' => 'en_US',];
                $data['profileId'] = 'pfl_v3RbhjxvrH';
                $data['testmode'] = TRUE;
                // Create a payment.
                //$payment = clone $this->withClient;
                // $payment = $this->withClient;
                // $payment = new \payment\Api\MollieApiClient();
                // $payment->setApiKey("paymentst_dHar4XY7LxsDOtmnkVtjNVWXLSlXsM");
                try {
                    // Create payment Mollie.
                    // $payment = $this->mollie->payments->get("tr_Db8sdYc9ZL");

                    $pay = $this->mollie->payments->create($data);// data
                    return $pay;
                } catch (MollieApiException $e) {
                    // Mollie\Api\Exceptions\ApiException: [2022-01-09T21:28:20+0200] Error executing API call (422: Unprocessable Entity):
                    // A website profile is required for payments. Field: profileId.
                    // Documentation: https://docs.mollie.com/overview/handling-errors
                    // in Mollie\Api\Exceptions\ApiException::createFromResponse() (line 107 of vendor\mollie\src\Exceptions\ApiException.php).
                    // "[2022-01-09T21:44:09+0200] Error executing API call (403: Forbidden):
                    // Not all required permissions (payments.write) for accessing this resource were granted..
                    // Documentation: https://docs.mollie.com/oauth/permissions"
                    \Drupal::logger('mollie')->error("API Mollie call failed (in progress create payment): " . htmlspecialchars($e->getMessage()));
                }
                ;
                break;
            case 'order':
                break;
            case 'payment_link':
                break;
            case 'captures':
                break;
            case 'subscription':
                if (!$user_id_cst) {
                    return NULL;
                }
                ;
                $path_webhook = Url::fromRoute(
                    'payment_provider.mollie_webhook.subscriptions',
                    [],
                    [
                        'https' => \Drupal::request()->getScheme() == 'https' ? TRUE : FALSE,
                        'absolute' => TRUE,
                    ]
                )->toString();
                $data['webhookUrl'] = $path_webhook;
                if (in_array($this->withClientAttributes['type'], ['organization', 'app'])) {
                    // REQUIRED for Access Tokens. The website profile's unique identifier, example (pfl_3RkSN1zuPE).
                    $data['profileId'] = $this->validator->get('profile_id');
                    // Use test mode?
                    if ($this->validator->useTestMode()) {
                        $data['testmode'] = TRUE;
                    }
                    ;
                }
                ;

                // Create a subscription Mollie.
                $mollieClient = $this->withClient;
                try {

                    // Getting customer data
                    if (!$customer_info = $this->getCustomersInfo($user_id_cst)) {
                        $customer = $this->createCustomers($user_id_cst);
                        $customer_info = isset($customer->id) ? ['id' => $customer->id] : null;
                        if (!$customer_info) {
                            return NULL;
                        }
                        ;
                    }
                    ;

                    // Get customer Mollie.
                    if (empty($customer)) {
                        $customer = $mollieClient->customers->get($customer_info['id']);
                    }
                    ;

                    // Check if the customer has active mandates.
                    if (!$this->hasCustomerActiveMmandates('', '', '', $customer)) {
                        return NULL;
                    }
                    ;
                    //$customerClient = $mollieClient->customers->get(['id' => $customer_info['id'],'testmode' => true,]);

                    // Check if the customer has active mandates.
                    //The subscription will be either pending or active depending on whether the customer has a pending or valid mandate.
                    //If the customer has no mandates an error is returned.
                    //You should then set up a "first payment" for the customer.
                    ////$mandates = $customerClient->mandates();
                    ////if ($mandates->count < 1) {
                    ////  $this->setCustomerFirstPayment($user_id_cst);
                    ////} else {
                    ////  // If there's at least one mandate with a status set to "valid" then continue.
                    ////  $mandates_valid = false;
                    ////  $mandates_list = $mandates->_embedded->mandates;
                    ////  foreach ($mandates_list as $v) {
                    ////    if ($v['status'] == 'valid') {$mandates_valid = true;break;};
                    ////  };
                    ////};

                    $subscription = $customer->createSubscription($data);
                    return $subscription;

                } catch (MollieApiException $e) {
                    \Drupal::logger('mollie')->error("API Mollie call failed (in progress create subscription): " . htmlspecialchars($e->getMessage()));
                }
                ;
                break;
            default:
                return NULL;
        }
        ;




        return NULL;
    }

    /**
     * Returns array Mollie Client Payment methods.
     * @return array
     */
    public function getPaymentMethods(): array
    {
        return self::AVAILABLE_PAYMENT_METHODS;
    }
    /**
     * Returns TRUE if Mollie Payment provider has support authorize via OAuth2.
     * @return bool support authorize via OAuth2
     */
    public function isSupportOAuth2(): bool
    {
        return self::SUPPORT_OAUTH2;
    }

    /**
     * Returns object for create help form in setting.
     * @return \Drupal\payment_provider\Plugin\PaymentProvider\HelperFormSettingsField object for form.
     */
    public function getHelperFormSettingsField(): object
    {
        $help_form = new HelperFormSettingsField($this);
        return $help_form;
    }

    /**
     * Returns an array with additional information for the current user in the configuration.
     * @param string|int $user_id
     * ID of the checked user.
     * @param string $key
     * The key by which the values are read.
     * - Allowed values: 'onboarding', 'customer',
     * @return array|null
     * - Array with additional user data.
     * - An empty array if the information is not complete.
     * - NULL if no such key is available or an invalid key is given.
     */
    public function getUserExtraInfo($user_id, string $key): ?array
    {

        $allowed_key = ['onboarding', 'customer',];
        if (!in_array($key, $allowed_key)) {
            return NULL;
        }
        ;

        /** @var \Drupal\user\UserData $user_data */
        $user_data = \Drupal::service('user.data');
        $values = $user_data->get('payment_provider_' . $this->pluginId, $user_id, $key);

        return $values;
    }

    /**
     * Returns an array with additional information for the current user in the configuration.
     * @param string|int $user_id
     * ID of the checked user.
     * @param string $key
     * The key by which the values are written.
     * - Allowed values: 'onboarding', 'customer',
     * @param array $values
     * Array with additional user data.
     * @return bool|null
     * - TRUE if the information is saved.
     * - FALSE if the information could not be saved.
     * - NULL if no such key exists (invalid key).
     */
    public function setUserExtraInfo($user_id, string $key, array $values): ?bool
    {

        $allowed_key = ['onboarding', 'customer',];
        if (!in_array($key, $allowed_key)) {
            return NULL;
        }
        ;

        /** @var \Drupal\user\UserData $user_data */
        $user_data = \Drupal::service('user.data');
        if (!$user_data) {
            return FALSE;
        }
        ;
        $user_data->set('payment_provider_' . $this->pluginId, $user_id, $key, $values);

        return TRUE;
    }

    /**
     * @param string|int $user_id
     * The id verifiable user
     * @return bool|string
     * - TRUE if the seller can accept payments.
     * - String with error messages otherwise. (A string describing the error in case of failure).
     */
    public function canAcceptPayments($user_id)
    {

        $values = $this->getUserExtraInfo($user_id, 'onboarding');
        if (empty($values)) {
            return 'Data on the seller\'s connection to the payment provider is not available.';
        } else if (isset($values['canReceivePayments'])) {
            if ($values['canReceivePayments'] == true) {
                return TRUE;
            } else if ($values['canReceivePayments'] == false) {
                return 'The seller cannot accept payments through the current payment provider.';
            }
            ;
        }
        ;

        return 'Checking the seller\'s ability to accept payments through the current provider ended with an unspecified error.';
    }

    /**
     * Array with data with keys: ('id', 'mode', 'createdAt').
     * @param string|int $user_id
     * The id user for which to get the customers identifier.
     * @return array|null
     * - Array with data customer if the user has customer's data.
     * - NULL otherwise.
     */
    public function getCustomersInfo($user_id)
    {
        // static save for convenience and performance
        static $customers_info = [];
        if (isset($customers_info[$user_id])) {
            return $customers_info[$user_id];
        } else {
            $customers_info[$user_id] = $this->getUserExtraInfo($user_id, 'customer');
        }
        ;

        return $customers_info[$user_id];
        //return $this->getUserExtraInfo($user_id, 'customer');
    }

    /**
     * @param string|int $user_id
     * The id user for which to get the customers identifier.
     * @param array $values
     * Array with customer data for user.
     * @return array|null
     * - Array with data customer if saved customer's data.
     * - Empty array if there is no data.
     * - Null otherwise.
     */
    public function setCustomersInfo($user_id, array $values): ?array
    {
        if ($this->setUserExtraInfo($user_id, 'customer', $values) == TRUE) {
            return $values;
        }
        return NULL;
    }

    /**
     * @param string|int $user_id
     * The ID user for which an customers identifier is being created.
     * @param string|null $metod 'profile' default.
     * Connection method (if need create Client).
     * - Possible values: "profile", "organization", "app".
     * @param string|int|null $app_owner_id
     * If connection method is 'app'. The ID user for which the app is created (must be available the token OAuth2).
     * @param \Mollie\Api\MollieApiClient|null $mollieClient
     * Null by default. Mollie client, if don't need to create a new one.
     * @return \Mollie\Api\Resources\Customer|null Objeck or NULL.
     * - Objeck with data customers.
     * - NULL if error otherwise.
     */
    public function createCustomers($user_id, string $metod = 'profile', $app_owner_id = null, ?MollieApiClient $mollieClient = null): ?\Mollie\Api\Resources\Customer
    {

        //if (!$metod) {$metod = 'profile';};
        if (!in_array($metod, ['profile', 'organization', 'app'])) {
            return NULL;
        }
        ;

        $user_account = \Drupal\user\Entity\User::load($user_id);
        if (!$user_account) {
            return NULL;
        }
        ;

        if (!$mollieClient) {
            switch ($metod) {
                case 'profile':
                    $mollieClient = $this->getPaymentAdapter()->getClient();
                    break;
                case 'organization':
                    $mollieClient = $this->getPaymentOAuth2Adapter()->getOrganizationClient();
                    break;
                case 'app':
                    if (!$app_owner_id) {
                        return NULL;
                    }
                    ;
                    $mollieClient = $this->getPaymentOAuth2Adapter()->getClientOAuth2($app_owner_id);
                    break;
                default:
                    return NULL;
            }
            ;
            if (!$mollieClient) {
                return NULL;
            }
            ;
        }
        ;

        $data = [
            'name' => $user_account->getDisplayName(),
            'email' => $user_account->getEmail(),
            'locale' => 'en_US',
            'metadata' => [
                'user_id' => $user_id,
                'access_method' => $metod,
            ],
        ];
        if ($app_owner_id) {
            $data['metadata']['app_owner_id'] = $app_owner_id;
        }
        ;
        if (in_array($metod, ['organization', 'app'])) {
            // Use test mode?
            if ($this->validator->useTestMode()) {
                $data['testmode'] = TRUE;
            }
            ;
        }
        ;

        try {

            $customer = $mollieClient->customers->create($data);

            $customer_data = [
                'id' => $customer->id,
                'mode' => $customer->mode,
                'createdAt' => $customer->createdAt,
                'access_method' => $customer->metadata->access_method,
            ];
            $this->setCustomersInfo($user_id, $customer_data);
            return $customer;

        } catch (MollieApiException $e) {
            \Drupal::logger('mollie')->error("API Mollie call failed: " . htmlspecialchars($e->getMessage()));
        }
        ;

        return NULL;

    }

    /**
     * @param string $transaction.
     * Payment transaction type.
     * - Allowed values: "payment" or "order".
     * @param string $amount.
     * For credit card and PayPal payments, you can create a payment with a zero amount.
     * - Allowed values: "0.01" or "0.00".
     * @param string $purpose.
     * Additional info for convenience purpose of payment. Example "Create VIP subscription".
     * @param string|int $user_id.
     * User ID, for info and to get the "customerId" parameter, for which need to receive the first payment.
     * @param \Mollie\Api\Resources\Customer|null $customerClient
     * (Optional if available) Object Customer for which need to create first payment.
     * @param string|null $metod "profile" default.
     * Connection method (if need create Client).
     * - Possible values: "profile", "organization", "app".
     * @param string|int|null $app_owner_id NULL by default.
     * In app use cases, it may be necessary to create mollieClient, for the seller to get the first payment.
     * Otherwise, the first payment is created for the user (website owner (data that is specified in the Mollie API)).
     * @param \Mollie\Api\MollieApiClient|null $mollieClient NULL by default.
     * (Optional if available) Object Mollie API client, to to receive the first payment.
     * @return \Mollie\Api\Resources\Payment|\Mollie\Api\Resources\Order|null
     * Object Payment on success or NULL on error otherwise.
     */

    public function getCustomerFirstPayment(
        string $amount, string $purpose,
        $user_id,
        string $method = 'profile',
        string $transaction = 'payment',
        $app_owner_id = null,
        ?\Mollie\Api\Resources\Customer $customerClient = null,
        ?MollieApiClient $mollieClient = null
    ) {
        if (!$transaction || !in_array($transaction, ['payment', 'order'])) {
            $this->getLogger('payment_provider')->error('For the first payment, only ("payment" or "order") "transaction" methods are available.');
            return NULL;
        }
        ;

        if (!$amount || !in_array($amount, ['0.01', '0.00'], true)) {
            $this->getLogger('payment_provider')->error('Only ("0.01" or "0.00") "amount" are available for the first payment. (and "0.00" only for credit card and PayPal payments).');
            return NULL;
        }
        ;

        // Some checks if the customerClient object exists
        // For a Client Object, we can only create a payment or a subscription
        if (!empty($customerClient) && $transaction == 'order') {
            $this->getLogger('payment_provider')->error('For the first payment, object "Customer" does not support create a orders only a payment or a subscription.');
            return NULL;
        }
        ;

        // Get Mollie's client id.
        // If there is no client, we will create it.
        if (!$customer_info = $this->getCustomersInfo($user_id, )) {
            if ($customerClient = $this->createCustomers($user_id, )) {
                $customer_info['id'] = $customerClient->id;
            } else {
                $this->getLogger('payment_provider')->error('Failed to get customer ID for first payment.');
                return NULL;
            }
            ;
        }
        ;

        // Check if the client object does not belong to this user.
        if (!empty($customerClient)) {
            if ($customer_info['id'] !== $customerClient->id) {
                $this->getLogger('payment_provider')->error('For the first payment, identifiers "customerId" from object "Customer" and received from given user_id do not match.');
                return NULL;
            }
            ;
        }
        ;

        /** @var \Drupal\Core\Routing\RouteMatchInterface $routeMatch */
        $routeMatch = \Drupal::service('current_route_match');

        $redirect_url = Url::fromRoute(
            $routeMatch->getRouteName(),
            [],
            [
                'https' => \Drupal::request()->getScheme() == 'https' ? TRUE : FALSE,
                'absolute' => TRUE,
            ]
        )->toString();
        $webhook_url = Url::fromRoute(
            'payment_provider.mollie_webhook.other',
            [
                'pay' => $transaction,
                'context' => 'first',
            ],
            [
                'https' => \Drupal::request()->getScheme() == 'https' ? TRUE : FALSE,
                'absolute' => TRUE,
            ]
        )->toString();

        $data = array(
            'amount' => array(
                'value' => $amount,
                'currency' => 'EUR',
            ),
            'sequenceType' => \Mollie\Api\Types\SequenceType::SEQUENCETYPE_FIRST,
            'description' => 'First payment',
            'redirectUrl' => $redirect_url,
            'webhookUrl' => $webhook_url,
        );

        //  We will add additional information to the payment for convenience.
        $data['metadata'] = [
            'user_id' => $user_id,
            'purpose' => $purpose,
        ];
        // If the payment will be organized not through the client's objeck, then "customerId" should be added.
        if (!$customerClient) {
            $data['customerId'] = $customer_info['id'];
        }
        ;
        // If we use OAuth2.
        if (in_array($method, ['organization', 'app'])) {
            // REQUIRED for Access Tokens. The website profile's unique identifier, example (pfl_3RkSN1zuPE).
            $data['profileId'] = $this->validator->get('profile_id');
            // Use test mode?
            if ($this->validator->useTestMode()) {
                $data['testmode'] = TRUE;
            }
            ;
        }
        ;
        // For the free method, we can use only 2 types of payments "creditcard" and "paypal".
        if ($amount == '0.00') {
            $data['method'] = ['creditcard', 'paypal'];
        }
        ;

        if ($customerClient && $transaction = 'payment') {
            //https://github.com/mollie/mollie-api-php/blob/master/examples/customers/create-customer-first-payment.php
            $first_payment = $customerClient->createPayment($data);
        } else {

            if (!$mollieClient) {
                switch ($method) {
                    case 'profile':
                        $mollieClient = $this->getPaymentAdapter()->getClient();
                        break;
                    case 'organization':
                        $mollieClient = $this->getPaymentOAuth2Adapter()->getOrganizationClient();
                        break;
                    case 'app':
                        if (!$app_owner_id) {
                            return NULL;
                        }
                        ;
                        $mollieClient = $this->getPaymentOAuth2Adapter()->getClientOAuth2($app_owner_id);
                        break;
                    default:
                        $this->getLogger('payment_provider')->error('An unknown connection method was given for the first payment.');
                        return NULL;
                }
                ;
            }
            ;

            if ($mollieClient) {
                //https://docs.mollie.com/payments/recurring
                if ($transaction == 'payment') {
                    $first_payment = $mollieClient->payments->create($data);
                } else if ($transaction == 'order') {
                    $first_payment = $mollieClient->orders->create($data);
                }
                ;
            }
            ;

        }
        ;

        return $first_payment;
    }

    /**
     * @param string $transaction.
     * Payment transaction type.
     * - Allowed values: "payment" or "order".
     * @param string $amount.
     * For credit card and PayPal payments, you can create a payment with a zero amount.
     * - Allowed values: "0.01" or "0.00".
     * @param string $purpose.
     * Additional info for convenience purpose of payment. Example "Create VIP subscription".
     * @param string|int $user_id.
     * User ID, for info and to get the "customerId" parameter, for which need to receive the first payment.
     * @param \Mollie\Api\Resources\Customer|null $customerClient
     * (Optional if available) Object Customer for which need to create first payment.
     * @param string|null $metod "profile" default.
     * Connection method (if need create Client).
     * - Possible values: "profile", "organization", "app".
     * @param string|int|null $app_owner_id NULL by default.
     * In app use cases, it may be necessary to create mollieClient, for the seller to get the first payment.
     * Otherwise, the first payment is created for the user (website owner (data that is specified in the Mollie API)).
     * @param \Mollie\Api\MollieApiClient|null $mollieClient NULL by default.
     * (Optional if available) Object Mollie API client, to to receive the first payment.
     * @return \Drupal\Core\Routing\TrustedRedirectResponse|null
     * Object for redirect with checkout Url on success or NULL if error otherwise.
     */
    public function getRedirectCustomerFirstPayment(
        string $amount,
        string $purpose,
        $user_id,
        string $method = 'profile',
        string $transaction = 'payment',
        $app_owner_id = null,
        ?\Mollie\Api\Resources\Customer $customerClient = null,
        ?MollieApiClient $mollieClient = null
    ): ?TrustedRedirectResponse {

        $payment = $this->getCustomerFirstPayment($amount, $purpose, $user_id, $method,$transaction, $app_owner_id, $customerClient, $mollieClient);
        if (empty($payment)) {
            return NULL;
        }
        ;

        if ($payment->status !== 'open') {
            $this->getLogger('payment_invoice')->error('First payment created for user:"@id" has status:"@status" - it is not "open".', [
                '@id' => $user_id,
                '@status' => $payment->status,
            ]);
            $this->messenger()->addError('Failed to create first payment. Has the status - "' . $payment->status . '".');
            return null;
        }
        ;

        $url = $payment->getCheckoutUrl();
        return new \Drupal\Core\Routing\TrustedRedirectResponse($url, '303');
    }

    /**
     * @param string|int|null $user_id
     * User ID for which need to check mandates on 'valid'.
     * @param string|null $metod 'profile' default.
     * Connection method (if need create Client) (use data that is specified in the Mollie API)).
     * - Possible values: "profile", "organization", "app".
     * @param string|int|null $app_owner_id
     * If connection method is 'app'. The ID user for which the app is created (must be available the token OAuth2).
     * @param \Mollie\Api\Resources\Customer|null $customerClient
     * Object for which need to check mandates.
     * @return bool|null
     * - TRUE on success, FALSE if missing value = 'valid'.
     * - NULL on error otherwise.
     */
    public function hasCustomerActiveMmandates(
        $user_id = '',
        $metod = 'profile',
        $app_owner_id = '',
        ?\Mollie\Api\Resources\Customer $customerClient = null
    ): ?bool {

        if (!$customerClient) {
            $mandates_list = $this->getCustomerAllMandates($metod, $user_id, $app_owner_id);
        } else {
            $mandates_list = $this->getCustomerAllMandates('', '', '', $customerClient);
        }
        ;

        if (is_array($mandates_list)) {

            $use_test = $this->validator->useTestMode();

            if (empty($mandates_list)) {
                return FALSE;
            }
            ;

            foreach ($mandates_list as $v) {
                if ($v['status'] == 'valid') {
                    if ($use_test === FALSE && $v['mode'] !== 'test') {
                        continue;
                    }
                    ;
                    return TRUE;
                }
                ;
            }
            ;

            return FALSE;
        }
        ;

        return NULL;
    }

    /**
     * @param string|null $metod 'profile' default.
     * Connection method (if need create Client) (use data that is specified in the Mollie API)).
     * - Possible values: "profile", "organization", "app".
     * @param string|int|null $user_id
     * User ID for which need to get mandates.
     * @param string|int|null $app_owner_id
     * If connection method is 'app'. The ID user for which the app is created (must be available the token OAuth2).
     * @param \Mollie\Api\Resources\Customer|null $customerClient
     * Object for which need to get mandates.
     * @return array|null Array or NULL.
     * - TRUE with data.
     * - NULL on error otherwise.
     */
    public function getCustomerAllMandates(
        string $metod = 'profile',
        $user_id = '',
        $app_owner_id = '',
        ?\Mollie\Api\Resources\Customer $customerClient = null
    ): ?array {

        //// static save for convenience and performance
        //static $customer_all_mandates = [];
        //if (isset($customer_all_mandates[$user_id])) {
        //  return $customer_all_mandates[$user_id];
        //};

        //$mollieClient->mandates->listForId($customer_info['id']);//listFor()

        //if ($metod = 'app' && !$app_owner_id) {return NULL;};
        //if (!in_array($metod, ['profile', 'organization', 'app'])) {return NULL;};

        if (!$customerClient) {
            if (!$user_id) {
                return NULL;
            }
            ;

            // Getting customer data
            $customer_info = $this->getCustomersInfo($user_id);

            if (!$customer_info) {

                switch ($metod) {
                    case 'profile':
                        $customerClient = $this->createCustomers($user_id, 'profile');
                        break;
                    case 'organization':
                        $customerClient = $this->createCustomers($user_id, 'organization');
                        break;
                    case 'app':
                        if (!$app_owner_id) {
                            return NULL;
                        }
                        ;
                        $customerClient = $this->createCustomers($user_id, 'app', $app_owner_id);
                        break;
                    default:
                        return NULL;
                }
                ;

            } else {

                switch ($metod) {
                    case 'profile':
                        $mollieClient = $this->getPaymentAdapter()->getClient();
                        break;
                    case 'organization':
                        $mollieClient = $this->getPaymentOAuth2Adapter()->getOrganizationClient();
                        break;
                    case 'app':
                        if (!$app_owner_id) {
                            return NULL;
                        }
                        ;
                        $mollieClient = $this->getPaymentOAuth2Adapter()->getClientOAuth2($app_owner_id);
                        break;
                    default:
                        return NULL;
                }
                ;


                // need test
                //if (in_array($metod, ['organization', 'app']) && $this->validator->useTestMode()) {
                //  // Use test mode?
                //  $mandates = $mollieClient->mandates->listForId($customer_info['id'], ['testmode' => true,]);
                //} else {
                //  $mandates = $mollieClient->mandates->listForId($customer_info['id']);
                //};
                if (in_array($metod, ['organization', 'app']) && $this->validator->useTestMode()) {
                    // Use test mode?
                    $customerClient = $mollieClient->customers->get($customer_info['id'], ['testmode' => true,]);
                } else {
                    $customerClient = $mollieClient->customers->get($customer_info['id']);
                }
                ;

            }
            ;

        }
        ;

        if (!$customerClient) {
            return NULL;
        }
        ;
        //The customer has a "pending" or "valid" mandate.
        //If the customer has no mandates an error is returned.
        //You should then set up a "first payment" for the customer.
        /** @var \Mollie\Api\Resources\MandateCollection $mandates */
        $mandates = $customerClient->mandates();

        if ($mandates->count < 1) {
            return [];
        } else {
            //$mandates->whereStatus('...')//$item->id $item->method $item->status
            $collection = [];
            foreach ($mandates as $item) {
                $collection[] = $item;
            }
            ;
            return $collection;
        }
        ;

        return NULL;
    }

    /**
     * @param string|int $user_id
     * The id user for which to get the customers identifier.
     * @param string|null $metod 'profile' default.
     * Connection method (if need create Client) (use data that is specified in the Mollie API)).
     * - Possible values: "profile", "organization", "app".
     * @param string|int|null $app_owner_id
     * If connection method is 'app'. The ID user for which the app is created (must be available the token OAuth2).
     * @return bool|string
     * - TRUE True on success if can accept recurring payments.
     * - String with error otherwise. Allowed values: (error_customer, error_mandat, no_mandate, no_valid_mandate).
     */
    public function canRecurringPayments($user_id, string $method = 'profile', $app_owner_id = '')
    {

        // We get a list of mandates.
        if (!$costumer_info = $this->getCustomersInfo($user_id)) {
            if (!$costumer = $this->createCustomers($user_id, $method, $app_owner_id)) {
                $this->messenger()->addError('Failed to create a customer in the process of creating recurring payments.');
                return 'error_customer';
            } else {
                $mandates_list = $this->getCustomerAllMandates('', null, null, $costumer);
            }
            ;
        } else {
            /** @var \Mollie\Api\Resources\Mandate[] $mandates_list */
            $mandates_list = $this->getCustomerAllMandates($method, $user_id, $app_owner_id);
        }
        ;

        // Let's check and return the result.
        if (!isset($mandates_list)) {
            $this->messenger()->addError('Failed to validate customer\'s mandat for recurring payments.');
            return 'error_mandate';
        } else if (count($mandates_list) > 0) {
            $use_test = $this->validator->useTestMode();
            foreach ($mandates_list as $v) {
                if ($v->status == 'valid') {
                    if ($use_test === FALSE && $v->mode !== 'test') {
                        continue;
                    }
                    ;
                    return TRUE;
                }
                ;
            }
            ;
            $this->messenger()->addError('Your customer Mollie does not have valid mandates for regular payments.');
            return 'no_valid_mandate';
        } else if ($mandates_list == []) {
            $this->messenger()->addError('Your customer mollie does not have mandates for creating regular payments.');
            return 'no_mandate';
        }
        ;

        return FALSE;
    }

    /**
     * @param string $subscription_id
     * The id subscription that need check.
     * @param string|int $user_id
     * The id user for which to get the customers info.
     * @param string|null $method 'profile' default.
     * Connection method (if need create Client) (use data that is specified in the Mollie API)).
     * - Possible values: "profile", "organization", "app".
     * @param string|int|null $app_owner_id
     * If connection method is 'app'. The ID user for which the app is created (must be available the token OAuth2).
     * @param \Mollie\Api\MollieApiClient|null $mollieClient NULL by default.
     * (Optional if available) Object Mollie API client, to to receive the first payment.
     * @param \Mollie\Api\Resources\Customer|null $customerClient
     * Object for which need to get mandates.
     * @return \Mollie\Api\Resources\Subscription|null
     * - Subscription with data.
     * - Null otherwise.
     */
    public function checkSubscriptionStatus(
        string $subscription_id,
        $user_id, ?string $method = 'profile', ?string $app_owner_id = null,
        ?MollieApiClient $mollieClient = null,
        ?\Mollie\Api\Resources\Customer $customerClient = null
    ): ?\Mollie\Api\Resources\Subscription {

        $subscription = null;

        if (!$customerClient) {

            if (!$mollieClient) {
                switch ($method) {
                    case 'profile':
                        $mollieClient = $this->getPaymentAdapter()->getClient();
                        break;
                    case 'organization':
                        $mollieClient = $this->getPaymentOAuth2Adapter()->getOrganizationClient();
                        break;
                    case 'app':
                        if (!$app_owner_id) {
                            return NULL;
                        }
                        ;
                        $mollieClient = $this->getPaymentOAuth2Adapter()->getClientOAuth2($app_owner_id);
                        break;
                    default:
                        return NULL;
                }
                ;
            }
            ;

            // need test
            //if ($mollieClient) {
            //  if (empty($user_id)) {return NULL;};
            //  $customer_id = $this->getCustomersInfo($user_id)['id'];
            //  if (in_array($method,['organization','app']) && $this->validator->useTestMode()) {
            //    $subscription = $mollieClient->subscriptions->getForId($customer_id, $subscription_id, ['testmode' => true]);
            //  } else {
            //    $subscription = $mollieClient->subscriptions->getForId($customer_id, $subscription_id);
            //  };
            //  return $subscription ?: NULL;
            //};
            if ($mollieClient) {
                if (empty($user_id)) {
                    return NULL;
                }
                ;
                $customer_id = $this->getCustomersInfo($user_id)['id'];
                if (in_array($method, ['organization', 'app']) && $this->validator->useTestMode()) {
                    $customerClient = $mollieClient->customers->get($customer_id, ['testmode' => true]);
                } else {
                    $customerClient = $mollieClient->customers->get($customer_id);
                }
                ;
            }
            ;

        }
        ;

        if ($customerClient) {
            if (in_array($method, ['organization', 'app']) && $this->validator->useTestMode()) {
                $subscription = $customerClient->getSubscription($subscription_id, ['testmode' => true]);
            } else {
                $subscription = $customerClient->getSubscription($subscription_id);
            }
            ;
        }
        ;

        return $subscription ?: NULL;
    }


    /** Writing test objects to a file */
    public static function intPrintTest($obj = [], $fi = __FILE__, $fun = __FUNCTION__, $lin = __LINE__, $note = '')
    {
        $ps = PHP_EOL; //"\r\n";
        $pr = ' ';
        $pr_step = 0;

        $objectToArray = function ($obj) use (&$objectToArray) {
            if (!is_object($obj) && !is_array($obj)) {
                return $obj;
            } else if (is_object($obj)) {
                return array_map($objectToArray, get_object_vars($obj));
            } else {
                return array_map($objectToArray, (array) $obj);
            };
        };
        $data = $objectToArray($obj);

        $stri = $fi . '::' . $fun . '()' . $lin . $ps . $note . $ps;

        $prin = function ($data) use ($ps, $pr, &$pr_step, &$prin) {
            $strin = '';
            foreach ($data as $key => $mixed) {
                if (empty($key) && empty($mixed)) {
                    continue;
                };
                $key = trim($key);
                if (!is_numeric($key) && !empty($key)) {
                    $key = '"' . addslashes($key) . '"';
                };

                if (!is_array($mixed) && !is_object($mixed) && !is_bool($mixed) && !is_null($mixed) && !empty($mixed)) {
                    $mixed = trim($mixed);
                } elseif ($mixed === null) {
                    $mixed = 'null';
                } elseif ($mixed === false) {
                    $mixed = 'false';
                } elseif ($mixed === true) {
                    $mixed = 'true';
                } elseif ($mixed === "") {
                    $mixed = '""';
                } elseif (is_object($mixed)) {
                    $mixed = (array) $mixed;
                };
                if ($mixed === []) {
                    $mixed = '[]';
                };

                if (!is_numeric($mixed) && !is_array($mixed) && !empty($mixed)) {
                    if ($mixed != 'false' && $mixed != 'true' && $mixed != 'null' && $mixed != '""' && $mixed != '[]') {
                        $mixed = '"' . addslashes($mixed) . '"';
                    }
                };

                if (is_array($mixed)) {
                    if ($key !== null) {
                        $strin .= str_repeat($pr, $pr_step) . "$key => array(" . $ps;
                        $pr_step = $pr_step + 3;
                        $strin .= $prin($mixed, $strin);
                        $pr_step = $pr_step - 3;
                        $strin .= str_repeat($pr, $pr_step) . ")," . $ps;
                    } else {
                        $strin .= str_repeat($pr, $pr_step) . "array(" . $ps;
                        $pr_step = $pr_step + 3;
                        $strin .= $prin($mixed, $strin);
                        $pr_step = $pr_step - 3;
                        $strin .= str_repeat($pr, $pr_step) . ")," . $ps;
                    }
                } else {
                    if ($key !== null) {
                        $strin .= str_repeat($pr, $pr_step) . "$key => " . $mixed . ',' . $ps;
                    } else {
                        $strin .= str_repeat($pr, $pr_step) . '[null] => ' . $mixed . ',' . $ps;
                    }
                };

            };
            return $strin;
        };

        $prints = $stri . $prin($data);

        $root_folder = 'modules\\custom\\payment_provider\\atestxt\\';
        $f_name = $root_folder . (new \DateTime)->format('m-d H_i_s') . '.txt';
        if (file_exists($f_name)) {
            $f_name = $root_folder . (new \DateTime)->format('m-d H_i_s_u') . '.txt';
        }
        ;
        $file = file_put_contents($f_name, $prints, FILE_APPEND | LOCK_EX);

        return $file;
    }


    /**
     *
     * Prashant's Code
     *
     */
  public function getRedirectCustomerPayment(
    string $amount,
    string $purpose,
           $user_id,
    string $method = 'profile',
    string $transaction = 'payment',
           $app_owner_id = null,
    ?\Mollie\Api\Resources\Customer $customerClient = null,
    ?MollieApiClient $mollieClient = null
  ): ?TrustedRedirectResponse {

    $payment = $this->getCustomerPayment($amount, $purpose, $user_id, $method,$transaction, $app_owner_id, $customerClient, $mollieClient);

    if (empty($payment)) {
      return NULL;
    }
    ;

    if ($payment->status !== 'open') {
      $this->getLogger('payment_invoice')->error('First payment created for user:"@id" has status:"@status" - it is not "open".', [
        '@id' => $user_id,
        '@status' => $payment->status,
      ]);
      $this->messenger()->addError('Failed to create first payment. Has the status - "' . $payment->status . '".');
      return null;
    }
    ;

    $url = $payment->getCheckoutUrl();
    return new \Drupal\Core\Routing\TrustedRedirectResponse($url, '303');
  }

  public function getCustomerPayment(
    string $amount, string $purpose,
           $user_id,
    string $method = 'profile',
    string $transaction = 'payment',
           $app_owner_id = null,
    ?\Mollie\Api\Resources\Customer $customerClient = null,
    ?MollieApiClient $mollieClient = null
  ) {
    // Get Mollie's client id.
    // If there is no client, we will create it.
    if (!$customer_info = $this->getCustomersInfo($user_id, )) {
      if ($customerClient = $this->createCustomers($user_id, )) {
        $customer_info['id'] = $customerClient->id;
      } else {
        $this->getLogger('payment_provider')->error('Failed to get customer ID for payment.');
        return NULL;
      }
      ;
    }
    $type = ($purpose === 'VIP Yearly Subscription') ? 'VIP Subscription' : 'Standard User Upgrade';

    /** @var \Drupal\Core\Routing\RouteMatchInterface $routeMatch */
    $routeMatch = \Drupal::service('current_route_match');

//    $redirect_url = Url::fromRoute(
//      $routeMatch->getRouteName(),
//      [],
//      [
//        'https' => \Drupal::request()->getScheme() == 'https' ? TRUE : FALSE,
//        'absolute' => TRUE,
//      ]
//    )->toString();
    $redirect_url = Url::fromRoute('entity.user.canonical', ['user' => $user_id], [
      'https' => \Drupal::request()->getScheme() === 'https',
      'absolute' => TRUE,
    ])->toString();

    $webhook_url = Url::fromRoute(
      'payment_provider.mollie_webhook.other',
      [
        'pay' => $transaction,
        'context' => 'paymentGateway',
      ],
      [
        'https' => \Drupal::request()->getScheme() == 'https' ? TRUE : FALSE,
        'absolute' => TRUE,
      ]
    )->toString();
    $data = array(
      'amount' => array(
        'value' => $amount,
        'currency' => 'EUR',
      ),
      'sequenceType' => \Mollie\Api\Types\SequenceType::SEQUENCETYPE_FIRST,
      'description' => $type,
      'redirectUrl' => $redirect_url,
      'webhookUrl' => $webhook_url,
      "method" => array('bancontact', 'belfius', 'creditcard','kbc','paysafecard','ideal'),
    );

    //  We will add additional information to the payment for convenience.
    $data['metadata'] = [
      'user_id' => $user_id,
      'purpose' => $purpose,
      'invoice' => time(),
    ];
    
    // If the payment will be organized not through the client's objeck, then "customerId" should be added.
    if (!$customerClient) {
      $data['customerId'] = $customer_info['id'];
    }
    ;
    // If we use OAuth2.
    if (in_array($method, ['organization', 'app'])) {
      // REQUIRED for Access Tokens. The website profile's unique identifier, example (pfl_3RkSN1zuPE).
      $data['profileId'] = $this->validator->get('profile_id');
      // Use test mode?
      if ($this->validator->useTestMode()) {
        $data['testmode'] = TRUE;
      }
      ;
    }
    ;

    if ($customerClient && $transaction = 'payment') {
      //https://github.com/mollie/mollie-api-php/blob/master/examples/customers/create-customer-first-payment.php
      $first_payment = $customerClient->createPayment($data);
    } else {

      if (!$mollieClient) {
        switch ($method) {
          case 'profile':
            $mollieClient = $this->getPaymentAdapter()->getClient();
            break;
          case 'organization':
            $mollieClient = $this->getPaymentOAuth2Adapter()->getOrganizationClient();
            break;
          case 'app':
            if (!$app_owner_id) {
              return NULL;
            }
            ;
            $mollieClient = $this->getPaymentOAuth2Adapter()->getClientOAuth2($app_owner_id);
            break;
          default:
            $this->getLogger('payment_provider')->error('An unknown connection method was given for the first payment.');
            return NULL;
        }
        ;
      }
      ;

      if ($mollieClient) {
        //https://docs.mollie.com/payments/recurring
        if ($transaction == 'payment') {
          $first_payment = $mollieClient->payments->create($data);
        } else if ($transaction == 'order') {
          $first_payment = $mollieClient->orders->create($data);
        }
        ;
      }
      ;

    }
    ;

    return $first_payment;
  }


}
