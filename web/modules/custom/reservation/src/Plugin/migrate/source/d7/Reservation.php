<?php

namespace Drupal\reservation\Plugin\migrate\source\d7;

use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\source\d7\FieldableEntity;

/**
 * Drupal 7 reservation source from database.
 *
 * @MigrateSource(
 *   id = "d7_reservation",
 *   source_module = "reservation"
 * )
 */
class Reservation extends FieldableEntity {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('reservation', 'c')->fields('c');
    $query->innerJoin('node', 'n', 'c.nid = n.nid');
    $query->addField('n', 'type', 'node_type');
    $query->orderBy('c.created');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $cid = $row->getSourceProperty('cid');

    $node_type = $row->getSourceProperty('node_type');
    $reservation_type = 'reservation_node_' . $node_type;
    $row->setSourceProperty('reservation_type', 'reservation_node_' . $node_type);

    // If this entity was translated using Entity Translation, we need to get
    // its source language to get the field values in the right language.
    // The translations will be migrated by the d7_reservation_entity_translation
    // migration.
    $entity_translatable = $this->isEntityTranslatable('reservation') && (int) $this->variableGet('language_content_type_' . $node_type, 0) === 4;
    $source_language = $this->getEntityTranslationSourceLanguage('reservation', $cid);
    $language = $entity_translatable && $source_language ? $source_language : $row->getSourceProperty('language');

    // Get Field API field values.
    foreach ($this->getFields('reservation', $reservation_type) as $field_name => $field) {
      // Ensure we're using the right language if the entity and the field are
      // translatable.
      $field_language = $entity_translatable && $field['translatable'] ? $language : NULL;
      $row->setSourceProperty($field_name, $this->getFieldValues('reservation', $field_name, $cid, NULL, $field_language));
    }

    // If the reservation subject was replaced by a real field using the Drupal 7
    // Title module, use the field value instead of the reservation subject.
    if ($this->moduleExists('title')) {
      $subject_field = $row->getSourceProperty('subject_field');
      if (isset($subject_field[0]['value'])) {
        $row->setSourceProperty('subject', $subject_field[0]['value']);
      }
    }

    return parent::prepareRow($row);
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
      'created' => $this->t('The time that the reservation was created, as a Unix timestamp.'),
      'changed' => $this->t('The time that the reservation was edited by its author, as a Unix timestamp.'),
      'status' => $this->t('The published status of a reservation. (0 = Published, 1 = Not Published)'),
      'format' => $this->t('The {filter_formats}.format of the reservation body.'),
      'thread' => $this->t("The vancode representation of the reservation's place in a thread."),
      'name' => $this->t("The reservation author's name. Uses {users}.name if the user is logged in, otherwise uses the value typed into the reservation form."),
      'mail' => $this->t("The reservation author's email address from the reservation form, if user is anonymous, and the 'Anonymous users may/must leave their contact information' setting is turned on."),
      'homepage' => $this->t("The reservation author's home page address from the reservation form, if user is anonymous, and the 'Anonymous users may/must leave their contact information' setting is turned on."),
      'language' => $this->t('The reservation language.'),
      'type' => $this->t("The {node}.type to which this reservation is a reply."),
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
