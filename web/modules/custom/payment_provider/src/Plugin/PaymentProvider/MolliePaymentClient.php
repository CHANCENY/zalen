<?php

namespace Drupal\payment_provider\Plugin\PaymentProvider;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Messenger\MessengerInterface;

use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Exceptions\IncompatiblePlatform;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\MethodCollection;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Logger\LoggerChannelTrait;

use Drupal\payment_provider\Plugin\PaymentProvider\MollieOAuth2Authorize;

/**
 * Class MolliePaymentClient.
 */
class MolliePaymentClient {

  use StringTranslationTrait;
  use LoggerChannelTrait;

  /**
   * Messenger.
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Config factory.
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Mollie config validator.
   * @var \Drupal\payment_provider\Plugin\PaymentProvider\MollieConfigValidator
   */
  protected $configValidator;

  /**
   * Mollie API client.
   * @var \Mollie\Api\MollieApiClient
   */
  protected $client;

  /**
   * Mollie organization API client.
   * @var \Mollie\Api\MollieApiClient
   */
  protected $clientOrganization;

  /**
   * Mollie has OAuth2 Authorize object.
   */
  protected $supportOAuth2 = TRUE;

  /**
   * Mollie OAuth2 Authorize object.
   * @var \Drupal\payment_provider\Plugin\PaymentProvider\MollieOAuth2Authorize
   */
  protected $authorizeOAuth2;

  /**
   * Mollie API client.
   * @var \Mollie\Api\MollieApiClient
   */
  protected $clientOAuth2;

  /**
   * Mollie constructor.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory.
   * @param \Drupal\payment_provider\Plugin\PaymentProvider\MollieConfigValidator $configValidator
   *   Mollie config validator.
   */
  //public function __construct(MessengerInterface $messenger, ConfigFactoryInterface $configFactory, MollieConfigValidator $configValidator) {}
  public function __construct(MollieOAuth2Authorize $authorizeOAuth2 = NULL) {
    //$this->messenger = $messenger;
    //$this->configFactory = $configFactory;
    //$this->configValidator = $configValidator;
    /** @var \Symfony\Component\DependencyInjection\ContainerInterface $container */
    $container = \Drupal::getContainer();
    $this->messenger = $container->get('messenger');
    $this->configFactory = $container->get('config.factory');
    $this->configValidator = new MollieConfigValidator;
    $this->authorizeOAuth2 = $authorizeOAuth2;
  }

  /**
   * Gets the logger for a specific channel.
   * This method exists for backward-compatibility between FormBase and
   * LoggerChannelTrait. Use LoggerChannelTrait::getLogger() instead.
   * @param string $channel
   * The name of the channel. Can be any string, but the general practice is
   * to use the name of the subsystem calling this.
   * @return \Psr\Log\LoggerInterface
   * The logger for the given channel.
   */
  protected function logger($channel) {
    return $this->getLogger($channel);
  }

  /**
   * Returns a client for communication with the Mollie API.
   * @return \Mollie\Api\MollieApiClient|null
   * A client for communication with the Mollie API or NULL if this client could not be loaded.
   */
  public function getClient(): ?MollieApiClient {
    // Static caching.
    if (isset($this->client)) {
      return $this->client;
    }

    try {
      $client = new MollieApiClient();
      // Add version strings. These are used by Mollie to gather statistics
      // about platform usage.
      $client->addVersionString('Drupal/8.x');
      $client->addVersionString('Drupal/' . \Drupal::VERSION);

      if (1||$this->useTestMode()) {
        //$client->setApiKey(Settings::get('mollie.settings')['test_key']);
        $client->setApiKey($this->configValidator->getTestApiKey());
      } elseif ($this->configValidator->hasLiveApiKey()) {
        //$client->setApiKey(Settings::get('mollie.settings')['live_key']);
        $client->setApiKey($this->configValidator->getLiveApiKey());
      }

      $this->client = $client;

      return $this->client;

    } catch (IncompatiblePlatform $e) {
      $this->logger('mollie')->error($e);
      //watchdog_exception('mollie', $e);
      $this->messenger->addError($this->t('This project is not compatible with Mollie API client for PHP.'));
    } catch (ApiException $e) {
      $this->logger('mollie')->error($e);
      //watchdog_exception('mollie', $e);
      $this->messenger->addError($this->t('The Mollie API client for PHP could not be initialized.'));
    }

    return NULL;
  }

