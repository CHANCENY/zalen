<?php

namespace Drupal\reservation;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the reservation entity type.
 *
 * @see \Drupal\reservation\Entity\Reservation
 */
class ReservationAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\reservation\ReservationInterface|\Drupal\user\EntityOwnerInterface $entity */

    $reservation_admin = $account->hasPermission('administer reservations');
    if ($operation == 'approve') {
      return AccessResult::allowedIf($reservation_admin && !$entity->isPublished())
        ->cachePerPermissions()
        ->addCacheableDependency($entity);
    }

    if ($reservation_admin) {
      $access = AccessResult::allowed()->cachePerPermissions();
      return ($operation != 'view') ? $access : $access->andIf($entity->getReservationedEntity()->access($operation, $account, TRUE));
    }

    switch ($operation) {
      case 'view':
        $access_result = AccessResult::allowedIf($account->hasPermission('access reservations') && $entity->isPublished())->cachePerPermissions()->addCacheableDependency($entity)
          ->andIf($entity->getReservationedEntity()->access($operation, $account, TRUE));
        if (!$access_result->isAllowed()) {
          $access_result->setReason("The 'access reservations' permission is required and the reservation must be published.");
        }

        return $access_result;

      case 'update':
        $access_result = AccessResult::allowedIf($account->id() && $account->id() == $entity->getOwnerId() && $entity->isPublished() && $account->hasPermission('edit own reservations'))
          ->cachePerPermissions()->cachePerUser()->addCacheableDependency($entity);
        if (!$access_result->isAllowed()) {
          $access_result->setReason("The 'edit own reservations' permission is required, the user must be the reservation author, and the reservation must be published.");
        }
        return $access_result;

      default:
        // No opinion.
        return AccessResult::neutral()->cachePerPermissions();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'post reservations');
  }

  /**
   * {@inheritdoc}
   */
  protected function checkFieldAccess($operation, FieldDefinitionInterface $field_definition, AccountInterface $account, FieldItemListInterface $items = NULL) {
    if ($operation == 'edit') {
      // Only users with the "administer reservations" permission can edit
      // administrative fields.
      $administrative_fields = [
        'uid',
        'status',
        'created',
        'date',
      ];
      if (in_array($field_definition->getName(), $administrative_fields, TRUE)) {
        return AccessResult::allowedIfHasPermission($account, 'administer reservations');
      }

      // No user can change read-only fields.
      $read_only_fields = [
        'hostname',
        'changed',
        'cid',
        'thread',
      ];
      // These fields can be edited during reservation creation.
      $create_only_fields = [
        'reservation_type',
        'uuid',
        'entity_id',
        'entity_type',
        'field_name',
        'pid',
      ];
      if ($items && ($entity = $items->getEntity()) && $entity->isNew() && in_array($field_definition->getName(), $create_only_fields, TRUE)) {
        // We are creating a new reservation, user can edit create only fields.
        return AccessResult::allowedIfHasPermission($account, 'post reservations')->addCacheableDependency($entity);
      }
      // We are editing an existing reservation - create only fields are now read
      // only.
      $read_only_fields = array_merge($read_only_fields, $create_only_fields);
      if (in_array($field_definition->getName(), $read_only_fields, TRUE)) {
        return AccessResult::forbidden();
      }

      // If the field is configured to accept anonymous contact details - admins
      // can edit name, homepage and mail. Anonymous users can also fill in the
      // fields on reservation creation.
      if (in_array($field_definition->getName(), ['name', 'mail', 'homepage'], TRUE)) {
        if (!$items) {
          // We cannot make a decision about access to edit these fields if we
          // don't have any items and therefore cannot determine the Reservation
          // entity. In this case we err on the side of caution and prevent edit
          // access.
          return AccessResult::forbidden();
        }
        $is_name = $field_definition->getName() === 'name';
        /** @var \Drupal\reservation\ReservationInterface $entity */
        $entity = $items->getEntity();
        $reservationed_entity = $entity->getReservationedEntity();
        $anonymous_contact = $reservationed_entity->get($entity->getFieldName())->getFieldDefinition()->getSetting('anonymous');
        $admin_access = AccessResult::allowedIfHasPermission($account, 'administer reservations');
        $anonymous_access = AccessResult::allowedIf($entity->isNew() && $account->isAnonymous() && ($anonymous_contact != ReservationInterface::ANONYMOUS_MAYNOT_CONTACT || $is_name) && $account->hasPermission('post reservations'))
          ->cachePerPermissions()
          ->addCacheableDependency($entity)
          ->addCacheableDependency($field_definition->getConfig($reservationed_entity->bundle()))
          ->addCacheableDependency($reservationed_entity);
        return $admin_access->orIf($anonymous_access);
      }
    }

    if ($operation == 'view') {
      // Nobody has access to the hostname.
      if ($field_definition->getName() == 'hostname') {
        return AccessResult::forbidden();
      }
      // The mail field is hidden from non-admins.
      if ($field_definition->getName() == 'mail') {
        return AccessResult::allowedIfHasPermission($account, 'administer reservations');
      }
    }
    return parent::checkFieldAccess($operation, $field_definition, $account, $items);
  }

}
