<?php

namespace Drupal\opentime;

use Drupal\node\NodeInterface;
use Drupal\Core\Datetime\DrupalDateTime;

class OpentimeManager {

  /**
   * Gets the opening hours for a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   *
   * @return array
   *   An array of opening hours.
   */
  public function getOpeningHours(NodeInterface $node) {
    $opening_hours = [];
    if ($node->hasField('field_openingsuren')) {
      $field_items = $node->get('field_openingsuren')->getValue();
      foreach ($field_items as $item) {
        $days = json_decode($item['days'], TRUE);
        $start_time = $item['start_time'];
        $end_time = $item['end_time'];

        $opening_hours[] = [
          'days' => $days,
          'start_time' => $start_time,
          'end_time' => $end_time,
        ];
      }
    }
    return $opening_hours;
  }
}
