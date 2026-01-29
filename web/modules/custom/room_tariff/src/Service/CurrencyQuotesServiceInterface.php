<?php

/**
 * @file
 * Contains Drupal\room_tariff\Service\CurrencyQuotesServiceInterface.
 */

namespace Drupal\room_tariff\Service;

/**
 * Interface for currency quotes.
 */
interface CurrencyQuotesServiceInterface {

  // base methods

  /**
   * The services id.
   * @return string id currently instance of the class.
   */
  public function getId(): string;

  /**
   * @return string|\Drupal\Core\StringTranslation\TranslatableMarkup Returns The services label.
   */
  public function getLabel();
  
  // methods to be implemented

  /**
   * Returns default list availability Currency.
   * @return array
   */
  public function getDefaultListCurrency(): array;

  /**
   * @return array The base currency Formar [ An ISO 4217 currency code => transcript description ].
   * @see https://en.wikipedia.org/wiki/ISO_4217
   */
  public function getBaseCurrency(): array;

  /**
   * Returns default configuration.
   * @return array
   */
  public function getDefaultConfigs(): array;

  /**
   * Returns exchange rates via curl.
   */
  public function downloadCurlExchangeRates(string $url = '');

  /**
   * Returns exchange rates.
   */
  public function downloadExchangeRates(string $url = '');

  /**
   * Parse exchange rates.
   * @param mixed $value
   */
  public function parseExchangeRates($value);

  /**
   * Checking the received currency exchange rate data.
   * @param array $value
   */
  public function validate($value);

  /**
   * Is actual on date?
   * @param string $value
   */
  public function isActual($value);

  /**
   * Get exchange rate state.
   * @return array|null
   */
  public function getCurrencyState(): ?array;

  /**
   * Save exchange rate state.
   * @return void
   */
  public function setCurrencyState(array $value): void;

  /**
   * Get exchange rate frm state or load new.
   * @return array
   */
  public function getCurrencyRate(): array;

  /**
   * Refresh exchange rate.
   * @return array|null
   */
  public function refreshCurrencyRate(): ?array;

}