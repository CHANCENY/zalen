<?php

namespace Drupal\reservation\Entity;

use Drupal\Component\Utility\Number;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\reservation\ReservationInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\user\Entity\User;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the reservation entity class.
 *
 * @ContentEntityType(
 *   id = "reservation",
 *   label = @Translation("Reservation"),
 *   label_singular = @Translation("reservation"),
 *   label_plural = @Translation("reservations"),
 *   label_count = @PluralTranslation(
 *     singular = "@count reservation",
 *     plural = "@count reservations",
 *   ),
 *   bundle_label = @Translation("Reservation type"),
 *   handlers = {
 *     "storage" = "Drupal\reservation\ReservationStorage",
 *     "storage_schema" = "Drupal\reservation\ReservationStorageSchema",
 *     "access" = "Drupal\reservation\ReservationAccessControlHandler",
 *     "list_builder" = "Drupal\Core\Entity\EntityListBuilder",
 *     "view_builder" = "Drupal\reservation\ReservationViewBuilder",
 *     "views_data" = "Drupal\reservation\ReservationViewsData",
 *     "form" = {
 *       "default" = "Drupal\reservation\ReservationForm",
 *       "delete" = "Drupal\reservation\Form\DeleteForm"
 *     },
 *     "translation" = "Drupal\reservation\ReservationTranslationHandler"
 *   },
 *   base_table = "reservation",
 *   data_table = "reservation_field_data",
 *   uri_callback = "reservation_uri",
 *   translatable = TRUE,
 *   entity_keys = {
 *     "id" = "cid",
 *     "bundle" = "reservation_type",
 *     "label" = "subject",
 *     "langcode" = "langcode",
 *     "uuid" = "uuid",
 *     "published" = "status",
 *     "owner" = "uid",
 *   },
 *   links = {
 *     "canonical" = "/reservation/{reservation}",
 *     "delete-form" = "/reservation/{reservation}/delete",
 *     "delete-multiple-form" = "/admin/content/reservation/delete",
 *     "edit-form" = "/reservation/{reservation}/edit",
 *     "create" = "/reservation",
 *   },
 *   bundle_entity_type = "reservation_type",
 *   field_ui_base_route  = "entity.reservation_type.edit_form",
 *   constraints = {
 *     "ReservationName" = {}
 *   }
 * )
 */
