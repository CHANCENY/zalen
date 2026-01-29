<?php

namespace Drupal\reservation\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\reservation\ReservationTypeInterface;

/**
 * Defines the reservation type entity.
 *
 * @ConfigEntityType(
 *   id = "reservation_type",
 *   label = @Translation("Reservation type"),
 *   label_singular = @Translation("reservation type"),
 *   label_plural = @Translation("reservation types"),
 *   label_count = @PluralTranslation(
 *     singular = "@count reservation type",
 *     plural = "@count reservation types",
 *   ),
 *   handlers = {
 *     "form" = {
 *       "default" = "Drupal\reservation\ReservationTypeForm",
 *       "add" = "Drupal\reservation\ReservationTypeForm",
 *       "edit" = "Drupal\reservation\ReservationTypeForm",
 *       "delete" = "Drupal\reservation\Form\ReservationTypeDeleteForm"
 *     },
 *     "list_builder" = "Drupal\reservation\ReservationTypeListBuilder"
 *   },
 *   admin_permission = "administer reservation types",
 *   config_prefix = "type",
 *   bundle_of = "reservation",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   links = {
 *     "delete-form" = "/admin/structure/reservation/manage/{reservation_type}/delete",
 *     "edit-form" = "/admin/structure/reservation/manage/{reservation_type}",
 *     "add-form" = "/admin/structure/reservation/types/add",
 *     "collection" = "/admin/structure/reservation",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "target_entity_type_id",
 *     "description",
 *   }
 * )
 */
class ReservationType extends ConfigEntityBundleBase implements ReservationTypeInterface {

  /**
   * The reservation type ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The reservation type label.
   *
   * @var string
   */
  protected $label;

  /**
   * The description of the reservation type.
   *
   * @var string
   */
  protected $description;

  /**
   * The target entity type.
   *
   * @var string
   */
  protected $target_entity_type_id;

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->description;
  }

  /**
   * {@inheritdoc}
   */
  public function setDescription($description) {
    $this->description = $description;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetEntityTypeId() {
    return $this->target_entity_type_id;
  }

}
