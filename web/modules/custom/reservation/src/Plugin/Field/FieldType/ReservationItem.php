<?php

namespace Drupal\reservation\Plugin\Field\FieldType;

use Drupal\reservation\ReservationInterface;
use Drupal\reservation\ReservationManagerInterface;
use Drupal\reservation\Entity\ReservationType;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Url;

/**
 * Plugin implementation of the 'reservation' field type.
 *
 * @FieldType(
 *   id = "reservation",
 *   label = @Translation("Reservations"),
 *   description = @Translation("This field manages configuration and presentation of reservations on an entity."),
 *   list_class = "\Drupal\reservation\ReservationFieldItemList",
 *   default_widget = "reservation_default",
 *   default_formatter = "reservation_default",
 *   cardinality = 1,
 * )
 */
class ReservationItem extends FieldItemBase implements ReservationItemInterface {

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    return [
      'reservation_type' => '',
    ] + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return [
      'default_mode' => ReservationManagerInterface::RESERVATION_MODE_THREADED,
      'per_page' => 50,
      'form_location' => ReservationItemInterface::FORM_BELOW,
      'anonymous' => ReservationInterface::ANONYMOUS_MAYNOT_CONTACT,
      'preview' => DRUPAL_OPTIONAL,
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['status'] = DataDefinition::create('integer')
      ->setLabel(t('Reservation status'))
      ->setRequired(TRUE);

    $properties['cid'] = DataDefinition::create('integer')
      ->setLabel(t('Last reservation ID'));

    $properties['last_reservation_timestamp'] = DataDefinition::create('integer')
      ->setLabel(t('Last reservation timestamp'))
      ->setDescription(t('The time that the last reservation was created.'));

    $properties['last_reservation_name'] = DataDefinition::create('string')
      ->setLabel(t('Last reservation name'))
      ->setDescription(t('The name of the user posting the last reservation.'));

    $properties['last_reservation_uid'] = DataDefinition::create('integer')
      ->setLabel(t('Last reservation user ID'));

    $properties['reservation_count'] = DataDefinition::create('integer')
      ->setLabel(t('Number of reservations'))
      ->setDescription(t('The number of reservations.'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'status' => [
          'description' => 'Whether reservations are allowed on this entity: 0 = no, 1 = closed (read only), 2 = open (read/write).',
          'type' => 'int',
          'default' => 0,
        ],
      ],
      'indexes' => [],
      'foreign keys' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $element = [];

    $settings = $this->getSettings();

    $anonymous_user = new AnonymousUserSession();

    $element['default_mode'] = [
      '#type' => 'checkbox',
      '#title' => t('Threading'),
      '#default_value' => $settings['default_mode'],
      '#description' => t('Show reservation replies in a threaded list.'),
    ];
    $element['per_page'] = [
      '#type' => 'number',
      '#title' => t('Reservations per page'),
      '#default_value' => $settings['per_page'],
      '#required' => TRUE,
      '#min' => 1,
      '#max' => 1000,
    ];
    $element['anonymous'] = [
      '#type' => 'select',
      '#title' => t('Anonymous reservationing'),
      '#default_value' => $settings['anonymous'],
      '#options' => [
        ReservationInterface::ANONYMOUS_MAYNOT_CONTACT => t('Anonymous posters may not enter their contact information'),
        ReservationInterface::ANONYMOUS_MAY_CONTACT => t('Anonymous posters may leave their contact information'),
        ReservationInterface::ANONYMOUS_MUST_CONTACT => t('Anonymous posters must leave their contact information'),
      ],
      '#access' => $anonymous_user->hasPermission('post reservations'),
    ];
    $element['form_location'] = [
      '#type' => 'checkbox',
      '#title' => t('Show reply form on the same page as reservations'),
      '#default_value' => $settings['form_location'],
    ];
    $element['preview'] = [
      '#type' => 'radios',
      '#title' => t('Preview reservation'),
      '#default_value' => $settings['preview'],
      '#options' => [
        DRUPAL_DISABLED => t('Disabled'),
        DRUPAL_OPTIONAL => t('Optional'),
        DRUPAL_REQUIRED => t('Required'),
      ],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function mainPropertyName() {
    return 'status';
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    // There is always a value for this field, it is one of
    // ReservationItemInterface::OPEN, ReservationItemInterface::CLOSED or
    // ReservationItemInterface::HIDDEN.
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    $element = [];

    // @todo Inject entity storage once typed-data supports container injection.
    //   See https://www.drupal.org/node/2053415 for more details.
    $reservation_types = ReservationType::loadMultiple();
    
    $options = [];
    $entity_type = $this->getEntity()->getEntityTypeId();
    //dump($entity_type);
    foreach ($reservation_types as $reservation_type) {
      if ($reservation_type->getTargetEntityTypeId() == $entity_type) {
        $options[$reservation_type->id()] = $reservation_type->label();
      }
    }
    $element['reservation_type'] = [
      '#type' => 'select',
      '#title' => t('Reservation type'),
      '#options' => $options,
      '#required' => TRUE,
      '#description' => $this->t('Select the Reservation type to use for this reservation field. Manage the reservation types from the <a href=":url">administration overview page</a>.', [':url' => Url::fromRoute('entity.reservation_type.collection')->toString()]),
      '#default_value' => $this->getSetting('reservation_type'),
      '#disabled' => $has_data,
    ];
    //dump($reservation_type);
    return $element;
 }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $statuses = [
      ReservationItemInterface::HIDDEN,
      ReservationItemInterface::CLOSED,
      ReservationItemInterface::OPEN,
    ];
    return [
      'status' => $statuses[mt_rand(0, count($statuses) - 1)],
    ];
  }

}