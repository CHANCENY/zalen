<?php

namespace Drupal\reservation\Plugin\EntityReferenceSelection;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;
use Drupal\reservation\ReservationInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Provides specific access control for the reservation entity type.
 *
 * @EntityReferenceSelection(
 *   id = "default:reservation",
 *   label = @Translation("Reservation selection"),
 *   entity_types = {"reservation"},
 *   group = "default",
 *   weight = 1
 * )
 */
class ReservationSelection extends DefaultSelection {

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    $query = parent::buildEntityQuery($match, $match_operator);

    // Adding the 'reservation_access' tag is sadly insufficient for reservations:
    // core requires us to also know about the concept of 'published' and
    // 'unpublished'.
    if (!$this->currentUser->hasPermission('administer reservations')) {
      $query->condition('status', ReservationInterface::PUBLISHED);
    }
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function createNewEntity($entity_type_id, $bundle, $label, $uid) {
    $reservation = parent::createNewEntity($entity_type_id, $bundle, $label, $uid);

    // In order to create a referenceable reservation, it needs to published.
    /** @var \Drupal\reservation\ReservationInterface $reservation */
    $reservation->setPublished();

    return $reservation;
  }

  /**
   * {@inheritdoc}
   */
  public function validateReferenceableNewEntities(array $entities) {
    $entities = parent::validateReferenceableNewEntities($entities);
    // Mirror the conditions checked in buildEntityQuery().
    if (!$this->currentUser->hasPermission('administer reservations')) {
      $entities = array_filter($entities, function ($reservation) {
        /** @var \Drupal\reservation\ReservationInterface $reservation */
        return $reservation->isPublished();
      });
    }
    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  public function entityQueryAlter(SelectInterface $query) {
    parent::entityQueryAlter($query);

    $tables = $query->getTables();
    $data_table = 'reservation_field_data';
    if (!isset($tables['reservation_field_data']['alias'])) {
      // If no conditions join against the reservation data table, it should be
      // joined manually to allow node access processing.
      $query->innerJoin($data_table, NULL, "base_table.cid = $data_table.cid AND $data_table.default_langcode = 1");
    }

    // The Reservation module doesn't implement any proper reservation access,
    // and as a consequence doesn't make sure that reservations cannot be viewed
    // when the user doesn't have access to the node.
    $node_alias = $query->innerJoin('node_field_data', 'n', '%alias.nid = ' . $data_table . '.entity_id AND ' . $data_table . ".entity_type = 'node'");
    // Pass the query to the node access control.
    $this->reAlterQuery($query, 'node_access', $node_alias);
    // Passing the query to node_query_node_access_alter() is sadly
    // insufficient for nodes.
    // @see \Drupal\node\Plugin\EntityReferenceSelection\NodeSelection::buildEntityQuery()
    if (!$this->currentUser->hasPermission('bypass node access') && !count(\Drupal::moduleHandler()->invokeAll('node_grants'))) {
      $query->condition($node_alias . '.status', 1);
    }
  }

}
