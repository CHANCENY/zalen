<?php

namespace Drupal\reservation;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\EntityOwnerInterface;

class ReservationStatistics implements ReservationStatisticsInterface {

  /**
   * The current database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The replica database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $databaseReplica;

  /**
   * The current logged in user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Constructs the ReservationStatistics service.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The active database connection.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current logged in user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Database\Connection|null $database_replica
   *   (Optional) the replica database connection.
   */
  public function __construct(Connection $database, AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager, StateInterface $state, Connection $database_replica = NULL) {
    $this->database = $database;
    $this->databaseReplica = $database_replica ?: $database;
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public function read($entities, $entity_type, $accurate = TRUE) {
    $connection = $accurate ? $this->database : $this->databaseReplica;
    $stats = $connection->select('reservation_entity_statistics', 'ces')
      ->fields('ces')
      ->condition('ces.entity_id', array_keys($entities), 'IN')
      ->condition('ces.entity_type', $entity_type)
      ->execute();

    $statistics_records = [];
    while ($entry = $stats->fetchObject()) {
      $statistics_records[] = $entry;
    }
    return $statistics_records;
  }

  /**
   * {@inheritdoc}
   */
  public function delete(EntityInterface $entity) {
    $this->database->delete('reservation_entity_statistics')
      ->condition('entity_id', $entity->id())
      ->condition('entity_type', $entity->getEntityTypeId())
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function create(FieldableEntityInterface $entity, $fields) {
    $query = $this->database->insert('reservation_entity_statistics')
      ->fields([
        'entity_id',
        'entity_type',
        'field_name',
        'cid',
        'last_reservation_timestamp',
        'last_reservation_name',
        'last_reservation_uid',
        'reservation_count',
      ]);
    foreach ($fields as $field_name => $detail) {
      // Skip fields that entity does not have.
      if (!$entity->hasField($field_name)) {
        continue;
      }
      // Get the user ID from the entity if it's set, or default to the
      // currently logged in user.
      $last_reservation_uid = 0;
      if ($entity instanceof EntityOwnerInterface) {
        $last_reservation_uid = $entity->getOwnerId();
      }
      if (!isset($last_reservation_uid)) {
        // Default to current user when entity does not implement
        // EntityOwnerInterface or author is not set.
        $last_reservation_uid = $this->currentUser->id();
      }
      // Default to REQUEST_TIME when entity does not have a changed property.
      //$last_reservation_timestamp = REQUEST_TIME;
      $request_time = \Drupal::time()->getRequestTime();
      $last_reservation_timestamp = $request_time;
      // @todo Make reservation statistics language aware and add some tests. See
      //   https://www.drupal.org/node/2318875
      if ($entity instanceof EntityChangedInterface) {
        $last_reservation_timestamp = $entity->getChangedTimeAcrossTranslations();
      }
      $query->values([
        'entity_id' => $entity->id(),
        'entity_type' => $entity->getEntityTypeId(),
        'field_name' => $field_name,
        'cid' => 0,
        'last_reservation_timestamp' => $last_reservation_timestamp,
        'last_reservation_name' => NULL,
        'last_reservation_uid' => $last_reservation_uid,
        'reservation_count' => 0,
      ]);
    }
    $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function getMaximumCount($entity_type) {
    return $this->database->query('SELECT MAX(reservation_count) FROM {reservation_entity_statistics} WHERE entity_type = :entity_type', [':entity_type' => $entity_type])->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function getRankingInfo() {
    return [
      'reservations' => [
        'title' => t('Number of reservations'),
        'join' => [
          'type' => 'LEFT',
          'table' => 'reservation_entity_statistics',
          'alias' => 'ces',
          // Default to reservation field as this is the most common use case for
          // nodes.
          'on' => "ces.entity_id = i.sid AND ces.entity_type = 'node' AND ces.field_name = 'reservation'",
        ],
        // Inverse law that maps the highest view count on the site to 1 and 0
        // to 0. Note that the ROUND here is necessary for PostgreSQL and SQLite
        // in order to ensure that the :reservation_scale argument is treated as
        // a numeric type, because the PostgreSQL PDO driver sometimes puts
        // values in as strings instead of numbers in complex expressions like
        // this.
        'score' => '2.0 - 2.0 / (1.0 + ces.reservation_count * (ROUND(:reservation_scale, 4)))',
        'arguments' => [':reservation_scale' => \Drupal::state()->get('reservation.node_reservation_statistics_scale', 0)],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function update(ReservationInterface $reservation) {
    // Allow bulk updates and inserts to temporarily disable the maintenance of
    // the {reservation_entity_statistics} table.
    if (!$this->state->get('reservation.maintain_entity_statistics')) {
      return;
    }

    $query = $this->database->select('reservation_field_data', 'c');
    $query->addExpression('COUNT(cid)');
    $count = $query->condition('c.entity_id', $reservation->getReservationedEntityId())
      ->condition('c.entity_type', $reservation->getReservationedEntityTypeId())
      ->condition('c.field_name', $reservation->getFieldName())
      ->condition('c.status', ReservationInterface::PUBLISHED)
      ->condition('default_langcode', 1)
      ->execute()
      ->fetchField();

    if ($count > 0) {
      // Reservations exist.
      $last_reply = $this->database->select('reservation_field_data', 'c')
        ->fields('c', ['cid', 'name', 'changed', 'uid'])
        ->condition('c.entity_id', $reservation->getReservationedEntityId())
        ->condition('c.entity_type', $reservation->getReservationedEntityTypeId())
        ->condition('c.field_name', $reservation->getFieldName())
        ->condition('c.status', ReservationInterface::PUBLISHED)
        ->condition('default_langcode', 1)
        ->orderBy('c.created', 'DESC')
        ->range(0, 1)
        ->execute()
        ->fetchObject();
      // Use merge here because entity could be created before reservation field.
      $this->database->merge('reservation_entity_statistics')
        ->fields([
          'cid' => $last_reply->cid,
          'reservation_count' => $count,
          'last_reservation_timestamp' => $last_reply->changed,
          'last_reservation_name' => $last_reply->uid ? '' : $last_reply->name,
          'last_reservation_uid' => $last_reply->uid,
        ])
        ->keys([
          'entity_id' => $reservation->getReservationedEntityId(),
          'entity_type' => $reservation->getReservationedEntityTypeId(),
          'field_name' => $reservation->getFieldName(),
        ])
        ->execute();
    }
    else {
      // Reservations do not exist.
      $entity = $reservation->getReservationedEntity();
      // Get the user ID from the entity if it's set, or default to the
      // currently logged in user.
      if ($entity instanceof EntityOwnerInterface) {
        $last_reservation_uid = $entity->getOwnerId();
      }
      if (!isset($last_reservation_uid)) {
        // Default to current user when entity does not implement
        // EntityOwnerInterface or author is not set.
        $last_reservation_uid = $this->currentUser->id();
      }
      $request_time = \Drupal::time()->getRequestTime();
      $this->database->update('reservation_entity_statistics')
        ->fields([
          'cid' => 0,
          'reservation_count' => 0,
          // Use the changed date of the entity if it's set, or default to
          // REQUEST_TIME.
          'last_reservation_timestamp' => ($entity instanceof EntityChangedInterface) ? $entity->getChangedTimeAcrossTranslations() : $request_time,
          'last_reservation_name' => '',
          'last_reservation_uid' => $last_reservation_uid,
        ])
        ->condition('entity_id', $reservation->getReservationedEntityId())
        ->condition('entity_type', $reservation->getReservationedEntityTypeId())
        ->condition('field_name', $reservation->getFieldName())
        ->execute();
    }

    // Reset the cache of the reservationed entity so that when the entity is loaded
    // the next time, the statistics will be loaded again.
    $this->entityTypeManager->getStorage($reservation->getReservationedEntityTypeId())->resetCache([$reservation->getReservationedEntityId()]);
  }

}
