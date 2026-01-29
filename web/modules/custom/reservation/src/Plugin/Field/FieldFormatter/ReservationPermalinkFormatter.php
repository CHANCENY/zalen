<?php

namespace Drupal\reservation\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\StringFormatter;

/**
 * Plugin implementation of the 'reservation_permalink' formatter.
 *
 * All the other entities use 'canonical' or 'revision' links to link the entity
 * to itself but reservations use permalink URL.
 *
 * @FieldFormatter(
 *   id = "reservation_permalink",
 *   label = @Translation("Reservation Permalink"),
 *   field_types = {
 *     "string",
 *     "uri",
 *   },
 *   quickedit = {
 *     "editor" = "plain_text"
 *   }
 * )
 */
class ReservationPermalinkFormatter extends StringFormatter {

  /**
   * {@inheritdoc}
   */
  protected function getEntityUrl(EntityInterface $reservation) {
    /* @var $reservation \Drupal\reservation\ReservationInterface */
    $reservation_permalink = $reservation->permalink();
    if ($reservation->hasField('reservaion_body') && ($body = $reservation->get('reservation_body')->value)) {
      $attributes = $reservation_permalink->getOption('attributes') ?: [];
      $attributes += ['title' => Unicode::truncate($body, 128)];
      $reservation_permalink->setOption('attributes', $attributes);
    }
    return $reservation_permalink;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return parent::isApplicable($field_definition) && $field_definition->getTargetEntityTypeId() === 'reservation' && $field_definition->getName() === 'subject';
  }

}
