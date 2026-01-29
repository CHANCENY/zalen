<?php

/**
 * @file
 * Contains Drupal\room_tariff\Service\CurrencyQuotesServiceNBU.
 */

namespace Drupal\room_tariff\Service;

use Drupal\room_tariff\Service\CurrencyQuotesServiceBase;

/**
 * NBU for currency quotes.
 * @package Drupal\room_tariff\Service
 */
class CurrencyQuotesServiceNBU extends CurrencyQuotesServiceBase {

  /**
   * {@inheritDoc}
   */
  protected $id = 'N_T';

  /**
   * Base currency for current financial organization.
   * @var string
   */
  const BASE_CURRENCY = 'JPY';

  /**
   * {@inheritDoc}
   */
  public function getDefaultListCurrency(): array {
    return [
      'EUR' => new \Drupal\Core\StringTranslation\TranslatableMarkup('Euro test'),
      'JPY' => new \Drupal\Core\StringTranslation\TranslatableMarkup('Japanese yen test'),
      'GBP' => new \Drupal\Core\StringTranslation\TranslatableMarkup('Pound sterling test'),
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function getDefaultConfigs(): array {
    return ['NBU_test'];
  }

  /**
   * {@inheritDoc}
   */
  public function downloadCurlExchangeRates(string $url = '') {
    return ['EUR' => 'NBU_Euro_test', 'GBP' => 'NBU_Pound_test',];
  }

  /**
   * {@inheritDoc}
   */
  public function downloadExchangeRates(string $url = '') {
    return ['EUR' => 'NBU_Euro_test', 'GBP' => 'NBU_Pound_test',];
  }

  /**
   * {@inheritDoc}
   */
  public function parseExchangeRates($value) {
    return ['NBU_test'];
  }

  /**
   * {@inheritDoc}
   */
  public function validate($value) {
    return true;
  }

  /**
   * {@inheritDoc}
   */
  public function isActual($value) {
    return true;
  }

  /**
   * {@inheritDoc}
   */
  public function refreshCurrencyRate(): ?array {
    return [];
  }

}