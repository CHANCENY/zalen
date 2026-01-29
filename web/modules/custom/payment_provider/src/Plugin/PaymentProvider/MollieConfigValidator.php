<?php

namespace Drupal\payment_provider\Plugin\PaymentProvider;

/**
 * Class MollieConfigValidator.
 */
class MollieConfigValidator {

  /**
   * The array Mollie payment provider API keys.
   * @var array
   */
  private $mollieAPIkey;

  /**
   * Construct MollieConfigValidator.
   * Mollie config validator.
   */
  public function __construct() {
    $this->mollieAPIkey = $this->setkeysApi();
  }

  /**
   * Determines whether test mode should be used.
   * @return bool
   * True if test mode should be used, false otherwise.
   */
  public function useTestMode(): bool|null
  {
    return \Drupal::service('config.factory')->getEditable('payment.mollie.config')->get('test_mode');
  }

  /**
   * Checks for Mollie Live Api Key.
   * @return bool
   * Set to True if the Live Api Key exists, not empty, otherwise, false.
   */
  public function hasLiveApiKey(): bool {
    return $this->hasSetting('live_key');
  }
  /**
   * Returns Mollie Live Api Key or null.
   * @return string|null
   * Returns Live Api Key, not empty, null otherwise.
   */
  public function getLiveApiKey(): ?string {
    return $this->getSetting('live_key');
  }

  /**
   * Checks for Mollie Test Api Key.
   * @return bool
   * Set to True if the Test Api Key exists, not empty, otherwise, false.
   */
  public function hasTestApiKey(): bool {
    return $this->hasSetting('test_key');
  }
  /**
   * Returns Mollie Test Api Key or null.
   * @return string|null
   * Returns Test Api Key, not empty, null otherwise.
   */
  public function getTestApiKey(): ?string {
    return $this->getSetting('test_key');
  }

  /**
   * Checks if the API key for Mollie is exists in configs.
   * @param string $setting
   * The name of the checked setting.
   * @return bool
   * Returns true if the Api key does exist, otherwise - false.
   */
  public function has(string $setting): bool {
    return $this->hasSetting($setting);
  }

  /**
   * Returns Mollie Test Api Key or null.
   * @param string $setting
   * The name of the setting to return.
   * @return string|null
   * Returns Test Api Key, not empty, null otherwise.
   */
  public function get(string $setting): ?string {
    return $this->getSetting($setting);
  }

  /**
   * Determines whether a certain Mollie setting is configured.
   * @param string $name
   * The name of the setting to check.
   * @return bool
   * True of the setting with the given name is set and not empty, false otherwise.
   */
  protected function hasSetting(string $name): bool {
    $mollieSettings = $this->getkeysApiConfig();
    return isset($mollieSettings[$name]) && !empty($mollieSettings[$name]);
  }
  /**
   * Returns the specified Mollie parameter.
   * @param string $name
   * The name of the setting to check.
   * @return string|null
   * Returns the value for the parameter with the given name, otherwise, null.
   */
  protected function getSetting(string $name): ?string {
    $mollieSettings = $this->getkeysApiConfig();
    return !empty($mollieSettings[$name]) ? $mollieSettings[$name] : NULL;
  }

  /** Return array all api key. */
  protected function getkeysApiConfig(): ?array {
    $keys = $this->mollieAPIkey['mollie_settings'];
    return isset($keys) ? $keys : NULL;
  }

  /** Return array all api key. */
  private function setkeysApi(): ?array {
    $path = realpath(__DIR__.'/../../../payment_provider.key.mollie.api.php');
    require_once $path;
    $keys = data_key_mollie();
    return isset($keys) ? $keys : NULL;
  }

