<?php

namespace Drupal\reservation;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a reservation entity.
 */
interface ReservationInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface, EntityPublishedInterface {

  /**
   * Reservation is awaiting approval.
   */
  const NOT_PUBLISHED = 0;

  /**
   * Reservation is published.
   */
  const PUBLISHED = 1;

  /**
   * Anonymous posters cannot enter their contact information.
   */
  const ANONYMOUS_MAYNOT_CONTACT = 0;

  /**
   * Anonymous posters may leave their contact information.
   */
  const ANONYMOUS_MAY_CONTACT = 1;

  /**
   * Anonymous posters are required to leave their contact information.
   */
  const ANONYMOUS_MUST_CONTACT = 2;

  /**
   * Determines if this reservation is a reply to another reservation.
   *
   * @return bool
   *   TRUE if the reservation has a parent reservation otherwise FALSE.
   */
  public function hasParentReservation();

  /**
   * Returns the parent reservation entity if this is a reply to a reservation.
   *
   * @return \Drupal\reservation\ReservationInterface|null
   *   A reservation entity of the parent reservation or NULL if there is no parent.
   */
  public function getParentReservation();

  /**
   * Returns the entity to which the reservation is attached.
   *
   * @return \Drupal\Core\Entity\FieldableEntityInterface|null
   *   The entity on which the reservation is attached or NULL if the reservation is an
   *   orphan.
   */
  public function getReservationedEntity();

  /**
   * Returns the ID of the entity to which the reservation is attached.
   *
   * @return int
   *   The ID of the entity to which the reservation is attached.
   */
  public function getReservationedEntityId();

  /**
   * Returns the type of the entity to which the reservation is attached.
   *
   * @return string
   *   An entity type.
   */
  public function getReservationedEntityTypeId();

  /**
   * Sets the field ID for which this reservation is attached.
   *
   * @param string $field_name
   *   The field name through which the reservation was added.
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setFieldName($field_name);

  /**
   * Returns the name of the field the reservation is attached to.
   *
   * @return string
   *   The name of the field the reservation is attached to.
   */
  public function getFieldName();

  /**
   * Returns the subject of the reservation.
   *
   * @return string
   *   The subject of the reservation.
   */
  public function getSubject();

  /**
   * Sets the subject of the reservation.
   *
   * @param string $subject
   *   The subject of the reservation.
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setSubject($subject);

  /**
   * Returns the reservation author's name.
   *
   * For anonymous authors, this is the value as typed in the reservation form.
   *
   * @return string
   *   The name of the reservation author.
   */
  public function getAuthorName();

  /**
   * Sets the name of the author of the reservation.
   *
   * @param string $name
   *   A string containing the name of the author.
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setAuthorName($name);

  /**
   * Returns the reservation author's email address.
   *
   * For anonymous authors, this is the value as typed in the reservation form.
   *
   * @return string
   *   The email address of the author of the reservation.
   */
  public function getAuthorEmail();

  /**
   * Returns the reservation author's home page address.
   *
   * For anonymous authors, this is the value as typed in the reservation form.
   *
   * @return string
   *   The homepage address of the author of the reservation.
   */
  public function getHomepage();

  /**
   * Sets the reservation author's home page address.
   *
   * For anonymous authors, this is the value as typed in the reservation form.
   *
   * @param string $homepage
   *   The homepage address of the author of the reservation.
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setHomepage($homepage);

  /**
   * Returns the reservation author's hostname.
   *
   * @return string
   *   The hostname of the author of the reservation.
   */
  public function getHostname();

  /**
   * Sets the hostname of the author of the reservation.
   *
   * @param string $hostname
   *   The hostname of the author of the reservation.
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setHostname($hostname);

  /**
   * Returns the time that the reservation was created.
   *
   * @return int
   *   The timestamp of when the reservation was created.
   */
  public function getCreatedTime();

  /**
   * Sets the creation date of the reservation.
   *
   * @param int $created
   *   The timestamp of when the reservation was created.
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setCreatedTime($created);

  /**
   * Returns the alphadecimal representation of the reservation's place in a thread.
   *
   * @return string
   *   The alphadecimal representation of the reservation's place in a thread.
   */
  public function getThread();

  /**
   * Sets the alphadecimal representation of the reservation's place in a thread.
   *
   * @param string $thread
   *   The alphadecimal representation of the reservation's place in a thread.
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setThread($thread);

  /**
   * Returns the permalink URL for this reservation.
   *
   * @return \Drupal\Core\Url
   */
  public function permalink();

  /**
   * Get the reservation type id for this reservation.
   *
   * @return string
   *   The id of the reservation type.
   */
  public function getTypeId();

}
