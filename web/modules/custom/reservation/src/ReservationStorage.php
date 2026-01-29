<?php

namespace Drupal\reservation;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the storage handler class for reservations.
 *
 * This extends the Drupal\Core\Entity\Sql\SqlContentEntityStorage class,
 * adding required special handling for reservation entities.
 */
class ReservationStorage extends SqlContentEntityStorage implements ReservationStorageInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs a ReservationStorage object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_info
   *   An array of entity info for the entity type.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection to be used.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   Cache backend instance to use.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Cache\MemoryCache\MemoryCacheInterface $memory_cache
   *   The memory cache.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeInterface $entity_info, Connection $database, EntityFieldManagerInterface $entity_field_manager, AccountInterface $current_user, CacheBackendInterface $cache, LanguageManagerInterface $language_manager, MemoryCacheInterface $memory_cache, EntityTypeBundleInfoInterface $entity_type_bundle_info, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($entity_info, $database, $entity_field_manager, $cache, $language_manager, $memory_cache, $entity_type_bundle_info, $entity_type_manager);
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_info) {
    return new static(
      $entity_info,
      $container->get('database'),
      $container->get('entity_field.manager'),
      $container->get('current_user'),
      $container->get('cache.entity'),
      $container->get('language_manager'),
      $container->get('entity.memory_cache'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getMaxThread(ReservationInterface $reservation) {
    $query = $this->database->select($this->getDataTable(), 'c')
      ->condition('entity_id', $reservation->getReservationedEntityId())
      ->condition('field_name', $reservation->getFieldName())
      ->condition('entity_type', $reservation->getReservationedEntityTypeId())
      ->condition('default_langcode', 1);
    $query->addExpression('MAX(thread)', 'thread');
    return $query->execute()
      ->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function getMaxThreadPerThread(ReservationInterface $reservation) {
    $query = $this->database->select($this->getDataTable(), 'c')
      ->condition('entity_id', $reservation->getReservationedEntityId())
      ->condition('field_name', $reservation->getFieldName())
      ->condition('entity_type', $reservation->getReservationedEntityTypeId())
      ->condition('thread', $reservation->getParentReservation()->getThread() . '.%', 'LIKE')
      ->condition('default_langcode', 1);
    $query->addExpression('MAX(thread)', 'thread');
    return $query->execute()
      ->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function getDisplayOrdinal(ReservationInterface $reservation, $reservation_mode, $divisor = 1) {
    // Count how many reservations (c1) are before $reservation (c2) in display order.
    // This is the 0-based display ordinal.
    $data_table = $this->getDataTable();
    $query = $this->database->select($data_table, 'c1');
    $query->innerJoin($data_table, 'c2', 'c2.entity_id = c1.entity_id AND c2.entity_type = c1.entity_type AND c2.field_name = c1.field_name');
    $query->addExpression('COUNT(*)', 'count');
    $query->condition('c2.cid', $reservation->id());
    if (!$this->currentUser->hasPermission('administer reservations')) {
      $query->condition('c1.status', ReservationInterface::PUBLISHED);
    }

    if ($reservation_mode == ReservationManagerInterface::RESERVATION_MODE_FLAT) {
      // For rendering flat reservations, cid is used for ordering reservations due to
      // unpredictable behavior with timestamp, so we make the same assumption
      // here.
      $query->condition('c1.cid', $reservation->id(), '<');
    }
    else {
      // For threaded reservations, the c.thread column is used for ordering. We can
      // use the sorting code for comparison, but must remove the trailing
      // slash.
      $query->where('SUBSTRING(c1.thread, 1, (LENGTH(c1.thread) - 1)) < SUBSTRING(c2.thread, 1, (LENGTH(c2.thread) - 1))');
    }

    $query->condition('c1.default_langcode', 1);
    $query->condition('c2.default_langcode', 1);

    $ordinal = $query->execute()->fetchField();

    return ($divisor > 1) ? floor($ordinal / $divisor) : $ordinal;
  }

  /**
   * {@inheritdoc}
   */
  public function getNewReservationPageNumber($total_reservations, $new_reservations, FieldableEntityInterface $entity, $field_name) {
    $field = $entity->getFieldDefinition($field_name);
    $reservations_per_page = $field->getSetting('per_page');
    $data_table = $this->getDataTable();

    if ($total_reservations <= $reservations_per_page) {
      // Only one page of reservations.
      $count = 0;
    }
    elseif ($field->getSetting('default_mode') == ReservationManagerInterface::RESERVATION_MODE_FLAT) {
      // Flat reservations.
      $count = $total_reservations - $new_reservations;
    }
    else {
      // Threaded reservations.

      // 1. Find all the threads with a new reservation.
      $unread_threads_query = $this->database->select($data_table, 'reservation')
        ->fields('reservation', ['thread'])
        ->condition('entity_id', $entity->id())
        ->condition('entity_type', $entity->getEntityTypeId())
        ->condition('field_name', $field_name)
        ->condition('status', ReservationInterface::PUBLISHED)
        ->condition('default_langcode', 1)
        ->orderBy('created', 'DESC')
        ->orderBy('cid', 'DESC')
        ->range(0, $new_reservations);

      // 2. Find the first thread.
      $first_thread_query = $this->database->select($unread_threads_query, 'thread');
      $first_thread_query->addExpression('SUBSTRING(thread, 1, (LENGTH(thread) - 1))', 'torder');
      $first_thread = $first_thread_query
        ->fields('thread', ['thread'])
        ->orderBy('torder')
        ->range(0, 1)
        ->execute()
        ->fetchField();

      // Remove the final '/'.
      $first_thread = substr($first_thread, 0, -1);

      // Find the number of the first reservation of the first unread thread.
      $count = $this->database->query('SELECT COUNT(*) FROM {' . $data_table . '} WHERE entity_id = :entity_id
                        AND entity_type = :entity_type
                        AND field_name = :field_name
                        AND status = :status
                        AND SUBSTRING(thread, 1, (LENGTH(thread) - 1)) < :thread
                        AND default_langcode = 1', [
        ':status' => ReservationInterface::PUBLISHED,
        ':entity_id' => $entity->id(),
        ':field_name' => $field_name,
        ':entity_type' => $entity->getEntityTypeId(),
        ':thread' => $first_thread,
      ])->fetchField();
    }

    return $reservations_per_page > 0 ? (int) ($count / $reservations_per_page) : 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getChildCids(array $reservations) {
    return $this->database->select($this->getDataTable(), 'c')
      ->fields('c', ['cid'])
      ->condition('pid', array_keys($reservations), 'IN')
      ->condition('default_langcode', 1)
      ->execute()
      ->fetchCol();
  }

  /**
   * {@inheritdoc}
   *
   * To display threaded reservations in the correct order we keep a 'thread' field
   * and order by that value. This field keeps this data in
   * a way which is easy to update and convenient to use.
   *
   * A "thread" value starts at "1". If we add a child (A) to this reservation,
   * we assign it a "thread" = "1.1". A child of (A) will have "1.1.1". Next
   * brother of (A) will get "1.2". Next brother of the parent of (A) will get
   * "2" and so on.
   *
   * First of all note that the thread field stores the depth of the reservation:
   * depth 0 will be "X", depth 1 "X.X", depth 2 "X.X.X", etc.
   *
   * Now to get the ordering right, consider this example:
   *
   * 1
   * 1.1
   * 1.1.1
   * 1.2
   * 2
   *
   * If we "ORDER BY thread ASC" we get the above result, and this is the
   * natural order sorted by time. However, if we "ORDER BY thread DESC"
   * we get:
   *
   * 2
   * 1.2
   * 1.1.1
   * 1.1
   * 1
   *
   * Clearly, this is not a natural way to see a thread, and users will get
   * confused. The natural order to show a thread by time desc would be:
   *
   * 2
   * 1
   * 1.2
   * 1.1
   * 1.1.1
   *
   * which is what we already did before the standard pager patch. To achieve
   * this we simply add a "/" at the end of each "thread" value. This way, the
   * thread fields will look like this:
   *
   * 1/
   * 1.1/
   * 1.1.1/
   * 1.2/
   * 2/
   *
   * we add "/" since this char is, in ASCII, higher than every number, so if
   * now we "ORDER BY thread DESC" we get the correct order. However this would
   * spoil the reverse ordering, "ORDER BY thread ASC" -- here, we do not need
   * to consider the trailing "/" so we use a substring only.
   */
  public function loadThread(EntityInterface $entity, $field_name, $mode, $reservations_per_page = 0, $pager_id = 0) {
    $data_table = $this->getDataTable();
    $query = $this->database->select($data_table, 'c');
    $query->addField('c', 'cid');
    $query
      ->condition('c.entity_id', $entity->id())
      ->condition('c.entity_type', $entity->getEntityTypeId())
      ->condition('c.field_name', $field_name)
      ->condition('c.default_langcode', 1)
      ->addTag('entity_access')
      ->addTag('reservation_filter')
      ->addMetaData('base_table', 'reservation')
      ->addMetaData('entity', $entity)
      ->addMetaData('field_name', $field_name);

    if ($reservations_per_page) {
      $query = $query->extend('Drupal\Core\Database\Query\PagerSelectExtender')
        ->limit($reservations_per_page);
      if ($pager_id) {
        $query->element($pager_id);
      }

      $count_query = $this->database->select($data_table, 'c');
      $count_query->addExpression('COUNT(*)');
      $count_query
        ->condition('c.entity_id', $entity->id())
        ->condition('c.entity_type', $entity->getEntityTypeId())
        ->condition('c.field_name', $field_name)
        ->condition('c.default_langcode', 1)
        ->addTag('entity_access')
        ->addTag('reservation_filter')
        ->addMetaData('base_table', 'reservation')
        ->addMetaData('entity', $entity)
        ->addMetaData('field_name', $field_name);
      $query->setCountQuery($count_query);
    }

    if (!$this->currentUser->hasPermission('administer reservations')) {
      $query->condition('c.status', ReservationInterface::PUBLISHED);
      if ($reservations_per_page) {
        $count_query->condition('c.status', ReservationInterface::PUBLISHED);
      }
    }
    if ($mode == ReservationManagerInterface::RESERVATION_MODE_FLAT) {
      $query->orderBy('c.cid', 'ASC');
    }
    else {
      // See reservation above. Analysis reveals that this doesn't cost too
      // much. It scales much much better than having the whole reservation
      // structure.
      $query->addExpression('SUBSTRING(c.thread, 1, (LENGTH(c.thread) - 1))', 'torder');
      $query->orderBy('torder', 'ASC');
    }

    $cids = $query->execute()->fetchCol();

    $reservations = [];
    if ($cids) {
      $reservations = $this->loadMultiple($cids);
    }

    return $reservations;
  }

  /**
   * {@inheritdoc}
   */
  public function getUnapprovedCount() {
    return $this->database->select($this->getDataTable(), 'c')
      ->condition('status', ReservationInterface::NOT_PUBLISHED, '=')
      ->condition('default_langcode', 1)
      ->countQuery()
      ->execute()
      ->fetchField();
  }

}
