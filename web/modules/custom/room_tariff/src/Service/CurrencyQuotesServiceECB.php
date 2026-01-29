<?php

/**
 * @file
 * Contains Drupal\room_tariff\Service\CurrencyQuotesServiceECB.
 */

namespace Drupal\room_tariff\Service;

use Drupal\room_tariff\Service\CurrencyQuotesServiceBase;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * ECB for currency quotes.
 * @package Drupal\room_tariff\Service
 */
class CurrencyQuotesServiceECB extends CurrencyQuotesServiceBase {

  /**
   * {@inheritDoc}
   */
  protected $id = 'ECB';

  /**
   * {@inheritDoc}
   */
  protected $label = 'European Central Bank';

  /**
   * {@inheritDoc}
   */
  protected $description = 'Currency quotes service based on "European Central Bank" data.';

  /**
   * Default list currency available in financial organization.
   * @var array
   */
  const AVAILABLE_LIST_CURRENCY = [
    'EUR' => 'Euro',
    'USD' => 'US dollar',
    'JPY' => 'Japanese yen',
    'BGN' => 'Bulgarian lev',
    'CZK' => 'Czech koruna',
    'DKK' => 'Danish krone',
    'GBP' => 'Pound sterling',
    'HUF' => 'Hungarian forint',
    'PLN' => 'Polish zloty',
    'RON' => 'Romanian leu',
    'SEK' => 'Swedish krona',
    'CHF' => 'Swiss franc',
    'ISK' => 'Icelandic krona',
    'NOK' => 'Norwegian krone',
    'HRK' => 'Croatian kuna',
    'RUB' => 'Russian rouble',
    'TRY' => 'Turkish lira',
    'AUD' => 'Australian dollar',
    'BRL' => 'Brazilian real',
    'CAD' => 'Canadian dollar',
    'CNY' => 'Chinese yuan renminbi',
    'HKD' => 'Hong Kong dollar',
    'IDR' => 'Indonesian rupiah',
    'ILS' => 'Israeli shekel',
    'INR' => 'Indian rupee',
    'KRW' => 'South Korean won',
    'MXN' => 'Mexican peso',
    'MYR' => 'Malaysian ringgit',
    'NZD' => 'New Zealand dollar',
    'PHP' => 'Philippine peso',
    'SGD' => 'Singapore dollar',
    'THB' => 'Thai baht',
    'ZAR' => 'South African rand',
  ];

  /**
   * Base currency for current financial organization.
   * @var string
   */
  const BASE_CURRENCY = 'EUR';

  /**
   * Path download for currency rate.
   * @var string
   */
  const PATH_DOWNLOAD = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml';

  /**
   * Time when currency quotes are updated.
   * @var string
   */
  const REFRESH_TIME = '16:30:00';

  /**
   * Currency quotes update period.
   * @var string
   * @see https://www.php.net/manual/ru/datetime.format.php.
   */
  const REFRESH_INTERVAL = '1 day';

  /**
   * The time zone in which the financial organization is located.
   * @var string
   */
  const TIME_ZONE = 'CET';

  /**
   * Date time format use for service.
   * @var \DateTimeInterface::RFC3339 = "Y-m-d\TH:i:sP"
   * @see https://www.php.net/manual/ru/class.datetime.php.
   */
  const DATE_TIME_FORMAT = \DateTimeInterface::RFC3339;

  /**
   * Default curl options.
   * @var array
   */
  const CURL_OPTIONS = array(
    ['CURLOPT_USERAGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win32; x86) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/99.0 Safari/537.36'],
  );

  /**
   * {@inheritDoc}
   */
  public function getDefaultListCurrency(): array {
    $currency = self::AVAILABLE_LIST_CURRENCY;
    foreach ($currency as &$value) {
      $value = $this->t($value);
    };
    return $currency;
  }

