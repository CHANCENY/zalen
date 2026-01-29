<?php

/**
 * @file
 * Contains Drupal\room_tariff\TariffCurrencyParser.
 */

namespace Drupal\room_tariff;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Parser for currency.
 */
class TariffCurrencyParser {

  /**
   * Data required to build a query to retrieve currency data from sources.
   * @var array $dataCurrency
   */
  protected $dataCurrency;

  /**
   * Settings for getting the exchange rate.
   * @var array $currencyConfig
   */
  protected $currencyConfig;

  /**
   * Logger ('logger.factory').
   * @var \Drupal\Core\Logger\LoggerChannelFactory $logger
   */
  protected $logger;

  /**
   * Messenger ('messenger').
   * @var \Drupal\Core\Messenger\Messenger $messenger
   */
  protected $messenger;

  /**
   * Config ('config.factory').
   * @var \Drupal\Core\Config\ConfigFactory $config
   */
  protected $config;

  /**
   * Config ('http_client').
   * @var \GuzzleHttp\Client $httpClient
   */
  protected $httpClient;

  public function __construct() {
    $this->dataCurrency = $this->getDefaultDataCurrency();
    $this->currencyConfig = $this->getDefaultConfig();
    $this->logger = \Drupal::service('logger.factory');
    $this->messenger = \Drupal::service('messenger');
    $this->config = \Drupal::configFactory();
    $this->httpClient = \Drupal::httpClient();
  }

  /**
   * Returns default data Currency.
   */
  public function getDefaultDataCurrency() {
    return [
      'currency' => [
        'EUR' => new TranslatableMarkup('Euro'),
        'USD' => new TranslatableMarkup('US dollar'),
        'JPY' => new TranslatableMarkup('Japanese yen'),
        'BGN' => new TranslatableMarkup('Bulgarian lev'),
        'CZK' => new TranslatableMarkup('Czech koruna'),
        'DKK' => new TranslatableMarkup('Danish krone'),
        'GBP' => new TranslatableMarkup('Pound sterling'),
        'HUF' => new TranslatableMarkup('Hungarian forint'),
        'PLN' => new TranslatableMarkup('Polish zloty'),
        'RON' => new TranslatableMarkup('Romanian leu'),
        'SEK' => new TranslatableMarkup('Swedish krona'),
        'CHF' => new TranslatableMarkup('Swiss franc'),
        'ISK' => new TranslatableMarkup('Icelandic krona'),
        'NOK' => new TranslatableMarkup('Norwegian krone'),
        'HRK' => new TranslatableMarkup('Croatian kuna'),
        'RUB' => new TranslatableMarkup('Russian rouble'),
        'TRY' => new TranslatableMarkup('Turkish lira'),
        'AUD' => new TranslatableMarkup('Australian dollar'),
        'BRL' => new TranslatableMarkup('Brazilian real'),
        'CAD' => new TranslatableMarkup('Canadian dollar'),
        'CNY' => new TranslatableMarkup('Chinese yuan renminbi'),
        'HKD' => new TranslatableMarkup('Hong Kong dollar'),
        'IDR' => new TranslatableMarkup('Indonesian rupiah'),
        'ILS' => new TranslatableMarkup('Israeli shekel'),
        'INR' => new TranslatableMarkup('Indian rupee'),
        'KRW' => new TranslatableMarkup('South Korean won'),
        'MXN' => new TranslatableMarkup('Mexican peso'),
        'MYR' => new TranslatableMarkup('Malaysian ringgit'),
        'NZD' => new TranslatableMarkup('New Zealand dollar'),
        'PHP' => new TranslatableMarkup('Philippine peso'),
        'SGD' => new TranslatableMarkup('Singapore dollar'),
        'THB' => new TranslatableMarkup('Thai baht'),
        'ZAR' => new TranslatableMarkup('South African rand'),
      ],
    ];
  }

