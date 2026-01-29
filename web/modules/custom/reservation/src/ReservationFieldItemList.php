<?php

namespace Drupal\reservation;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines an item list class for reservation fields.
 */
class ReservationFieldItemList extends FieldItemList {

  /**
   * {@inheritdoc}
   */
  public function get($index) {
    // The Field API only applies the "field default value" to newly created
    // entities. In the specific case of the "reservation status", though, we need
    // this default value to be also applied for existing entities created
    // before the reservation field was added, which have no value stored for the
    // field.
    if ($index == 0 && empty($this->list)) {
      $field_default_value = $this->getFieldDefinition()->getDefaultValue($this->getEntity());
      return $this->appendItem($field_default_value[0]);
    }
    return parent::get($index);
  }

  /**
   * {@inheritdoc}
   */
  public function offsetExists($offset) {
    // For consistency with what happens in get(), we force offsetExists() to
    // be TRUE for delta 0.
    if ($offset === 0) {
      return TRUE;
    }
    return parent::offsetExists($offset);
  }

  /**
   * {@inheritdoc}
   */
  public function access($operation = 'view', AccountInterface $account = NULL, $return_as_object = FALSE) {
    if ($operation === 'edit') {
      // Only users with administer reservations permission can edit the reservation
      // status field.
      $result = AccessResult::allowedIfHasPermission($account ?: \Drupal::currentUser(), 'administer reservations');
      return $return_as_object ? $result : $result->isAllowed();
    }
    if ($operation === 'view') {
      // Only users with "post reservations" or "access reservations" permission can
      // view the field value. The formatter,
      // Drupal\reservation\Plugin\Field\FieldFormatter\ReservationDefaultFormatter,
      // takes care of showing the thread and form based on individual
      // permissions, so if a user only has ‘post reservations’ access, only the
      // form will be shown and not the reservations.
      $result = AccessResult::allowedIfHasPermission($account ?: \Drupal::currentUser(), 'access reservations')
        ->orIf(AccessResult::allowedIfHasPermission($account ?: \Drupal::currentUser(), 'post reservations'));
      return $return_as_object ? $result : $result->isAllowed();
    }
    return parent::access($operation, $account, $return_as_object);
  }

}