  /**
   * {@inheritDoc}
   */
  public function getDefaultConfigs(): array {
    return [
      'base_currency' => self::BASE_CURRENCY,
      'url' => self::PATH_DOWNLOAD,
      'refresh_time' => self::REFRESH_TIME,
      'refresh_interval' => self::REFRESH_INTERVAL,
      'time_zone' => self::TIME_ZONE,
      'curl_options' => self::CURL_OPTIONS,
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function downloadCurlExchangeRates(string $url = self::PATH_DOWNLOAD, array $config = ['timeout_request' => 10,]) {

    $timeout = intval($config['timeout_request'], 10);

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
        throw new \Exception($this->t('Error updating date: @date, http code:@code.', ['@date' => (new DrupalDateTime)->format('Y-m-d H:i:s'), '@code' => $status,]));
      };

      return $this->parseExchangeRates($response);
    }
    catch (\Exception $e) {
      // Make an entry about this error.
      $this->getlogChannel('room_tariff')->error($this->t('Download of exchange rates failed: @error', ['@error' => $e->getMessage()]));

      // Show a message to users with administration privileges.
      if (\Drupal::currentUser()->hasPermission('administer site configuration')) {
        $this->getMessenger()->addError($this->t('Download of exchange rates failed: @error', ['@error' => $e->getMessage()]));
      };

      return [];
    }
  }

  /**
   * {@inheritDoc}
   */
  public function downloadExchangeRates(string $url = self::PATH_DOWNLOAD, array $config = ['timeout_request' => 10,]) {
    // Specify timeout in seconds.
    $timeout = intval($config['timeout_request'], 10);

    /** @var \GuzzleHttp\Client $client */
    $client = $this->getHttpClient();
    try {
      $response = $client->get($url, ['timeout' => $timeout]);
      // Extract XML data from the received forecast.
      return $this->parseExchangeRates($response->getBody());
    }
    catch (\Exception $e) {
      // Make an entry about this error.
      $this->getlogChannel('room_tariff')->error($this->t('Download of exchange rates failed: @error', ['@error' => $e->getMessage()]));

      // Show a message to users with administration privileges.
      if (\Drupal::currentUser()->hasPermission('administer site configuration')) {
        $this->getMessenger()->addError($this->t('Download of exchange rates failed: @error', ['@error' => $e->getMessage()]));
      };

      return [];
    };
  }

  /**
   * {@inheritDoc}
   */
  public function parseExchangeRates($value) {
    $value = new \SimpleXMLElement($value);

    $currency['date'] = $value->Cube->Cube->attributes()['time']->__toString();
    $amount = $value->Cube->Cube->children('http://www.ecb.int/vocabulary/2002-08-01/eurofxref')->count();
    $value = $value->Cube->Cube;
    for ($i=0; $i<$amount; $i++) {
      $element = $value->children()[$i];
      $currency[$element->attributes()['currency']->__toString()] = $element->attributes()['rate']->__toString();
    };
    return $this->validate($currency) ? $currency : NULL;
  }

  /**
   * {@inheritDoc}
   */
  public function validate($value): bool {
    unset($value['date']);
    if (!$value) {
      return FALSE;
    };
    foreach ($value as $k => $v) {
      if (!(preg_match('/^[A-Z]{3}$/',$k) && preg_match('/^[\d]+[\.]{1}[\d]*$/',$v))) {
        return false;
      };
    };
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function isActual($value) {
    $date_currency_updates = new DrupalDateTime('now',new \DateTimeZone(strval(self::TIME_ZONE)));
    
    // Because the date on the data is written in days as (Cube time="2022-01-01").
    // We will check if the update hours have already arrived on that day. If yes, then subtract the interval.
    $date_value = new DrupalDateTime($value['date'].' '.self::REFRESH_TIME,new \DateTimeZone(strval(self::TIME_ZONE)));
    $late_mistake = '+3 hours';
    return $date_value->modify('+' . self::REFRESH_INTERVAL)->modify($late_mistake) > $date_currency_updates;
  }

  /**
   * Check if the data is newer than the current state.
   * @param array $data new data.
   * @param array $old_data old data.
   * @return bool
   */
  public function isNewer(array $data, array $old_data): bool {
    $data = new \DateTime($data['date'].' '.self::REFRESH_TIME,new \DateTimeZone(strval(self::TIME_ZONE)));
    $old_data = new \DateTime($old_data['date'].' '.self::REFRESH_TIME,new \DateTimeZone(strval(self::TIME_ZONE)));
    return $data > $old_data;
  }

  /**
   * {@inheritDoc}
   */
  public function refreshCurrencyRate(): ?array {
    if ($rate = $this->downloadExchangeRates()) {
      if (!$this->validate($rate)) {
        return NULL;
      };
      if ($rate_old = $this->getCurrencyState()) {
        if (0 > (new \DateTime($rate['date']))->diff(new \DateTime($rate_old['date']))->format('%d')) {
          return $rate;
        };
      };
      $this->setCurrencyState($rate);
      return $rate;
    };
    return NULL;
  }

}