class Reservation extends ContentEntityBase implements ReservationInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;
  use EntityPublishedTrait;

  /**
   * The thread for which a lock was acquired.
   *
   * @var string
   */
  protected $threadLock = '';

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    if ($this->isNew()) {
      // Add the reservation to database. This next section builds the thread field.
      // @see \Drupal\reservation\ReservationViewBuilder::buildComponents()
      $thread = $this->getThread();
      if (empty($thread)) {
        if ($this->threadLock) {
          // Thread lock was not released after being set previously.
          // This suggests there's a bug in code using this class.
          throw new \LogicException('preSave() is called again without calling postSave() or releaseThreadLock()');
        }
        if (!$this->hasParentReservation()) {
          // This is a reservation with no parent reservation (depth 0): we start
          // by retrieving the maximum thread level.
          $max = $storage->getMaxThread($this);
          // Strip the "/" from the end of the thread.
          $max = rtrim($max, '/');
          // We need to get the value at the correct depth.
          $parts = explode('.', $max);
          $n = Number::alphadecimalToInt($parts[0]);
          $prefix = '';
        }
        else {
          // This is a reservation with a parent reservation, so increase the part of
          // the thread value at the proper depth.

          // Get the parent reservation:
          $parent = $this->getParentReservation();

          // Strip the "/" from the end of the parent thread.
          $parent->setThread((string) rtrim((string) $parent->getThread(), '/'));
          $prefix = $parent->getThread() . '.';
          // Get the max value in *this* thread.
          $max = $storage->getMaxThreadPerThread($this);

          if ($max == '') {
            // First child of this parent. As the other two cases do an
            // increment of the thread number before creating the thread
            // string set this to -1 so it requires an increment too.
            $n = -1;
          }
          else {
            // Strip the "/" at the end of the thread.
            $max = rtrim($max, '/');
            // Get the value at the correct depth.
            $parts = explode('.', $max);
            $parent_depth = count(explode('.', $parent->getThread()));
            $n = Number::alphadecimalToInt($parts[$parent_depth]);
          }
        }
        // Finally, build the thread field for this new reservation. To avoid
        // race conditions, get a lock on the thread. If another process already
        // has the lock, just move to the next integer.
        do {
          $thread = $prefix . Number::intToAlphadecimal(++$n) . '/';
          $lock_name = "reservation:{$this->getReservationedEntityId()}:$thread";
        } while (!\Drupal::lock()->acquire($lock_name));
        $this->threadLock = $lock_name;
      }
      $this->setThread($thread);
    }
    // The entity fields for name and mail have no meaning if the user is not
    // Anonymous. Set them to NULL to make it clearer that they are not used.
    // For anonymous users see \Drupal\reservation\ReservationForm::form() for mail,
    // and \Drupal\reservation\ReservationForm::buildEntity() for name setting.
    if (!$this->getOwner()->isAnonymous()) {
      $this->set('name', NULL);
      $this->set('mail', NULL);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    // Always invalidate the cache tag for the reservationed entity.
    if ($reservationed_entity = $this->getReservationedEntity()) {
      Cache::invalidateTags($reservationed_entity->getCacheTagsToInvalidate());
    }

    $this->releaseThreadLock();
    // Update the {reservation_entity_statistics} table prior to executing the hook.
    \Drupal::service('reservation.statistics')->update($this);
  }

  /**
   * Release the lock acquired for the thread in preSave().
   */
  protected function releaseThreadLock() {
    if ($this->threadLock) {
      \Drupal::lock()->release($this->threadLock);
      $this->threadLock = '';
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    parent::postDelete($storage, $entities);

    $child_cids = $storage->getChildCids($entities);
    $reservation_storage = \Drupal::entityTypeManager()->getStorage('reservation');
    $reservations = $reservation_storage->loadMultiple($child_cids);
    $reservation_storage->delete($reservations);

    foreach ($entities as $id => $entity) {
      \Drupal::service('reservation.statistics')->update($entity);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function referencedEntities() {
    $referenced_entities = parent::referencedEntities();
    if ($this->getReservationedEntityId()) {
      $referenced_entities[] = $this->getReservationedEntity();
    }
    return $referenced_entities;
  }

  /**
   * {@inheritdoc}
   */
  public function permalink() {
    $uri = $this->toUrl();
    $uri->setOption('fragment', 'reservation-' . $this->id());
    return $uri;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    /** @var \Drupal\Core\Field\BaseFieldDefinition[] $fields */
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::publishedBaseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['cid']->setLabel(t('Reservation ID'))
      ->setDescription(t('The reservation ID.'));

    $fields['uuid']->setDescription(t('The reservation UUID.'));

    $fields['reservation_type']->setLabel(t('Reservation Type'))
      ->setDescription(t('The reservation type.'));

    $fields['langcode']->setDescription(t('The reservation language code.'));

    // Set the default value callback for the status field.
    $fields['status']->setDefaultValueCallback('Drupal\reservation\Entity\Reservation::getDefaultStatus');

    $fields['pid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Parent ID'))
      ->setDescription(t('The parent reservation ID if this is a reply to a reservation.'))
      ->setSetting('target_type', 'reservation');

    $fields['entity_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Entity ID'))
      ->setDescription(t('The ID of the entity of which this reservation is a reply.'))
      ->setRequired(TRUE);

    $fields['subject'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Subject'))
      ->setTranslatable(TRUE)
      ->setSetting('max_length', 64)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        // Default reservation body field has weight 20.
        'weight' => 10,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['uid']
      ->setDescription(t('The user ID of the reservation author.'));

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t("The reservation author's name."))
      ->setTranslatable(TRUE)
      ->setSetting('max_length', 60)
      ->setDefaultValue('');

    $fields['mail'] = BaseFieldDefinition::create('email')
      ->setLabel(t('Email'))
      ->setDescription(t("The reservation author's email address."))
      ->setTranslatable(TRUE);

    $fields['homepage'] = BaseFieldDefinition::create('uri')
      ->setLabel(t('Homepage'))
      ->setDescription(t("The reservation author's home page address."))
      ->setTranslatable(TRUE)
      // URIs are not length limited by RFC 2616, but we can only store 255
      // characters in our reservation DB schema.
      ->setSetting('max_length', 255);

    $fields['hostname'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Hostname'))
      ->setDescription(t("The reservation author's hostname."))
      ->setTranslatable(TRUE)
      ->setSetting('max_length', 128)
      ->setDefaultValueCallback(static::class . '::getDefaultHostname');

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the reservation was created.'))
      ->setTranslatable(TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the reservation was last edited.'))
      ->setTranslatable(TRUE);

    $fields['thread'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Thread place'))
      ->setDescription(t("The alphadecimal representation of the reservation's place in a thread, consisting of a base 36 string prefixed by an integer indicating its length."))
      ->setSetting('max_length', 255);

    $fields['entity_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Entity type'))
      ->setRequired(TRUE)
      ->setDescription(t('The entity type to which this reservation is attached.'))
      ->setSetting('is_ascii', TRUE)
      ->setSetting('max_length', EntityTypeInterface::ID_MAX_LENGTH);

    $fields['field_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Reservation field name'))
      ->setRequired(TRUE)
      ->setDescription(t('The field name through which this reservation was added.'))
      ->setSetting('is_ascii', TRUE)
      ->setSetting('max_length', FieldStorageConfig::NAME_MAX_LENGTH);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public static function bundleFieldDefinitions(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions) {
    if ($reservation_type = ReservationType::load($bundle)) {
      $fields['entity_id'] = clone $base_field_definitions['entity_id'];
      $fields['entity_id']->setSetting('target_type', $reservation_type->getTargetEntityTypeId());
      return $fields;
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function hasParentReservation() {
    return (bool) $this->get('pid')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getParentReservation() {
    return $this->get('pid')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getReservationedEntity() {
    return $this->get('entity_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getReservationedEntityId() {
    return $this->get('entity_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getReservationedEntityTypeId() {
    return $this->get('entity_type')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setFieldName($field_name) {
    $this->set('field_name', $field_name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldName() {
    return $this->get('field_name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getSubject() {
    return $this->get('subject')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setSubject($subject) {
    $this->set('subject', $subject);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthorName() {
    // If their is a valid user id and the user entity exists return the label.
    if ($this->get('uid')->target_id && $this->get('uid')->entity) {
      return $this->get('uid')->entity->label();
    }
    return $this->get('name')->value ?: \Drupal::config('user.settings')->get('anonymous');
  }

  /**
   * {@inheritdoc}
   */
  public function setAuthorName($name) {
    $this->set('name', $name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthorEmail() {
    $mail = $this->get('mail')->value;

    if ($this->get('uid')->target_id != 0) {
      $mail = $this->get('uid')->entity->getEmail();
    }

    return $mail;
  }

  /**
   * {@inheritdoc}
   */
  public function getHomepage() {
    return $this->get('homepage')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setHomepage($homepage) {
    $this->set('homepage', $homepage);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getHostname() {
    return $this->get('hostname')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setHostname($hostname) {
    $this->set('hostname', $hostname);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    if (isset($this->get('created')->value)) {
      return $this->get('created')->value;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($created) {
    $this->set('created', $created);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getThread() {
    $thread = $this->get('thread');
    if (!empty($thread->value)) {
      return $thread->value;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setThread($thread) {
    $this->set('thread', $thread);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage, array &$values) {
    if (empty($values['reservation_type']) && !empty($values['field_name']) && !empty($values['entity_type'])) {
      $fields = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions($values['entity_type']);
      $values['reservation_type'] = $fields[$values['field_name']]->getSetting('reservation_type');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    $user = $this->get('uid')->entity;
    if (!$user || $user->isAnonymous()) {
      $user = User::getAnonymousUser();
      $user->name = $this->getAuthorName();
      $user->homepage = $this->getHomepage();
    }
    return $user;
  }

  /**
   * Get the reservation type ID for this reservation.
   *
   * @return string
   *   The ID of the reservation type.
   */
  public function getTypeId() {
    return $this->bundle();
  }

  /**
   * Default value callback for 'status' base field definition.
   *
   * @see ::baseFieldDefinitions()
   *
   * @return bool
   *   TRUE if the reservation should be published, FALSE otherwise.
   */
  public static function getDefaultStatus() {
    return \Drupal::currentUser()->hasPermission('skip reservation approval') ? ReservationInterface::PUBLISHED : ReservationInterface::NOT_PUBLISHED;
  }

  /**
   * Returns the default value for entity hostname base field.
   *
   * @return string
   *   The client host name.
   */
  public static function getDefaultHostname() {
    if (\Drupal::config('reservation.settings')->get('log_ip_addresses')) {
      return \Drupal::request()->getClientIP();
    }
    return '';
  }

}
