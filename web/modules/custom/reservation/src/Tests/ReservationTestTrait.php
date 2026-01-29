<?php

namespace Drupal\reservation\Tests;

use Drupal\Component\Utility\Unicode;
use Drupal\reservation\Plugin\Field\FieldType\ReservationItemInterface;

/**
 * Provides common functionality for the Reservation test classes.
 */
trait ReservationTestTrait {

  /**
   * Adds the default reservation field to an entity.
   *
   * Attaches a reservation field named 'reservation' to the given entity type and
   * bundle. Largely replicates the default behavior in Drupal 7 and earlier.
   *
   * @param string $entity_type
   *   The entity type to attach the default reservation field to.
   * @param string $bundle
   *   The bundle to attach the default reservation field to.
   * @param string $field_name
   *   (optional) Field name to use for the reservation field. Defaults to
   *     'reservation'.
   * @param int $default_value
   *   (optional) Default value, one of ReservationItemInterface::HIDDEN,
   *   ReservationItemInterface::OPEN, ReservationItemInterface::CLOSED. Defaults to
   *   ReservationItemInterface::OPEN.
   * @param string $reservation_type_id
   *   (optional) ID of reservation type to use. Defaults to 'reservation'.
   * @param string $reservation_view_mode
   *   (optional) The reservation view mode to be used in reservation field formatter.
   *   Defaults to 'full'.
   */
  public function addDefaultReservationField($entity_type, $bundle, $field_name = 'reservation', $default_value = ReservationItemInterface::OPEN, $reservation_type_id = 'reservation', $reservation_view_mode = 'full') {
    $entity_type_manager = \Drupal::entityTypeManager();
    $entity_display_repository = \Drupal::service('entity_display.repository');
    /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager */
    $entity_field_manager = \Drupal::service('entity_field.manager');
    // Create the reservation type if needed.
    $reservation_type_storage = $entity_type_manager->getStorage('reservation_type');
    if ($reservation_type = $reservation_type_storage->load($reservation_type_id)) {
      if ($reservation_type->getTargetEntityTypeId() !== $entity_type) {
        throw new \InvalidArgumentException("The given reservation type id $reservation_type_id can only be used with the $entity_type entity type");
      }
    }
    else {
      $reservation_type_storage->create([
        'id' => $reservation_type_id,
        'label' => Unicode::ucfirst($reservation_type_id),
        'target_entity_type_id' => $entity_type,
        'description' => 'Default reservation field',
      ])->save();
    }
    // Add a body field to the reservation type.
    \Drupal::service('reservation.manager')->addBodyField($reservation_type_id);

    // Add a reservation field to the host entity type. Create the field storage if
    // needed.
    if (!array_key_exists($field_name, $entity_field_manager->getFieldStorageDefinitions($entity_type))) {
      $entity_type_manager->getStorage('field_storage_config')->create([
        'entity_type' => $entity_type,
        'field_name' => $field_name,
        'type' => 'reservation',
        'translatable' => TRUE,
        'settings' => [
          'reservation_type' => $reservation_type_id,
        ],
      ])->save();
    }
    // Create the field if needed, and configure its form and view displays.
    if (!array_key_exists($field_name, $entity_field_manager->getFieldDefinitions($entity_type, $bundle))) {
      $entity_type_manager->getStorage('field_config')->create([
        'label' => 'Reservations',
        'description' => '',
        'field_name' => $field_name,
        'entity_type' => $entity_type,
        'bundle' => $bundle,
        'required' => 1,
        'default_value' => [
          [
            'status' => $default_value,
            'cid' => 0,
            'last_reservation_name' => '',
            'last_reservation_timestamp' => 0,
            'last_reservation_uid' => 0,
          ],
        ],
      ])->save();

      // Entity form displays: assign widget settings for the default form
      // mode, and hide the field in all other form modes.
      $entity_display_repository->getFormDisplay($entity_type, $bundle)
        ->setComponent($field_name, [
          'type' => 'reservation_default',
          'weight' => 20,
        ])
        ->save();
      foreach ($entity_display_repository->getFormModes($entity_type) as $id => $form_mode) {
        $display = $entity_display_repository->getFormDisplay($entity_type, $bundle, $id);
        // Only update existing displays.
        if ($display && !$display->isNew()) {
          $display->removeComponent($field_name)->save();
        }
      }

      // Entity view displays: assign widget settings for the default view
      // mode, and hide the field in all other view modes.
      $entity_display_repository->getViewDisplay($entity_type, $bundle)
        ->setComponent($field_name, [
          'label' => 'above',
          'type' => 'reservation_default',
          'weight' => 20,
          'settings' => ['view_mode' => $reservation_view_mode],
        ])
        ->save();
      foreach ($entity_display_repository->getViewModes($entity_type) as $id => $view_mode) {
        $display = $entity_display_repository->getViewDisplay($entity_type, $bundle, $id);
        // Only update existing displays.
        if ($display && !$display->isNew()) {
          $display->removeComponent($field_name)->save();
        }
      }
    }
  }

}
