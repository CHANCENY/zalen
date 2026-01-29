<?php

/**
 * @file
 * Provide views data for reservation.module.
 */

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Implements hook_views_data_alter().
 */
function reservation_views_data_alter(&$data) {
  // New reservations are only supported for node table because it requires the
  // history table.
  $data['node']['new_reservations'] = [
    'title' => t('New reservations'),
    'help' => t('The number of new reservations on the node.'),
    'field' => [
      'id' => 'node_new_reservations',
      'no group by' => TRUE,
    ],
  ];

  // Provides an integration for each entity type except reservation.
  foreach (\Drupal::entityTypeManager()->getDefinitions() as $entity_type_id => $entity_type) {
    if ($entity_type_id == 'reservation' || !$entity_type->entityClassImplements(ContentEntityInterface::class) || !$entity_type->getBaseTable()) {
      continue;
    }
    $fields = \Drupal::service('reservation.manager')->getFields($entity_type_id);
    $base_table = $entity_type->getDataTable() ?: $entity_type->getBaseTable();
    $args = ['@entity_type' => $entity_type_id];

    if ($fields) {
      $data[$base_table]['reservations_link'] = [
        'field' => [
          'title' => t('Add reservation link'),
          'help' => t('Display the standard add reservation link used on regular @entity_type, which will only display if the viewing user has access to add a reservation.', $args),
          'id' => 'reservation_entity_link',
        ],
      ];

      // Multilingual properties are stored in data table.
      if (!($table = $entity_type->getDataTable())) {
        $table = $entity_type->getBaseTable();
      }
      $data[$table]['uid_touch'] = [
        'title' => t('User posted or reservationed'),
        'help' => t('Display nodes only if a user posted the @entity_type or reservationed on the @entity_type.', $args),
        'argument' => [
          'field' => 'uid',
          'name table' => 'users_field_data',
          'name field' => 'name',
          'id' => 'argument_reservation_user_uid',
          'no group by' => TRUE,
          'entity_type' => $entity_type_id,
          'entity_id' => $entity_type->getKey('id'),
        ],
        'filter' => [
          'field' => 'uid',
          'name table' => 'users_field_data',
          'name field' => 'name',
          'id' => 'reservation_user_uid',
          'entity_type' => $entity_type_id,
          'entity_id' => $entity_type->getKey('id'),
        ],
      ];

      foreach ($fields as $field_name => $field) {
        $data[$base_table][$field_name . '_cid'] = [
          'title' => t('Reservations of the @entity_type using field: @field_name', $args + ['@field_name' => $field_name]),
          'help' => t('Relate all reservations on the @entity_type. This will create 1 duplicate record for every reservation. Usually if you need this it is better to create a reservation view.', $args),
          'relationship' => [
            'group' => t('Reservation'),
            'label' => t('Reservations'),
            'base' => 'reservation_field_data',
            'base field' => 'entity_id',
            'relationship field' => $entity_type->getKey('id'),
            'id' => 'standard',
            'extra' => [
              [
                'field' => 'entity_type',
                'value' => $entity_type_id,
              ],
              [
                'field' => 'field_name',
                'value' => $field_name,
              ],
            ],
          ],
        ];
      }
    }
  }
}
