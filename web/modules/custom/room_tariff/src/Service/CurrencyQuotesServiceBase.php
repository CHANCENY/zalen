<?php

/**
 * @file
 * Contains Drupal\room_tariff\Service\CurrencyQuotesServiceBase.
 */

namespace Drupal\room_tariff\Service;

use Drupal\room_tariff\Service\CurrencyQuotesServiceInterface;
//use Symfony\Component\DependencyInjection\ContainerInterface;
//use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
//use \Drupal\Core\StringTranslation\TranslationManager;
//use Drupal\Core\StringTranslation\TranslatableMarkup;
//use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Psr\Log\LoggerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Config\Config;
use GuzzleHttp\Client;

/**
 * Base for currency quotes.
 * @package Drupal\room_tariff\Service
 */
abstract class CurrencyQuotesServiceBase implements CurrencyQuotesServiceInterface {
  use StringTranslationTrait;

  /**
   * @var string $id The services id.
   */
  protected $id = '';

  /**
   * @var string $label The services label.
   */
  protected $label = '';

  /**
   * @var string $description The services description.
   */
  protected $description = 'Currency quotes service.';

  /**
   * The currently active container object, or NULL if not initialized yet.
   * @var \Symfony\Component\DependencyInjection\ContainerInterface|null $container
   */
  private $container;

  /**
   * A logger service.
   * @var \Psr\Log\LoggerInterface|null $loggerFactory
   */
  protected $loggerFactory;

  /**
   * A state storage service.
   * @var \Drupal\Core\State\StateInterface|null $state
   */
  protected $state;

  /**
   * The messenger.
   * @var \Drupal\Core\Messenger\MessengerInterface|null $messenger
   */
  protected $messenger;

  /**
   * The configuration factory.
   * @var \Drupal\Core\Config\ConfigFactoryInterface|null $configFactory
   * The configuration factory service.
   */
  protected $configFactory;

  /**
   * The default http client.
   * @var \GuzzleHttp\Client|null $httpClient
   */
  protected $httpClient;

  /**
   * {@inheritDoc}
   */
  public function getId(): string {
    return $this->id ? $this->id : 'no_specified';
  }

  /**
   * Returns the currently active global container.
   * @return \Symfony\Component\DependencyInjection\ContainerInterface|null
   */
  private function getContainer(): ?ContainerInterface {
    if (!$this->container) {
      $this->container = \Drupal::getContainer();
    };
    return $this->container;
  }

  /**
   * Returns a channel logger object.
   * @param string $channel The name of the channel.
   * * Can be any string, but the general practice is to use the name of the subsystem calling this.
   * @return \Psr\Log\LoggerInterface The logger for this channel.
   */
  public function getlogChannel(string $channel): LoggerInterface {
    if (!$this->loggerFactory) {
      $this->loggerFactory = $this->getContainer()->get('logger.factory');
    };
    return $this->loggerFactory->get($channel);
  }

  /**
   * Returns the messenger.
   * @return \Drupal\Core\Messenger\MessengerInterface The messenger.
   */
  public function getMessenger(): MessengerInterface {
    if (!$this->messenger) {
      $this->messenger = $this->getContainer()->get('messenger');
    };
    return $this->messenger;
  }

  /**
   * Returns a configuration object.
   * @param string $name The name of the configuration object to retrieve,
   * which typically corresponds to a configuration file.
   * @return \Drupal\Core\Config\ImmutableConfig|\Drupal\Core\Config\Config|mixed
   * An immutable configuration object | configuration object | value per name.
   */
  public function getConfiguration(?string $name = '', bool $editable = FALSE) {
    if (!$this->configFactory) {
      $this->configFactory = $this->getContainer()->get('config.factory');
    };
    $genus = 'room_tariff.currency';
    $config = $editable ? $this->configFactory->getEditable($genus) : $this->configFactory->get($genus);
    return $name ? $config->get($name) : $config;
  }

  /**
   * Sets a value in this configuration object.
   * @param string $key Identifier to store value in configuration.
   * @param mixed $value Value to set.
   * @return \Drupal\Core\Config\Config $this The configuration object.
   * @throws \Drupal\Core\Config\ConfigValueException If $value is an array and any of its keys in any depth contains a dot.
   */
  public function setConfiguration(string $key, mixed $value): Config {
    return $this->getConfiguration('', true)->set($key, $value);
  }

  /**
   * Returns the state storage service.
   * @return \Drupal\Core\State\StateInterface
   */
  public function getState() {
    if (!$this->state) {
      $this->state = $this->getContainer()->get('state');
    };
    return $this->state;
  }

  /**
   * Returns the default http client.
   * @return \GuzzleHttp\Client A guzzle http client instance..
   */
  public function getHttpClient(): Client {
    if (!$this->httpClient) {
      $this->httpClient = $this->getContainer()->get('http_client');
    };
    return clone $this->httpClient;
  }

  /**
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup Returns The services label.
   */
  public function getLabel() {
    return $this->label ? $this->t($this->label) : 'no_specified';
  }

  /**
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup The services description.
   */
  public function getDescription() {
    return $this->t($this->description);
  }

  /**
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup[] The base currency.
   */
  public function getBaseCurrency(): array {
    return [static::BASE_CURRENCY => $this->getDefaultListCurrency()[static::BASE_CURRENCY]];
  }

  // state //

  /**
   * {@inheritDoc}
   */
  public function setCurrencyState(array $value): void {
    $state = $this->getState();
    $state->set('room_tariff_currency_'.$this->getId(), $value);
  }

  /**
   * {@inheritDoc}
   */
  public function getCurrencyState(): ?array {
    $state = $this->getState();
    return $state->get('room_tariff_currency_'.$this->getId());
  }

  /**
   * {@inheritDoc}
   */
  public function getCurrencyRate(): array {
    if (!$rate = $this->getCurrencyState()) {
      $rate = $this->downloadExchangeRates();
      $this->validate($rate) ? $this->setCurrencyState($rate) : $rate = [];
    }
    return $rate;
  }

  /**
   * @param int|string $monney Amount of money to exchange.
   * @param string $currency_from Currency current.
   * @param string $currency_from Currency after exchange.
   * @return string|null The services description.
   */
  public function getMoneyExchange($monney, string $currency_from, string $currency_to): ?string {
    $base = static::BASE_CURRENCY;
    $rates = $this->getCurrencyRate();
    $currency_from = mb_strtoupper($currency_from, 'UTF-8');
    $currency_to = mb_strtoupper($currency_to, 'UTF-8');
    if ($currency_from == $currency_to) {
      return $monney;
    };
    $monney = $monney*100;
    if ($base == $currency_from) {
      $result = $monney * $rates[$currency_to];
    } else if ($base == $currency_to) {
      $result = $monney / $rates[$currency_from];
    } else {
      $result = $monney / $rates[$currency_from] * $rates[$currency_to];
    };
    return isset($result) ? number_format((float) $result/100, 2, '.', '') : NULL;
  }

}