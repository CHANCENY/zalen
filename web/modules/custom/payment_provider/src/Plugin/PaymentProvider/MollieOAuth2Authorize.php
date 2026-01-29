<?php

namespace Drupal\payment_provider\Plugin\PaymentProvider;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Exceptions\IncompatiblePlatform;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Logger\LoggerChannelTrait;
use Mollie\OAuth2\Client\Provider\Mollie as MollieOAuth2;
use Drupal\Core\Routing\TrustedRedirectResponse;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Grant\RefreshToken;
use Drupal\oauth_authorize\Entity\OauthAuthorize;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Class MollieOAuth2Authorize.
 */
class MollieOAuth2Authorize {

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
   * @var \Drupal\room_invoice\Mollie\OAuth2Provider\Mollie
   */
  protected $clientOAuth2;

  /**
   * Mollie constructor.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory.
   */
  public function __construct() {
    /** @var \Symfony\Component\DependencyInjection\ContainerInterface $container */
    $container = \Drupal::getContainer();
    $this->messenger = $container->get('messenger');
    $this->configFactory = $container->get('config.factory');
    $this->configValidator = new MollieConfigValidator;
  }
  //public static function buildInstance() {
  //  $container = \Drupal::getContainer();
  //  $instance = new static($container->get('messenger'), $container->get('config.factory'));
  //  return $instance;
  //}

  /**
   * Gets the logger for a specific channel.
   * This method exists for backward-compatibility between FormBase and
   * LoggerChannelTrait. Use LoggerChannelTrait::getLogger() instead.
   *
   * @param string $channel
   * The name of the channel. Can be any string, but the general practice is
   * to use the name of the subsystem calling this.
   *
   * @return \Psr\Log\LoggerInterface
   * The logger for the given channel.
   */
  protected function logger($channel) {
    return $this->getLogger($channel);
  }

  /**
   * Returns a client for communication with the Mollie OAuth2.
   *
   * @return \Mollie\OAuth2\Client\Provider\Mollie|null
   * A client for communication with the Mollie OAuth2 or NULL if this client could not be loaded.
   */
  protected function getOAuth2Client(): ?MollieOAuth2 {

    // Static caching.
    if (isset($this->clientOAuth2)) {
      return $this->clientOAuth2;
    };

    // Set initialization options
    $init_options = [
      'clientId' => $this->configValidator->get('client_id'),
      'clientSecret' => $this->configValidator->get('client_secret'),
      'redirectUri' => $this->configValidator->get('app_redirect_url'),
    ];

    try {
      $clientOAuth2 = new MollieOAuth2($init_options);
      $this->clientOAuth2 = $clientOAuth2;
      return $this->clientOAuth2;
    }
    catch (IncompatiblePlatform $e) {
      $this->logger('mollie_OAuth2')->notice($e);
      $this->messenger->addError($this->t('This project is not compatible with Mollie OAuth2 client for PHP.'));
    }
    catch (ApiException $e) {
      $this->logger('mollie_OAuth2')->notice($e);
      $this->messenger->addError($this->t('The Mollie OAuth2 client for PHP could not be initialized.'));
    }

    return NULL;
  }

  /**
   * Returns a client for communication with the Mollie OAuth2.
   * \Drupal\room_invoice\Mollie\OAuth2Provider\Mollie|null
   * \League\OAuth2\Client\Provider\ResourceOwnerInterface
   * @return Drupal\Core\Routing\TrustedRedirectResponse|null
   * A client for communication with the Mollie OAuth2 or NULL if this client could not be loaded.
   */
  public function getOAuth2Redirect(): ?TrustedRedirectResponse {

    /** @var \Mollie\OAuth2\Client\Provider\Mollie $provider */
    $provider = $this->getOAuth2Client();
    /** @var \Symfony\Component\HttpFoundation\Request The currently active request object. */
    $currentRequest = \Drupal::request();
    /** @var \Drupal\Core\TempStore\PrivateTempStore $tempstore */
    $tempstore = \Drupal::service('tempstore.private')->get('module_mollie');

    if (!$currentRequest->query->get('code')) {
      $authorizationUrl = $provider->getAuthorizationUrl([
        // Optional, only use this if you want to ask for scopes the user previously denied.
        'approval_prompt' => 'force',
        // Optional, a list of scopes. Defaults to only 'organizations.read'.
        'scope' => [
          MollieOAuth2::SCOPE_ORGANIZATIONS_READ,
          MollieOAuth2::SCOPE_PAYMENTS_READ,
          MollieOAuth2::SCOPE_ORGANIZATIONS_WRITE,
          MollieOAuth2::SCOPE_PAYMENTS_WRITE,
          MollieOAuth2::SCOPE_ONBOARDING_READ,
          MollieOAuth2::SCOPE_ONBOARDING_WRITE,
          //Not all required permissions (onboarding.read) for accessing this resource were granted
        ],
      ]);
      $tempstore->set('oauth2state', $provider->getState());
      return new TrustedRedirectResponse($authorizationUrl);
    };

    return NULL;
  }