  /**
   * Returns a Organization client (OAuth2 authorization) for communication with the Mollie API.
   * @return \Mollie\Api\MollieApiClient|null
   * A Organization client for communication with the Mollie API or NULL if this client could not be loaded.
   */
  public function getOrganizationClient(): ?MollieApiClient {

    // The client uses OAuth2, but we do not need the authorizeOAuth2 object
    // because the key does not need to be searched for and refresh.

    // Static caching.
    if (isset($this->clientOrganization)) {
      return $this->clientOrganization;
    }

    try {
      $clientOrganization = new MollieApiClient();
      // Add version strings. These are used by Mollie to gather statistics
      // about platform usage.
      $clientOrganization->addVersionString('Drupal/8.x');
      $clientOrganization->addVersionString('Drupal/' . \Drupal::VERSION);

      // if ($this->useTestMode()) { 'testmode' => 'TRUE',
      if ($this->configValidator->has('token_organization_access')) {
        $clientOrganization->setAccessToken($this->configValidator->get('token_organization_access'));
      };

      $this->clientOrganization = $clientOrganization;
      return $this->clientOrganization;

    } catch (IncompatiblePlatform $e) {
      $this->logger('mollie')->error($e);
      $this->messenger->addError($this->t('This project is not compatible with Mollie API client for PHP.'));
    } catch (ApiException $e) {
      $this->logger('mollie')->error($e);
      $this->messenger->addError($this->t('The Mollie API client for PHP could not be initialized.'));
    }

    return NULL;
  }

  /**
   * Returns a client with OAuth2 authorization for communication with the Mollie API.
   * @param string $user_id
   * User ID for which you want to use Mollie
   * @return \Mollie\Api\MollieApiClient|null
   * A client for communication with the Mollie API + OAuth2 or NULL if this client could not be loaded.
   */
  public function getClientOAuth2($user_id): ?MollieApiClient {

    // If the client was loaded without OAuth2 support
    if (empty($this->authorizeOAuth2)) {
      return NULL;
    }

    // Static caching.
    if (isset($this->clientOAuth2)) {
      return $this->clientOAuth2;
    }

    $token = $this->authorizeOAuth2->getToken($user_id);

    if (empty($token)) {
      return NULL;
    }

    try {
      $clientOAuth2 = new MollieApiClient();
      // Add version strings. These are used by Mollie to gather statistics
      // about platform usage.
      $clientOAuth2->addVersionString('Drupal/8.x');
      $clientOAuth2->addVersionString('Drupal/' . \Drupal::VERSION);

      // if ($this->useTestMode()) { 'testmode' => 'TRUE',
      // if ($this->configValidator->hasLiveApiKey()) {
      $clientOAuth2->setAccessToken($token->getTokenValue());

      $this->clientOAuth2 = $clientOAuth2;
      return $this->clientOAuth2;

    } catch (IncompatiblePlatform $e) {
      $this->logger('mollie')->notice($e);
      $this->messenger->addError($this->t('This project is not compatible with Mollie API client for PHP.'));
    } catch (ApiException $e) {
      $this->logger('mollie')->notice($e);
      $this->messenger->addError($this->t('The Mollie API client for PHP could not be initialized.'));
    }

    return NULL;
  }

  /**
   * Determines whether test mode should be used.
   * @return bool
   * True if test mode should be used, false otherwise.
   */
  public function useTestMode(): bool {
    return $this->configValidator->hasTestApiKey() && $this->configFactory->get('payment.mollie.config')->get('test_mode');
  }