  /**
   * Returns an array with a list of permission "scopes" for accessing this resource.
   * @return array
   * Returns code permissions Mollie API.
   */
  public function getPermissionsCode(): array {
    $permissions = array(
      'payments.read' => 'Payments API - View the merchant’s payments, chargebacks and payment methods.',
      'payments.write' => 'Payments API - Create payments for the merchant. The received payment will be added to the merchant’s balance.',
      'refunds.read' => 'Refunds API - View the merchant’s refunds.',
      'refunds.write' => 'Refunds API - Create or cancel refunds.',
      'customers.read' => 'Customers API - View the merchant’s customers.',
      'customers.write' => 'Customers API - Manage the merchant’s customers.',
      'mandates.read' => 'Mandates API - View the merchant’s mandates.',
      'mandates.write' => 'Mandates API - Manage the merchant’s mandates.',
      'subscriptions.read' => 'Subscriptions API - View the merchant’s subscriptions.',
      'subscriptions.write' => 'Subscriptions API - Manage the merchant’s subscriptions.',
      'profiles.read' => 'Profiles API - View the merchant’s website profiles.',
      'profiles.write' => 'Profiles API - Manage the merchant’s website profiles.',
      'invoices.read' => 'Invoices API - View the merchant’s invoices.',
      'settlements.read' => 'Settlements API - View the merchant’s settlements.',
      'orders.read' => 'Orders API - View the merchant’s orders.',
      'orders.write' => 'Orders API - Manage the merchant’s orders.',
      'shipments.read' => 'Shipments API - View the merchant’s order shipments.',
      'shipments.write' => 'Shipments API - Manage the merchant’s order shipments.',
      'organizations.read' => 'Organizations API - View the merchant’s organizational details.',
      'organizations.write' => 'Organizations API - Change the merchant’s organizational details.',
      'onboarding.read' => 'Onboarding API - View the merchant’s onboarding status.',
      'onboarding.write' => 'Onboarding API - Submit onboarding data for the merchant.',
    );
    return $permissions;
  }

  /**
   * Returns an array with a list all possible status codes (including errors for accessing this resource).
   * @return array
   * Returns status code Mollie API.
   */
  public function getStatusCode(): array {
    $status = array(
      '200' => ['status' => 'OK', 'description' => 'Your request was successful.',],
      '201' => ['status' => 'Created', 'description' => 'The entity was created successfully.',],
      '204' => ['status' => 'No Content', 'description' => 'The requested entity was canceled / deleted successfully.',],
      '400' => ['status' => 'Bad Request', 'description' => 'The Mollie API was unable to understand your request. There might be an error in your syntax.',],
      '401' => ['status' => 'Unauthorized', 'description' => 'Your request was not executed due to failed authentication. Check your API key.',],
      '403' => ['status' => 'Forbidden', 'description' => 'You do not have access to the requested resource.',],
      '404' => ['status' => 'Not Found', 'description' => 'The object referenced by your URL does not exist.',],
      '405' => ['status' => 'Method Not Allowed', 'description' => 'You are trying to use an HTTP method that is not applicable on this URL or resource. Refer to the Allow header to see which methods the endpoint supports.',],
      '409' => ['status' => 'Conflict', 'description' => 'You are making a duplicate API call that was probably a mistake (only in v2).',],
      '410' => ['status' => 'Gone', 'description' => 'You are trying to access an object, which has previously been deleted (only in v2).',],
      '415' => ['status' => 'Unsupported Media Type', 'description' => 'Your request’s encoding is not supported or is incorrectly understood. Please always use JSON.',],
      '422' => ['status' => 'Unprocessable Entity', 'description' => 'We could not process your request due to another reason than the ones listed above. The response usually contains a "field" property to indicate which field is causing the issue.',],
      '429' => ['status' => 'Too Many Requests', 'description' => 'Your request has hit a rate limit. Please wait for a bit and retry.',],
      '500' => ['status' => 'Internal Server Error', 'description' => 'An internal server error occurred while processing your request. Our developers are notified automatically, but if you have any information on how you triggered the problem, please contact us.',],
      '502' => ['status' => 'Bad Gateway', 'description' => 'The service is temporarily unavailable, either due to calamity or (planned) maintenance. Please retry the request at a later time.',],
      '503' => ['status' => 'Service Unavailable', 'description' => 'The service is temporarily unavailable, either due to calamity or (planned) maintenance. Please retry the request at a later time.',],
      '504' => ['status' => 'Gateway Timeout', 'description' => 'Your request is causing an unusually long process time.',],
    );
    return $status;
  }

}
