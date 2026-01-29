<?php

namespace Drupal\reservation\Plugin\migrate\destination;

use Drupal\migrate\Plugin\migrate\destination\EntityConfigBase;
use Drupal\migrate\Row;

/**
 * @MigrateDestination(
 *   id = "entity:reservation_type"
 * )
 */
class EntityReservationType extends EntityConfigBase {

  /**
   * {@inheritdoc}
   */
  public function import(Row $row, array $old_destination_id_values = []) {
    $entity_ids = parent::import($row, $old_destination_id_values);
    \Drupal::service('reservation.manager')->addBodyField(reset($entity_ids));
    return $entity_ids;
  }

}