  /**
   * Returns an array of payment methods available for a given amount.
   * @param float $amount
   * Amount to be paid.
   * @param string $currency
   * Currency in which the amount should be paid.
   * @return array
   * Associative array keyed by payment method ID.
   */
  public function getMethods(float $amount, string $currency): array {
    $methods = [];

    foreach ($this->getMethodsRaw($amount, $currency) as $method) {
      /** @var \Mollie\Api\Resources\Method $method */
      $methods[$method->id] = $method->description;
    }

    return $methods;
  }

  /**
   * Returns a collection of payment methods available for a given amount.
   * @param float $amount
   * Amount to be paid.
   * @param string $currency
   * Currency in which the amount should be paid.
   * @return \Mollie\Api\Resources\MethodCollection
   * Method collection.
   */
  protected function getMethodsRaw(float $amount, string $currency): MethodCollection {
    try {
      return $this->getClient()->methods->allActive(
        [
          'amount' => [
            'value' => number_format($amount, 2, '.', ''),
            'currency' => $currency,
          ]
        ]
      );
    }
    catch (ApiException $e) {
      $this->logger('mollie')->notice($e);
      //watchdog_exception('mollie', $e);
    }

    return new MethodCollection(0, []);
  }

  // удалить
  ///**
  // * Returns array Mollie Payment methods.
  // * @return array Available payment methods
  // */
  //public function getAvailablePaymentMethods(): array {
  //  $methods = [
  //    'payment' => $this->t('Payments API v2: This is a simple payment.'),
  //    'order' => $this->t('Orders API v2: Orders have specific parameters suitable for stores (multiple items or delivery..).'),
  //    'subscription' => $this->t('Subscriptions API v2: You can schedule recurring payments.'),
  //    'to_pay' => $this->t('Payments reservation with OAutx2 and fees.'),
  //  ];
  //  return $methods;
  //}
  ///**
  // * Returns TRUE if Mollie Payment provider has support authorize via OAuth2.
  // * @return bool support authorize via OAuth2
  // */
  //public function isSupportOAuth2(): bool {
  //  return $this->supportOAuth2;
  //}

  /**
   * Check the Organization client connection status to Mollie.
   * @return \Mollie\Api\Resources\Onboarding|int
   * Response from Mollie, or status code in case of error.
   */
  public function getStatusOrganizationConnection() {

    // Authentication: Organization access tokens; App access tokens.
    // This API endpoint is only available with an OAuth access token and cannot be accessed with an API key
    $clientOrganization = $this->getOrganizationClient();

    try {
      /** @var \Mollie\Api\Resources\Onboarding $onboarding */
      $onboarding = $clientOrganization->onboarding->get();
    } catch (ApiException $e) {
      $this->logger('mollie')->warning($e);
      $status = $e->getCode();
      if ($status == 403) {
        $this->messenger->addError($this->t('Not all required permissions for accessing this resource were granted.'));
      } else {
        $this->messenger->addError($this->t('The Mollie API client for PHP could not be initialized.'));
      };
    };
    return $onboarding ?? intval($status);
  }

