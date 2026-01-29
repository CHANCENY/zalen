<?php

namespace Drupal\reservation\Form;

use Drupal\reservation\ReservationManagerInterface;
use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Entity\FieldStorageConfig;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a confirmation form for deleting a reservation type entity.
 *
 * @internal
 */
class ReservationTypeDeleteForm extends EntityDeleteForm {

  /**
   * The reservation manager service.
   *
   * @var \Drupal\reservation\ReservationManagerInterface
   */
  protected $reservationManager;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The entity being used by this form.
   *
   * @var \Drupal\reservation\ReservationTypeInterface
   */
  protected $entity;

  /**
   * Constructs a query factory object.
   *
   * @param \Drupal\reservation\ReservationManagerInterface $reservation_manager
   *   The reservation manager service.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   */
  public function __construct(ReservationManagerInterface $reservation_manager, LoggerInterface $logger) {
    $this->reservationManager = $reservation_manager;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('reservation.manager'),
      $container->get('logger.factory')->get('reservation')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $reservations = $this->entityTypeManager->getStorage('reservation')->getQuery()
      ->accessCheck(TRUE)
      ->condition('reservation_type', $this->entity->id())
      ->execute();
    $entity_type = $this->entity->getTargetEntityTypeId();
    $caption = '';
    foreach (array_keys($this->reservationManager->getFields($entity_type)) as $field_name) {
      /** @var \Drupal\field\FieldStorageConfigInterface $field_storage */
      if (($field_storage = FieldStorageConfig::loadByName($entity_type, $field_name)) && $field_storage->getSetting('reservation_type') == $this->entity->id() && !$field_storage->isDeleted()) {
        $caption .= '<p>' . $this->t('%label is used by the %field field on your site. You can not remove this reservation type until you have removed the field.', [
          '%label' => $this->entity->label(),
          '%field' => $field_storage->label(),
        ]) . '</p>';
      }
    }

    if (!empty($reservations)) {
      $caption .= '<p>' . $this->formatPlural(count($reservations), '%label is used by 1 reservation on your site. You can not remove this reservation type until you have removed all of the %label reservations.', '%label is used by @count reservations on your site. You may not remove %label until you have removed all of the %label reservations.', ['%label' => $this->entity->label()]) . '</p>';
    }
    if ($caption) {
      $form['description'] = ['#markup' => $caption];
      return $form;
    }
    else {
      return parent::buildForm($form, $form_state);
    }
  }

}