  /**
   * Processing OAuth2 authorization from redirect request
   * @return Drupal\oauth_authorize\Entity\OauthAuthorize|null
   * A token Mollie OAuth2 for communication or NULL if this request could not be processing.
   */
  public function processingOAuth2Redirect(): ?OauthAuthorize {

    /** @var \Symfony\Component\HttpFoundation\Request The currently active request object. */
    $currentRequest = \Drupal::request();

    /** @var \Drupal\Core\TempStore\PrivateTempStore $tempstore */
    $tempstore = \Drupal::service('tempstore.private')->get('module_mollie');
    if (empty($currentRequest->query->get('state')) || ($currentRequest->query->get('state') !== $tempstore->get('oauth2state'))) {

      $tempstore->delete('module_mollie');
      $this->logger('mollie_OAuth2')->notice($this->t('Invalid state in Request on processing Mollie OAuth2 Authorize, User ID: @user.', ['@user' => \Drupal::currentUser()->id()]));
      $this->messenger->addError($this->t('Invalid state. Client could not be initialized.'));
      $this->messenger->addMessage($this->t('Please restart the process there was problem with the OAuth2 Authorize.'));
      return NULL;

    } else {

      try {

        /** @var \Mollie\OAuth2\Client\Provider\Mollie $provider */
         $provider = $this->getOAuth2Client();

         $accessToken = $provider->getAccessToken('authorization_code', ['code' => $currentRequest->query->get('code')]);

         $tempstore->delete('module_mollie');
         $access_token = $accessToken->getToken();
         $refresh_token = $accessToken->getRefreshToken();
         $expires_in = $accessToken->getExpires();
         $token_type = $accessToken->getValues()['token_type'];
         $scope = explode(' ', $accessToken->getValues()['scope']);

         $storageToken = $this->getStorageToken();
         if ($storageToken->isNew()) {

           // Start mollie payment gateway.
           $mollie = new \Mollie\Api\MollieApiClient();
           $mollie->setAccessToken($access_token);
           $organization = $mollie->organizations->current();

           $storageToken->setProvider('mollie')->setExpireTime($expires_in)->setOrganizationId($organization->id);
         };
         $storageToken->setTokenValue($access_token)->setRefreshToken($refresh_token)->setScopesList($scope);

         /** @var \Drupal\Core\Entity\EntityConstraintViolationList $validate */
         $validate = $storageToken->validate();
         $validate_error = $validate->count();
         if ($validate_error) {
           for ($i=0; $i<$validate_error; $i++) {
             /** @var Symfony\Component\Validator\ConstraintViolation $violation */
             $violation = $validate->get($i);
             $arguments = [
               '@property path' => $violation->getPropertyPath(),
               '@invalid_value' => 'ID:' . $violation->getInvalidValue()->getString(),
               '@message' => $violation->getMessage(),
             ];
             $this->logger('mollie_OAuth2')->warning('An error occurred while create Mollie OAuth2 entity token. Violation consists of 1.Property path: @property path. 2.Invalid value: @invalid_value. 3.Message: @message', $arguments);
             $this->messenger->addWarning($this->t('Error create Mollie OAuth2 storage token. @message', ['@message' => $arguments['@message']]));
           };
         } else {
           $storageToken->save();
           $this->messenger->addMessage($this->t('Your token has been saved, you are authorize via Mollie.'));
           return $storageToken;
         };

        // Using the access token, we may look up details about the resource owner.
        //$resourceOwner = $provider->getResourceOwner($accessToken);
        //$dataResourceOwner = $resourceOwner->toArray();

        return NULL;

      } catch (IdentityProviderException $e) {

        // Failed to get the access token or user details.
        $this->logger('mollie_OAuth2')->notice($this->t($e->getMessage()));
        $this->messenger->addError($this->t('Error. Client could not be initialized.'));
        return NULL;
      };

    };

    return NULL;

  }

