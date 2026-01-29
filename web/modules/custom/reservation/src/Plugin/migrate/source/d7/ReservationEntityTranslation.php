<?php

namespace Drupal\reservation\Plugin\migrate\source\d7;

use Drupal\migrate\Exception\RequirementsException;
use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\source\d7\FieldableEntity;

/**
 * Provides Drupal 7 reservation entity translation source plugin.
 *
 * @MigrateSource(
 *   id = "d7_reservation_entity_translation",
 *   source_module = "entity_translation"
 * )
 */
class ReservationEntityTranslation extends FieldableEntity {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('entity_translation', 'et')
      ->fields('et')
      ->fields('c', [
        'subject',
      ])
      ->condition('et.entity_type', 'reservation')
      ->condition('et.source', '', '<>');

    $query->innerJoin('reservation', 'c', 'c.cid = et.entity_id');
    $query->innerJoin('node', 'n', 'n.nid = c.nid');

    $query->addField('n', 'type', 'node_type');

    $query->orderBy('et.created');

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $cid = $row->getSourceProperty('entity_id');
    $language = $row->getSourceProperty('language');
    $node_type = $row->getSourceProperty('node_type');
    $reservation_type = 'reservation_node_' . $node_type;

    // Get Field API field values.
    foreach ($this->getFields('reservation', $reservation_type) as $field_name => $field) {
      // Ensure we're using the right language if the entity is translatable.
      $field_language = $field['translatable'] ? $language : NULL;
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
      'entity_type' => $this->t('The entity type this translation relates to'),
      'entity_id' => $this->t('The entity ID this translation relates to'),
      'revision_id' => $this->t('The entity revision ID this translation relates to'),
      'language' => $this->t('The target language for this translation.'),
      'source' => $this->t('The source language from which this translation was created.'),
      'uid' => $this->t('The author of this translation.'),
      'status' => $this->t('Boolean indicating whether the translation is published (visible to non-administrators).'),
      'translate' => $this->t('A boolean indicating whether this translation needs to be updated.'),
      'created' => $this->t('The Unix timestamp when the translation was created.'),
      'changed' => $this->t('The Unix timestamp when the translation was most recently saved.'),
      'subject' => $this->t('The reservation title.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'entity_id' => [
        'type' => 'integer',
        'alias' => 'et',
      ],
      'language' => [
        'type' => 'string',
        'alias' => 'et',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function checkRequirements() {
    parent::checkRequirements();

    if (!$this->moduleExists('reservation')) {
      // If we make it to here, the reservation module isn't installed.
      throw new RequirementsException('The module reservation is not enabled in the source site');
    }
    if (!$this->moduleExists('node')) {
      // Node module is also a requirement.
      throw new RequirementsException('The module node is not enabled in the source site');
    }
  }

}
