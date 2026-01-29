<?php

namespace Drupal\reservation;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\views\EntityViewsData;

/**
 * Provides views data for the reservation entity type.
 */
class ReservationViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    $data['reservation_field_data']['table']['base']['help'] = $this->t('Reservations are responses to content.');
    $data['reservation_field_data']['table']['base']['access query tag'] = 'reservation_access';

    $data['reservation_field_data']['table']['wizard_id'] = 'reservation';

    $data['reservation_field_data']['subject']['title'] = $this->t('Title');
    $data['reservation_field_data']['subject']['help'] = $this->t('The title of the reservation.');
    $data['reservation_field_data']['subject']['field']['default_formatter'] = 'reservation_permalink';

    $data['reservation_field_data']['name']['title'] = $this->t('Author');
    $data['reservation_field_data']['name']['help'] = $this->t("The name of the reservation's author. Can be rendered as a link to the author's homepage.");
    $data['reservation_field_data']['name']['field']['default_formatter'] = 'reservation_username';

    $data['reservation_field_data']['homepage']['title'] = $this->t("Author's website");
    $data['reservation_field_data']['homepage']['help'] = $this->t("The website address of the reservation's author. Can be rendered as a link. Will be empty if the author is a registered user.");

    $data['reservation_field_data']['mail']['help'] = $this->t('Email of user that posted the reservation. Will be empty if the author is a registered user.');

    $data['reservation_field_data']['created']['title'] = $this->t('Post date');
    $data['reservation_field_data']['created']['help'] = $this->t('Date and time of when the reservation was created.');

    $data['reservation_field_data']['created_fulldata'] = [
      'title' => $this->t('Created date'),
      'help' => $this->t('Date in the form of CCYYMMDD.'),
      'argument' => [
        'field' => 'created',
        'id' => 'date_fulldate',
      ],
    ];

    $data['reservation_field_data']['created_year_month'] = [
      'title' => $this->t('Created year + month'),
      'help' => $this->t('Date in the form of YYYYMM.'),
      'argument' => [
        'field' => 'created',
        'id' => 'date_year_month',
      ],
    ];

    $data['reservation_field_data']['created_year'] = [
      'title' => $this->t('Created year'),
      'help' => $this->t('Date in the form of YYYY.'),
      'argument' => [
        'field' => 'created',
        'id' => 'date_year',
      ],
    ];

    $data['reservation_field_data']['created_month'] = [
      'title' => $this->t('Created month'),
      'help' => $this->t('Date in the form of MM (01 - 12).'),
      'argument' => [
        'field' => 'created',
        'id' => 'date_month',
      ],
    ];

    $data['reservation_field_data']['created_day'] = [
      'title' => $this->t('Created day'),
      'help' => $this->t('Date in the form of DD (01 - 31).'),
      'argument' => [
        'field' => 'created',
        'id' => 'date_day',
      ],
    ];

    $data['reservation_field_data']['created_week'] = [
      'title' => $this->t('Created week'),
      'help' => $this->t('Date in the form of WW (01 - 53).'),
      'argument' => [
        'field' => 'created',
        'id' => 'date_week',
      ],
    ];

    $data['reservation_field_data']['changed']['title'] = $this->t('Updated date');
    $data['reservation_field_data']['changed']['help'] = $this->t('Date and time of when the reservation was last updated.');

    $data['reservation_field_data']['changed_fulldata'] = [
      'title' => $this->t('Changed date'),
      'help' => $this->t('Date in the form of CCYYMMDD.'),
      'argument' => [
        'field' => 'changed',
        'id' => 'date_fulldate',
      ],
    ];

    $data['reservation_field_data']['changed_year_month'] = [
      'title' => $this->t('Changed year + month'),
      'help' => $this->t('Date in the form of YYYYMM.'),
      'argument' => [
        'field' => 'changed',
        'id' => 'date_year_month',
      ],
    ];

    $data['reservation_field_data']['changed_year'] = [
      'title' => $this->t('Changed year'),
      'help' => $this->t('Date in the form of YYYY.'),
      'argument' => [
        'field' => 'changed',
        'id' => 'date_year',
      ],
    ];

    $data['reservation_field_data']['changed_month'] = [
      'title' => $this->t('Changed month'),
      'help' => $this->t('Date in the form of MM (01 - 12).'),
      'argument' => [
        'field' => 'changed',
        'id' => 'date_month',
      ],
    ];

    $data['reservation_field_data']['changed_day'] = [
      'title' => $this->t('Changed day'),
      'help' => $this->t('Date in the form of DD (01 - 31).'),
      'argument' => [
        'field' => 'changed',
        'id' => 'date_day',
      ],
    ];

    $data['reservation_field_data']['changed_week'] = [
      'title' => $this->t('Changed week'),
      'help' => $this->t('Date in the form of WW (01 - 53).'),
      'argument' => [
        'field' => 'changed',
        'id' => 'date_week',
      ],
    ];

    $data['reservation_field_data']['status']['title'] = $this->t('Approved status');
    $data['reservation_field_data']['status']['help'] = $this->t('Whether the reservation is approved (or still in the moderation queue).');
    $data['reservation_field_data']['status']['filter']['label'] = $this->t('Approved reservation status');
    $data['reservation_field_data']['status']['filter']['type'] = 'yes-no';

    $data['reservation']['approve_reservation'] = [
      'field' => [
        'title' => $this->t('Link to approve reservation'),
        'help' => $this->t('Provide a simple link to approve the reservation.'),
        'id' => 'reservation_link_approve',
      ],
    ];

    $data['reservation']['replyto_reservation'] = [
      'field' => [
        'title' => $this->t('Link to reply-to reservation'),
        'help' => $this->t('Provide a simple link to reply to the reservation.'),
        'id' => 'reservation_link_reply',
      ],
    ];

    $data['reservation_field_data']['entity_id']['field']['id'] = 'reservationed_entity';
    unset($data['reservation_field_data']['entity_id']['relationship']);

    $data['reservation']['reservation_bulk_form'] = [
      'title' => $this->t('Reservation operations bulk form'),
      'help' => $this->t('Add a form element that lets you run operations on multiple reservations.'),
      'field' => [
        'id' => 'reservation_bulk_form',
      ],
    ];

    $data['reservation_field_data']['thread']['field'] = [
      'title' => $this->t('Depth'),
      'help' => $this->t('Display the depth of the reservation if it is threaded.'),
      'id' => 'reservation_depth',
    ];
    $data['reservation_field_data']['thread']['sort'] = [
      'title' => $this->t('Thread'),
      'help' => $this->t('Sort by the threaded order. This will keep child reservations together with their parents.'),
      'id' => 'reservation_thread',
    ];
    unset($data['reservation_field_data']['thread']['filter']);
    unset($data['reservation_field_data']['thread']['argument']);

    $entities_types = \Drupal::entityTypeManager()->getDefinitions();

    // Provide a relationship for each entity type except reservation.
    foreach ($entities_types as $type => $entity_type) {
      if ($type == 'reservation' || !$entity_type->entityClassImplements(ContentEntityInterface::class) || !$entity_type->getBaseTable()) {
        continue;
      }
      if (\Drupal::service('reservation.manager')->getFields($type)) {
        $data['reservation_field_data'][$type] = [
          'relationship' => [
            'title' => $entity_type->getLabel(),
            'help' => $this->t('The @entity_type to which the reservation is a reply to.', ['@entity_type' => $entity_type->getLabel()]),
            'base' => $entity_type->getDataTable() ?: $entity_type->getBaseTable(),
            'base field' => $entity_type->getKey('id'),
            'relationship field' => 'entity_id',
            'id' => 'standard',
            'label' => $entity_type->getLabel(),
            'extra' => [
              [
                'field' => 'entity_type',
                'value' => $type,
                'table' => 'reservation_field_data',
              ],
            ],
          ],
        ];
      }
    }

    $data['reservation_field_data']['uid']['title'] = $this->t('Author uid');
    $data['reservation_field_data']['uid']['help'] = $this->t('If you need more fields than the uid add the reservation: author relationship');
    $data['reservation_field_data']['uid']['relationship']['title'] = $this->t('Author');
    $data['reservation_field_data']['uid']['relationship']['help'] = $this->t("The User ID of the reservation's author.");
    $data['reservation_field_data']['uid']['relationship']['label'] = $this->t('author');

    $data['reservation_field_data']['pid']['title'] = $this->t('Parent CID');
    $data['reservation_field_data']['pid']['relationship']['title'] = $this->t('Parent reservation');
    $data['reservation_field_data']['pid']['relationship']['help'] = $this->t('The parent reservation');
    $data['reservation_field_data']['pid']['relationship']['label'] = $this->t('parent');

    // Define the base group of this table. Fields that don't have a group defined
    // will go into this field by default.
    $data['reservation_entity_statistics']['table']['group'] = $this->t('Reservation Statistics');

    // Provide a relationship for each entity type except reservation.
    foreach ($entities_types as $type => $entity_type) {
      if ($type == 'reservation' || !$entity_type->entityClassImplements(ContentEntityInterface::class) || !$entity_type->getBaseTable()) {
        continue;
      }
      // This relationship does not use the 'field id' column, if the entity has
      // multiple reservation-fields, then this might introduce duplicates, in which
      // case the site-builder should enable aggregation and SUM the reservation_count
      // field. We cannot create a relationship from the base table to
      // {reservation_entity_statistics} for each field as multiple joins between
      // the same two tables is not supported.
      if (\Drupal::service('reservation.manager')->getFields($type)) {
        $data['reservation_entity_statistics']['table']['join'][$entity_type->getDataTable() ?: $entity_type->getBaseTable()] = [
          'type' => 'LEFT',
          'left_field' => $entity_type->getKey('id'),
          'field' => 'entity_id',
          'extra' => [
            [
              'field' => 'entity_type',
              'value' => $type,
            ],
          ],
        ];
      }
    }

    $data['reservation_entity_statistics']['last_reservation_timestamp'] = [
      'title' => $this->t('Last reservation time'),
      'help' => $this->t('Date and time of when the last reservation was posted.'),
      'field' => [
        'id' => 'reservation_last_timestamp',
      ],
      'sort' => [
        'id' => 'date',
      ],
      'filter' => [
        'id' => 'date',
      ],
    ];

    $data['reservation_entity_statistics']['last_reservation_name'] = [
      'title' => $this->t("Last reservation author"),
      'help' => $this->t('The name of the author of the last posted reservation.'),
      'field' => [
        'id' => 'reservation_ces_last_reservation_name',
        'no group by' => TRUE,
      ],
      'sort' => [
        'id' => 'reservation_ces_last_reservation_name',
        'no group by' => TRUE,
      ],
    ];

    $data['reservation_entity_statistics']['reservation_count'] = [
      'title' => $this->t('Reservation count'),
      'help' => $this->t('The number of reservations an entity has.'),
      'field' => [
        'id' => 'numeric',
      ],
      'filter' => [
        'id' => 'numeric',
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'argument' => [
        'id' => 'standard',
      ],
    ];

    $data['reservation_entity_statistics']['last_updated'] = [
      'title' => $this->t('Updated/reservationed date'),
      'help' => $this->t('The most recent of last reservation posted or entity updated time.'),
      'field' => [
        'id' => 'reservation_ces_last_updated',
        'no group by' => TRUE,
      ],
      'sort' => [
        'id' => 'reservation_ces_last_updated',
        'no group by' => TRUE,
      ],
      'filter' => [
        'id' => 'reservation_ces_last_updated',
      ],
    ];

    $data['reservation_entity_statistics']['cid'] = [
      'title' => $this->t('Last reservation CID'),
      'help' => $this->t('Display the last reservation of an entity'),
      'relationship' => [
        'title' => $this->t('Last reservation'),
        'help' => $this->t('The last reservation of an entity.'),
        'group' => $this->t('Reservation'),
        'base' => 'reservation',
        'base field' => 'cid',
        'id' => 'standard',
        'label' => $this->t('Last Reservation'),
      ],
    ];

    $data['reservation_entity_statistics']['last_reservation_uid'] = [
      'title' => $this->t('Last reservation uid'),
      'help' => $this->t('The User ID of the author of the last reservation of an entity.'),
      'relationship' => [
        'title' => $this->t('Last reservation author'),
        'base' => 'users',
        'base field' => 'uid',
        'id' => 'standard',
        'label' => $this->t('Last reservation author'),
      ],
      'filter' => [
        'id' => 'numeric',
      ],
      'argument' => [
        'id' => 'numeric',
      ],
      'field' => [
        'id' => 'numeric',
      ],
    ];

    $data['reservation_entity_statistics']['entity_type'] = [
      'title' => $this->t('Entity type'),
      'help' => $this->t('The entity type to which the reservation is a reply to.'),
      'field' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'string',
      ],
      'argument' => [
        'id' => 'string',
      ],
      'sort' => [
        'id' => 'standard',
      ],
    ];
    $data['reservation_entity_statistics']['field_name'] = [
      'title' => $this->t('Reservation field name'),
      'help' => $this->t('The field name from which the reservation originated.'),
      'field' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'string',
      ],
      'argument' => [
        'id' => 'string',
      ],
      'sort' => [
        'id' => 'standard',
      ],
    ];

    return $data;
  }

}
