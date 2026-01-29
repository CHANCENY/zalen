<?php

namespace Drupal\reservation\Plugin\migrate\source\d6;

use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;

/**
 * Drupal 6 reservation source from database.
 *
 * @MigrateSource(
 *   id = "d6_reservation",
 *   source_module = "reservation"
 * )
 */
class Reservation extends DrupalSqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('reservations', 'c')
      ->fields('c', ['cid', 'pid', 'nid', 'uid', 'subject',
      'reservation', 'hostname', 'timestamp', 'status', 'thread', 'name',
      'mail', 'homepage', 'format',
    ]);
    $query->innerJoin('node', 'n', 'c.nid = n.nid');
    $query->fields('n', ['type', 'language']);
    $query->orderBy('c.timestamp');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    // @todo Remove the call to ->prepareReservation() in
    // https://www.drupal.org/project/drupal/issues/3069260 when the Drupal 9
    // branch opens.
    return parent::prepareRow($this->prepareReservation($row));
  }

  /**
   * Provides a BC layer for deprecated sources.
   *
   * @param \Drupal\migrate\Row $row
   *   The row from the source to process.
   *
   * @return \Drupal\migrate\Row
   *   The row object.
   *
   * @throws \Exception
   *   Passing a Row with a frozen source to this method will trigger an
   *   \Exception when attempting to set the source properties.
   *
   * @todo Remove usages of this method and deprecate for removal in
   *   https://www.drupal.org/project/drupal/issues/3069260 when the Drupal 9
   *   branch opens.
   */
  protected function prepareReservation(Row $row) {
    if ($this->variableGet('reservation_subject_field_' . $row->getSourceProperty('type'), 1)) {
      // Reservation subject visible.
      $row->setSourceProperty('field_name', 'reservation');
      $row->setSourceProperty('reservation_type', 'reservation');
    }
    else {
      $row->setSourceProperty('field_name', 'reservation_no_subject');
      $row->setSourceProperty('reservation_type', 'reservation_no_subject');
    }

    // In D6, status=0 means published, while in D8 means the opposite.
    // See https://www.drupal.org/node/237636.
    $row->setSourceProperty('status', !$row->getSourceProperty('status'));

    // If node did not have a language, use site default language as a fallback.
    if (!$row->getSourceProperty('language')) {
      $language_default = $this->variableGet('language_default', NULL);
      $language = $language_default ? $language_default->language : 'en';
      $row->setSourceProperty('language', $language);
    }
    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'cid' => $this->t('Reservation ID.'),
      'pid' => $this->t('Parent reservation ID. If set to 0, this reservation is not a reply to an existing reservation.'),
      'nid' => $this->t('The {node}.nid to which this reservation is a reply.'),
      'uid' => $this->t('The {users}.uid who authored the reservation. If set to 0, this reservation was created by an anonymous user.'),
      'subject' => $this->t('The reservation title.'),
      'reservation' => $this->t('The reservation body.'),
      'hostname' => $this->t("The author's host name."),
      'timestamp' => $this->t('The time that the reservation was created, or last edited by its author, as a Unix timestamp.'),
      'status' => $this->t('The published status of a reservation. (0 = Published, 1 = Not Published)'),
      'format' => $this->t('The {filter_formats}.format of the reservation body.'),
      'thread' => $this->t("The vancode representation of the reservation's place in a thread."),
      'name' => $this->t("The reservation author's name. Uses {users}.name if the user is logged in, otherwise uses the value typed into the reservation form."),
      'mail' => $this->t("The reservation author's email address from the reservation form, if user is anonymous, and the 'Anonymous users may/must leave their contact information' setting is turned on."),
      'homepage' => $this->t("The reservation author's home page address from the reservation form, if user is anonymous, and the 'Anonymous users may/must leave their contact information' setting is turned on."),
      'type' => $this->t("The {node}.type to which this reservation is a reply."),
      'language' => $this->t("The {node}.language to which this reservation is a reply. Site default language is used as a fallback if node does not have a language."),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['cid']['type'] = 'integer';
    return $ids;
  }

}
