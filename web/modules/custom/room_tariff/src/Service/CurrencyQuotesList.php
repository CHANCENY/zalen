<?php

/**
 * @file
 * Contains Drupal\room_tariff\Service\CurrencyQuotesList.
 */

namespace Drupal\room_tariff\Service;

use Drupal\room_tariff\Service\CurrencyQuotesServiceInterface;
use Drupal\Core\Config\Config;

/**
 * Manager for currency quotes.
 */
class CurrencyQuotesList {

  /**
   * The services array.
   * @var \Drupal\room_tariff\Service\CurrencyQuotesServiceInterface[]
   */
  protected $financialInstitutions = [];

  /**
   * The services list.
   * @var array $services
   */
  public $services;

  /**
   * The services configuration.
   * @var \Drupal\Core\Config\Config $configuration
   */
  protected $configuration;

  /**
   * Gets services list.
   * @param array $instanses The service classes.
   */
  public function getService($instanses): void {
    //$test = func_get_args();
    $services = [];
    foreach ($instanses as $instanse) {
      $service = new $instanse;
      if (is_subclass_of($service, 'Drupal\room_tariff\Service\CurrencyQuotesServiceInterface', false)) {
        $services[$service->getId()] = $service;
        $this->services[$service->getId()] = array(
          'id' => $service->getId(),
          'label' => $service->getLabel(),
          'description' => $service->getDescription(),
        );
      };
    };
    $this->financialInstitutions = $services;
    return;
  }

  /**
   * Gets service object from id.
   * @param string $id The service id.
   * @return \Drupal\room_tariff\Service\CurrencyQuotesServiceInterface|null object.
   */
  public function getObjService(string $id): ?CurrencyQuotesServiceInterface {
    return $this->financialInstitutions[$id] ?? null;
  }

  // Working with field configs. //

  /**
   * @return \Drupal\field\Entity\FieldStorageConfig[] Array storage instance.
   */
  public function getToAllFieldsStorageConfig(): array {
    // Get a list of storage fields and their data. (non-bundle specific - e.g. 'node.field_image')

    /** @var \Drupal\Core\Entity\Query\QueryInterface $entityQuery The query object that can query the given entity type. */
    $entityQuery = \Drupal::entityQuery('field_storage_config');
    // Get a list of storage fields. Allow access to all regardless of permissons.
    $field_config_ids = $entityQuery->accessCheck(FALSE)->condition('type', 'room_tariff')->condition('status', 1)->execute();

    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface The entity type manager. */
    /** @var \Drupal\field\FieldStorageConfigStorage $EntityStorage \Drupal\Core\Entity\EntityStorageInterface A storage instance. */
    $EntityStorage = \Drupal::entityTypeManager()->getStorage('field_storage_config');
    // Load all the fields.
    $field_config_entities = $EntityStorage->loadMultipleOverrideFree($field_config_ids);

    return $field_config_entities;
  }

  /**
   * @return \Drupal\field\Entity\FieldConfig[] The configs per bundle.
   */
  public function getToAllBundleFieldsConfig(): array {
    // Get a list of field instances and their data. (fields per bundle - e.g. 'node.story.field_image')

    /** @var \Drupal\Core\Entity\Query\QueryInterface $entityQuery The query object that can query the given entity type. */
    $entityQuery = \Drupal::entityQuery('field_config');
    $field_config_ids = $entityQuery->accessCheck(FALSE)->condition('field_type', 'room_tariff')->condition('status', 1)->execute();

    /** @var \Drupal\field\FieldConfigStorage $EntityStorage \Drupal\Core\Entity\EntityStorageInterface A storage instance. */
    $EntityStorage = \Drupal::entityTypeManager()->getStorage('field_config');
    // Load the data.
    $field_config_entities = $EntityStorage->loadMultipleOverrideFree($field_config_ids);
    return $field_config_entities;
  }

  /**
   * Get config object for this module.
   * @return \Drupal\Core\Config\Config A configuration object.
   */
  protected function getCurrencyConfig(): Config {
    if (!$this->configuration) {
      $this->configuration = \Drupal::configFactory()->getEditable('room_tariff.currency');
    };
    return $this->configuration;
  }

  /**
   * Get configuration used for cron on refresh for service.
   * @return mixed The configuration data.
   */
  public function getRefreshList(): mixed {
    return $this->getCurrencyConfig()->get('currency_data.cron_refresh');
  }
  /**
   * Set configuration used for cron on refresh for service.
   * @param mixed $value: Value to associate with identifier.
   * @return \Drupal\Core\Config\Config $this The configuration object.
   */
  public function setRefreshList($value): Config {
    return $this->getCurrencyConfig()->set('currency_data.cron_refresh', $value);
  }

  /**
   * Retrieve for cron info - need update the list of downloadable exchange rates.
   * @return string value configuration.
   */
  public function checkCronRefreshConfig(): string {
    return $this->getCurrencyConfig()->get('currency_data.check_changed_in');
  }
  /**
   * Mark for cron update the list of downloadable exchange rates.
   * @param string $value The string to be written in the configuration.
   * @return \Drupal\Core\Config\Config The configuration object.
   */
  public function markCronRefreshConfig(string $value): Config {
    return $this->getCurrencyConfig()->set('currency_data.check_changed_in', $value)->save();
  }


}