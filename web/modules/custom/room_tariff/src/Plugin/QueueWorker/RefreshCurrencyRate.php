<?php

namespace  Drupal\room_tariff\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;

/**
 * Updates a currency rate for tariff.
 *
 * @QueueWorker(
 *   id = "refresh_currency",
 *   title = @Translation("Refresh currency rate"),
 *   cron = {"time" = 60}
 * )
 */
class RefreshCurrencyRate extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    
    /** @var \Drupal\room_tariff\Service\CurrencyQuotesList $fin_services */
    $fin_services = \Drupal::service('room_tariff.currency_quotes_list');

    if ($service = $fin_services->getObjService($data)) {
      $rate = $service->refreshCurrencyRate();
      if ($rate) {
        \Drupal::logger('room_tariff')->notice('Currency exchange rates have been updated for: '.$data.'. Relevant on '.$rate['date'].'.');
      } else {
        \Drupal::logger('room_tariff')->notice('Failed to update currency exchange rates for: '.$data);
      };
    } else {
      \Drupal::logger('room_tariff')->notice('Failed processed item: '.$data.' for update currency exchange rates');
    };

  }

}