  /**
   * Check the client's connection status to Mollie.
   * @see https://docs.mollie.com/connect/onboarding
   * @param string $user_id
   * User ID for which you want to use Mollie
   * @return \Mollie\Api\Resources\Onboarding|int Response from Mollie, or status code in case of error.
   * @see https://docs.mollie.com/reference/v2/onboarding-api/get-onboarding-status
   * An object with information about the status of the client's connection to the Mollie
   * - resource (string) Indicates the response contains an onboarding object. Will always contain onboarding for this endpoint.
   * - name (string) The name of the organization.
   * - signedUpAt (datetime) The sign up date and time of the organization.
   * - status (string) The current status of the organization's onboarding process. Possible values:
   * - -  "needs-data" The onboarding is not completed and the merchant needs to provide (more) information
   * - -  "in-review" The merchant provided all information and Mollie needs to check this
   * - -  "completed" The onboarding is completed
   * - canReceivePayments (boolean) Whether or not the organization can receive payments.
   * - canReceiveSettlements (boolean) Whether or not the organization can receive settlements.
   * - _links (object) An object with several URL objects relevant to the onboarding status. Every URL object will contain an "href" and a "type" field.
   */
  public function registrationSellerGetStatusConnection($user_id) {
    //$mollie = new \Mollie\Api\MollieApiClient();
    //$mollie->setAccessToken("access_dHar4XY7LxsDOtmnkVtjNVWXLSlXsM");
    //$onboarding = $mollie->onboarding->get();

    // Authentication: Organization access tokens; App access tokens.
    // This API endpoint is only available with an OAuth access token and cannot be accessed with an API key
    $mollieOAuth2Client = $this->getClientOAuth2($user_id);
    try {
      /** @var \Mollie\Api\Resources\Onboarding $onboarding */
      $onboarding = $mollieOAuth2Client->onboarding->get();
    } catch (ApiException $e) {
      $this->logger('mollie')->warning($e);
      $status = $e->getCode();
      if ($status == 403) {
        $this->messenger->addError($this->t('Not all required permissions for accessing this resource were granted.'));
      } else {
        $this->messenger->addError($this->t('The Mollie API client for PHP could not be initialized.'));
      };
    };
    return $onboarding ?? intval($status);
  }

  /**
   * Provide seller details to connect to Mollie using an endpoint.
   * @param string $user_id
   * User ID for which you want to use Mollie
   * @param array $seller_data
   * Data sent to Mollie, which will be pre-filled when registering the seller.
   * @see https://docs.mollie.com/reference/v2/onboarding-api/submit-onboarding-data
   * @param bool $seller_status
   * "TRUE" if need to check the status of the seller because:
   * Data be processed only then when the status "needs-data".
   * Data sent to Mollie, which will be pre-filled when registering the seller.
   * @return bool because the Mollie API does not return anything (Available void)
   * we will catch errors if they are, then the data was not sent, and if they are not, then the data was sent.
   */
  public function registrationSellerSendDataConnection ($user_id, $seller_data, $seller_status = TRUE): bool {
    // if needs-data
    // see сообщения о статусе подключения https://docs.mollie.com/connect/onboarding
    // canReceivePayments (предоставлена основная информация) canReceiveSettlements (вся информация предоставлена и проверена)

    return TRUE;
    // example
    //$mollie = new \Mollie\Api\MollieApiClient();
    //$mollie->setAccessToken("access_dHar4XY7LxsDOtmnkVtjNVWXLSlXsM");
    //$mollie->onboarding->submit([
    //  "organization" => [
    //    "name" => "Mollie B.V.",
    //    "address" => [
    //      "streetAndNumber" => "Keizersgracht 126",
    //      "postalCode" => "1015 CW",
    //      "city" => "Amsterdam",
    //      "country" => "NL",
    //    ],
    //    "registrationNumber" => "30204462",
    //    "vatNumber" => "NL815839091B01",
    //  ],
    //  "profile" => [
    //    "name" => "Mollie",
    //    "url" => "https://www.mollie.com",
    //    "email" => "info@mollie.com",
    //    "phone" => "+31208202070",
    //    "businessCategory" => "MONEY_SERVICES",
    //  ],
    //]);

    // If need to check the status of the seller before sending the data to the Mollie.
    if ($seller_status) {
      $seller_status = $this->registrationSellerGetStatusConnection($user_id);
      if ($seller_status->status !== 'needs-data') {
        $this->messenger->addWarning($this->t('Mollie will not accept data, it needs the "needs-data" status to send the data. Your status is different.'));
        return FALSE;
      };
    };

    try {
    // Authentication: Organization access tokens; App access tokens.
    $mollieOAuth2Client = $this->getClientOAuth2($user_id);
    $mollieOAuth2Client->onboarding->submit($seller_data);
    } catch (ApiException $e) {
    $this->logger('mollie')->warning($e);
    $this->messenger->addError($this->t('Data sending error.'));
    return FALSE;
    };

    return TRUE;

  }




}
