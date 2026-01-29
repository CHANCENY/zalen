<?php

namespace Drupal\reservation\Plugin\views\field;

use Drupal\views\Plugin\views\field\EntityField;
use Drupal\views\ResultRow;

/**
 * Views field display for reservationed entity.
 *
 * @ViewsField("reservationed_entity")
 */
class ReservationedEntity extends EntityField {

  /**
   * Array of entities that has reservations.
   *
   * We use this to load all the reservationed entities of same entity type at once
   * to the EntityStorageController static cache.
   *
   * @var array
   */
  protected $loadedReservationedEntities = [];

  /**
   * {@inheritdoc}
   */
  public function getItems(ResultRow $values) {
    if (empty($this->loadedReservationedEntities)) {
      $result = $this->view->result;

      $entity_ids_per_type = [];
      foreach ($result as $value) {
        /** @var \Drupal\reservation\ReservationInterface $reservation */
        if ($reservation = $this->getEntity($value)) {
          $entity_ids_per_type[$reservation->getReservationedEntityTypeId()][] = $reservation->getReservationedEntityId();
        }
      }

      foreach ($entity_ids_per_type as $type => $ids) {
        $this->loadedReservationedEntities[$type] = $this->entityTypeManager->getStorage($type)->loadMultiple($ids);
      }
    }

    return parent::getItems($values);
  }

}
