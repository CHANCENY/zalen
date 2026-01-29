<?php

namespace Drupal\reservation;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\language\Entity\ContentLanguageSettings;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base form handler for reservation type edit forms.
 *
 * @internal
 */
class ReservationTypeForm extends EntityForm {

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The reservation manager.
   *
   * @var \Drupal\reservation\ReservationManagerInterface
   */
  protected $reservationManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('logger.factory')->get('reservation'),
      $container->get('reservation.manager')
    );
  }

  /**
   * Constructs a ReservationTypeFormController.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\reservation\ReservationManagerInterface $reservation_manager
   *   The reservation manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, LoggerInterface $logger, ReservationManagerInterface $reservation_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
    $this->reservationManager = $reservation_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $reservation_type = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => t('Label'),
      '#maxlength' => 255,
      '#default_value' => $reservation_type->label(),
      '#required' => TRUE,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $reservation_type->id(),
      '#machine_name' => [
        'exists' => '\Drupal\reservation\Entity\ReservationType::load',
      ],
      '#maxlength' => EntityTypeInterface::BUNDLE_MAX_LENGTH,
      '#disabled' => !$reservation_type->isNew(),
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#default_value' => $reservation_type->getDescription(),
      '#description' => t('Describe this reservation type. The text will be displayed on the <em>Reservation types</em> administration overview page.'),
      '#title' => t('Description'),
    ];

    if ($reservation_type->isNew()) {
      $options = [];
      foreach ($this->entityTypeManager->getDefinitions() as $entity_type) {
        // Only expose entities that have field UI enabled, only those can
        // get reservation fields added in the UI.
        if ($entity_type->get('field_ui_base_route')) {
          $options[$entity_type->id()] = $entity_type->getLabel();
        }
      }
      $form['target_entity_type_id'] = [
        '#type' => 'select',
        '#default_value' => $reservation_type->getTargetEntityTypeId(),
        '#title' => t('Target entity type'),
        '#options' => $options,
        '#description' => t('The target entity type can not be changed after the reservation type has been created.'),
      ];
    }
    else {
      $form['target_entity_type_id_display'] = [
        '#type' => 'item',
        '#markup' => $this->entityTypeManager->getDefinition($reservation_type->getTargetEntityTypeId())->getLabel(),
        '#title' => t('Target entity type'),
      ];
    }

    if ($this->moduleHandler->moduleExists('content_translation')) {
      $form['language'] = [
        '#type' => 'details',
        '#title' => t('Language settings'),
        '#group' => 'additional_settings',
      ];

      $language_configuration = ContentLanguageSettings::loadByEntityTypeBundle('reservation', $reservation_type->id());
      $form['language']['language_configuration'] = [
        '#type' => 'language_configuration',
        '#entity_information' => [
          'entity_type' => 'reservation',
          'bundle' => $reservation_type->id(),
        ],
        '#default_value' => $language_configuration,
      ];

      $form['#submit'][] = 'language_configuration_element_submit';
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => t('Save'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $reservation_type = $this->entity;
    $status = $reservation_type->save();

    $edit_link = $this->entity->toLink($this->t('Edit'), 'edit-form')->toString();
    if ($status == SAVED_UPDATED) {
      $this->messenger()->addStatus(t('Reservation type %label has been updated.', ['%label' => $reservation_type->label()]));
      $this->logger->notice('Reservation type %label has been updated.', ['%label' => $reservation_type->label(), 'link' => $edit_link]);
    }
    else {
      $this->reservationManager->addBodyField($reservation_type->id());
      $this->messenger()->addStatus(t('Reservation type %label has been added.', ['%label' => $reservation_type->label()]));
      $this->logger->notice('Reservation type %label has been added.', ['%label' => $reservation_type->label(), 'link' => $edit_link]);
    }

    $form_state->setRedirectUrl($reservation_type->toUrl('collection'));
  }

}
