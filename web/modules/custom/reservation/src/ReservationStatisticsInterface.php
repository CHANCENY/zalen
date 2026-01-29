<?php

namespace Drupal\reservation;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides an interface for storing and retrieving reservation statistics.
 */
interface ReservationStatisticsInterface {

  /**
   * Returns an array of ranking information for hook_ranking().
   *
   * @return array
   *   Array of ranking information as expected by hook_ranking().
   *
   * @see hook_ranking()
   * @see reservation_ranking()
   */
  public function getRankingInfo();

  /**
   * Read reservation statistics records for an array of entities.
   *
   * @param \Drupal\Core\Entity\EntityInterface[] $entities
   *   Array of entities on which reservationing is enabled, keyed by id
   * @param string $entity_type
   *   The entity type of the passed entities.
   * @param bool $accurate
   *   (optional) Indicates if results must be completely up to date. If set to
   *   FALSE, a replica database will used if available. Defaults to TRUE.
   *
   * @return object[]
   *   Array of statistics records.
   */
  public function read($entities, $entity_type, $accurate = TRUE);

  /**
   * Delete reservation statistics records for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity for which reservation statistics should be deleted.
   */
  public function delete(EntityInterface $entity);

  /**
   * Update or insert reservation statistics records after a reservation is added.
   *
   * @param \Drupal\reservation\ReservationInterface $reservation
   *   The reservation added or updated.
   */
  public function update(ReservationInterface $reservation);

  /**
   * Find the maximum number of reservations for the given entity type.
   *
   * Used to influence search rankings.
   *
   * @param string $entity_type
   *   The entity type to consider when fetching the maximum reservation count for.
   *
   * @return int
   *   The maximum number of reservations for and entity of the given type.
   *
   * @see reservation_update_index()
   */
  public function getMaximumCount($entity_type);

  /**
   * Insert an empty record for the given entity.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The created entity for which a statistics record is to be initialized.
   * @param array $fields
   *   Array of reservation field definitions for the given entity.
   */
  public function create(FieldableEntityInterface $entity, $fields);

}
