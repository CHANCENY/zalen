<?php

namespace Drupal\zalen_migrate\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\Core\Database\Database;

abstract class LocationProcessPluginBase extends ProcessPluginBase {

  protected function getLocationIds($value, Row $row) {
    if (empty($value)) {
      return [];
    }
    return is_array($value) ? $value : [$value];
  }

  protected function getLocationProperties($lid) {
    $database = Database::getConnection('default', 'migrate');

    return $database->select('location', 'l')
      ->fields('l', [
        'name',
        'street',
        'additional',
        'city',
        'province',
        'postal_code',
        'country',
      ])
      ->condition('lid', $lid)
      ->execute()
      ->fetchAssoc();
  }

}