  /**
   * Returns default configuration.
   */
  public function getDefaultConfig() {
    return [
      'base_currency' => 'EUR',
      'url' => 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml',
      'provider' => 'European Central Bank',
      'time_zone' => 'CET',
      'curl_options' => array(
        ['CURLOPT_USERAGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win32; x86) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/99.0 Safari/537.36'],
      ),
    ];
  }

  public function downloadCurlExchangeRates(string $url = '') {
    $url = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml';
    $timeout = 10;

    try {

      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
      curl_setopt($ch, CURLOPT_HEADER, FALSE);
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
      curl_setopt($ch, CURLOPT_MAXREDIRS, 3);

      $response = curl_exec($ch);
      $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

      curl_close($ch);

      if (!$response){
        throw new \Exception(new TranslatableMarkup('Error updating date: @date, http code:@code.', ['@date' => (new DrupalDateTime)->format('Y-m-d H:i:s'), '@code' => $status,]));
      };

      return $this->parseExchangeRates($response);
    }
    catch (\Exception $e) {
      // Make an entry about this error.
      $this->logger->get('room_tariff')->error(new TranslatableMarkup('Download of exchange rates failed: @error', ['@error' => $e->getMessage()]));

      // Show a message to users with administration privileges.
      if (\Drupal::currentUser()->hasPermission('administer site configuration')) {
        $this->messenger->addError(new TranslatableMarkup('Download of exchange rates failed: @error', ['@error' => $e->getMessage()]));
      }
    }
  }

  public function downloadExchangeRates(string $url = '') {
    // Specify timeout in seconds.
    $url = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml';
    $timeout = 10;

    /* @var $client \GuzzleHttp\Client */
    $client = $this->httpClient;
    try {
      $response = $client->get($url, ['timeout' => $timeout]);
      // Extract XML data from the received forecast.
      return $this->parseExchangeRates($response->getBody());
    }
    catch (\Exception $e) {
      // Make an entry about this error.
      $this->logger->get('room_tariff')->error(new TranslatableMarkup('Download of exchange rates failed: @error', ['@error' => $e->getMessage()]));

      // Show a message to users with administration privileges.
      if (\Drupal::currentUser()->hasPermission('administer site configuration')) {
        $this->messenger->addError(new TranslatableMarkup('Download of exchange rates failed: @error', ['@error' => $e->getMessage()]));
      }
    }
  }

  /**
   * Parse exchange rates.
   * @param mixed $value
   */
  public function parseExchangeRates($value) {
    $value = new \SimpleXMLElement($value);

    $currency['date'] = $value->Cube->Cube->attributes()['time']->__toString();
    $currency['provider'] = 'European Central Bank';
    $currency['base_currency'] = 'EUR';
    $amount = $value->Cube->Cube->children('http://www.ecb.int/vocabulary/2002-08-01/eurofxref')->count();
    $value = $value->Cube->Cube;
    for ($i=0; $i<$amount; $i++) {
      $element = $value->children()[$i];
      $currency[$element->attributes()['currency']->__toString()] = $element->attributes()['rate']->__toString();
    };
    return $this->validate($currency) ? $currency : NULL;
  }

  /**
   * Checking the received currency exchange rate data.
   * @param array $value
   */
  public function validate($value) {
    $test = 0;
    if ($this->isActual($value)) {
      unset($value['date']);
      foreach ($value as $k => $v) {
        if (!(preg_match('/^[A-Z]{3}$/',$k) && preg_match('/^[\d]+[\.]{1}[\d]*$/',$v))) {
          return false;
        };
      };
    };
    return TRUE;
  }

  /**
   * Is actual on date?
   * @param string $value
   */
  private function isActual($value) {
    $date_currency_updates = new DrupalDateTime('now',new \DateTimeZone('CET'));
    if ($date_currency_updates->format('H') < 17) {
      $date_currency_updates->modify('-1 day');
    };
    $date_currency_updates = $date_currency_updates->format('Y-m-d');
    return $value['date'] === $date_currency_updates;
  }

}