  /**
   * Returns a client for communication with the Mollie OAuth2.
   * @param string $user_id
   * User ID for which you want to load the entity with the token
   * @param \Drupal\oauth_authorize\Entity\OauthAuthorize|null $token
   * Token which need to refresh.
   * @return  \Drupal\oauth_authorize\Entity\OauthAuthorize|null
   * A access_token for Mollie OAuth2 or NULL if this client could not be loaded.
   */
  public function refreshMollieOAuth2Authorization($user_id = '', OauthAuthorize $token = NULL): ?OauthAuthorize {
    /** @var \Mollie\OAuth2\Client\Provider\Mollie $provider */
    $provider = $this->getOAuth2Client();

    if ($token && $user_id) {
      $this->messenger->addMessage($this->t('You cannot use user ID and token at the same time. It is not clear for whom to update the data.'));
      return NULL;
    };

    ///** @var \Drupal\Core\Session\AccountProxyInterface $currentUser Proxy object current user */
    //$currentUser = \Drupal::currentUser();// \Drupal::request()->getSession()->get('uid');//id
    //$user_id = $user_id ? $user_id : \Drupal::currentUser()->id();
    //$storageToken = $token ? $token : $this->getStorageToken($user_id);
    if ($token) {
      $user_id = $token->getOwnerId;
      $storageToken = $token;
    } else if ($user_id) {
      $storageToken = $this->getStorageToken($user_id);
    } else {
      $user_id = \Drupal::currentUser()->id();
      $storageToken = $this->getStorageToken($user_id);
    };

    if (empty($storageToken)) {
      $this->logger('mollie_OAuth2')->notice($this->t('It is not possible to update the Mollie OAuth2 token for the user ID:@user, because the entity storing the tokens is not available.', ['@user' => $user_id]));
      $this->messenger->addError($this->t('Error. Client could not be initialized.'));
      return NULL;
    };
    $refreshToken = $storageToken->getRefreshToken();

    $grant = new RefreshToken();
    $accessToken = $provider->getAccessToken($grant, ['refresh_token' => $refreshToken]);
    $access_token = $accessToken->getToken();
    $expires_in = $accessToken->getExpires();
    $storageToken->setTokenValue($access_token)->setExpireTime($expires_in);
    $storageToken->save();
    return $storageToken;
  }

  /**
   * TODO it don't work
   * Returns client information for Mollie OAuth2.
   * @return array|null void
   * Mollie OAuth2 client information, or NULL if this client cannot be loaded.
   */
  public function infoOwnerMollieOAuth2(): ?array {
    /** @var \Mollie\OAuth2\Client\Provider\Mollie $provider */
    $provider = $this->getOAuth2Client();

    /** @var \Drupal\Core\Session\AccountProxyInterface $currentUser Proxy object current user */
    $currentUser = \Drupal::currentUser();// \Drupal::request()->getSession()->get('uid');//id
    $storageToken = $this->getStorageToken($currentUser->id());
    if (empty($storageToken)) {return null;};
    $token = $storageToken->getTokenValue();

    // TODO it don't work
    $accessToken = $provider->getAccessToken('authorization_code', ['code' => $token]);
    // Using the access token, we may look up details about the resource owner.
    $resourceOwner = $provider->getResourceOwner($accessToken);
    $user_id = $resourceOwner->getId();//getName() getUsername() getLocation()
    //print_r($resourceOwner->toArray());
    return $resourceOwner->toArray();
  }

  /**
   * Loads an entity storing an OAuth2 authorization token
   * @param string $user_id
   * User ID for which you want to load the entity with the token
   * @param bool $cteate_new
   * TRUE if there is no token for this user and need to create a new one.
   * @return \Drupal\oauth_authorize\Entity\OauthAuthorize
   * Returns an entity with an OAuth2 token for the set user, or creates a new entity for the current user.
   */
  private function getStorageToken($user_id = '', $cteate_new = TRUE): ?OauthAuthorize {

    ///** @var \Drupal\Core\Session\AccountProxyInterface $currentUser Proxy object current user */
    //$currentUser = \Drupal::currentUser();
    $user_id = $user_id ? $user_id : \Drupal::currentUser()->id();
    /** @var \Drupal\Core\Entity\Sql\SqlContentEntityStorage $storageToken */
    $storageToken = \Drupal::entityTypeManager()->getStorage('oauth_authorize_token');
    /** @var \Drupal\oauth_authorize\Entity\OauthAuthorize[] $tokens object */
    $tokens = $storageToken->loadByProperties(['provider' => 'mollie', 'author' => $user_id]);

    if (empty($tokens) && !$cteate_new) {
      return NULL;
    };

    return empty($tokens) ? $storageToken->create() : current($tokens);
  }

  /**
   * Loads an entity storing an OAuth2 authorization token
   * @param string $user_id
   * User ID for which you want to load the entity with the token
   * @return \Drupal\oauth_authorize\Entity\OauthAuthorize
   * Returns an entity with an OAuth2 token for the set user, or creates a new entity for the current user.
   */
  public function getToken($user_id): ?OauthAuthorize {
    $token = $this->getStorageToken($user_id, FALSE);
    if (!$token) {
      $this->messenger->addError($this->t('Payment provider access token not found.'));
      return NULL;
    };
    if ($token->getExpireTime() < (new DrupalDateTime())->getTimestamp()) {
      $token = $this->refreshMollieOAuth2Authorization($user_id);
    };
    return $token;
  }







}
