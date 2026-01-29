<?php

namespace Drupal\zalen_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * @MigrateProcessPlugin(
 *   id = "location_to_address",
 *   handle_multiples = TRUE
 * )
 */
class LocationToAddress extends LocationProcessPluginBase {

  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (empty($lids = $this->getLocationIds($value, $row))) {
      return NULL;
    }

    $processed_values = [];

    foreach ($lids as $lid) {
      $location = $this->getLocationProperties($lid);

      if (!$location) {
        continue;
      }

      $processed_values[] = [
        'country_code' => strtoupper($location['country']),
        'postal_code' => $location['postal_code'],
        'locality' => $location['city'],
        'administrative_area' => $location['province'],
        'address_line1' => $location['street'],
        'address_line2' => $location['additional'],
        'organization' => $location['name'],
      ];
    }

    return $processed_values;
  }

}
