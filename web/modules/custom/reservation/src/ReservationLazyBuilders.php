<?php

namespace Drupal\reservation;

use Drupal\reservation\Plugin\Field\FieldType\ReservationItemInterface;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Render\Element\Link;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;

/**
 * Defines a service for reservation #lazy_builder callbacks.
 */
class ReservationLazyBuilders implements TrustedCallbackInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity form builder service.
   *
   * @var \Drupal\Core\Entity\EntityFormBuilderInterface
   */
  protected $entityFormBuilder;

  /**
   * Reservation manager service.
   *
   * @var \Drupal\reservation\ReservationManagerInterface
   */
  protected $reservationManager;

  /**
   * Current logged in user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs a new ReservationLazyBuilders object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Entity\EntityFormBuilderInterface $entity_form_builder
   *   The entity form builder service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current logged in user.
   * @param \Drupal\reservation\ReservationManagerInterface $reservation_manager
   *   The reservation manager service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityFormBuilderInterface $entity_form_builder, AccountInterface $current_user, ReservationManagerInterface $reservation_manager, ModuleHandlerInterface $module_handler, RendererInterface $renderer) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFormBuilder = $entity_form_builder;
    $this->currentUser = $current_user;
    $this->reservationManager = $reservation_manager;
    $this->moduleHandler = $module_handler;
    $this->renderer = $renderer;
  }

  /**
   * #lazy_builder callback; builds the reservation form.
   *
   * @param string $reservationed_entity_type_id
   *   The reservationed entity type ID.
   * @param string $reservationed_entity_id
   *   The reservationed entity ID.
   * @param string $field_name
   *   The reservation field name.
   * @param string $reservation_type_id
   *   The reservation type ID.
   *
   * @return array
   *   A renderable array containing the reservation form.
   */
  public function renderForm($reservationed_entity_type_id, $reservationed_entity_id, $field_name, $reservation_type_id) {
    $values = [
      'entity_type' => $reservationed_entity_type_id,
      'entity_id' => $reservationed_entity_id,
      'field_name' => $field_name,
      'reservation_type' => $reservation_type_id,
      'pid' => NULL,
    ];
    $reservation = $this->entityTypeManager->getStorage('reservation')->create($values);
    return $this->entityFormBuilder->getForm($reservation);
  }

  /**
   * #lazy_builder callback; builds a reservation's links.
   *
   * @param string $reservation_entity_id
   *   The reservation entity ID.
   * @param string $view_mode
   *   The view mode in which the reservation entity is being viewed.
   * @param string $langcode
   *   The language in which the reservation entity is being viewed.
   * @param bool $is_in_preview
   *   Whether the reservation is currently being previewed.
   *
   * @return array
   *   A renderable array representing the reservation links.
   */
  public function renderLinks($reservation_entity_id, $view_mode, $langcode, $is_in_preview) {
    $links = [
      '#theme' => 'links__reservation',
      '#pre_render' => [[Link::class, 'preRenderLinks']],
      '#attributes' => ['class' => ['links', 'inline']],
    ];

    if (!$is_in_preview) {
      /** @var \Drupal\reservation\ReservationInterface $entity */
      $entity = $this->entityTypeManager->getStorage('reservation')->load($reservation_entity_id);
      if ($reservationed_entity = $entity->getReservationedEntity()) {
        $links['reservation'] = $this->buildLinks($entity, $reservationed_entity);
      }

      // Allow other modules to alter the reservation links.
      $hook_context = [
        'view_mode' => $view_mode,
        'langcode' => $langcode,
        'reservationed_entity' => $reservationed_entity,
      ];
      $this->moduleHandler->alter('reservation_links', $links, $entity, $hook_context);
    }
    return $links;
  }

  /**
   * Build the default links (reply, edit, delete …) for a reservation.
   *
   * @param \Drupal\reservation\ReservationInterface $entity
   *   The reservation object.
   * @param \Drupal\Core\Entity\EntityInterface $reservationed_entity
   *   The entity to which the reservation is attached.
   *
   * @return array
   *   An array that can be processed by drupal_pre_render_links().
   */
  protected function buildLinks(ReservationInterface $entity, EntityInterface $reservationed_entity) {
    $links = [];
    $status = $reservationed_entity->get($entity->getFieldName())->status;

    if ($status == ReservationItemInterface::OPEN) {
      if ($entity->access('delete')) {
        $links['reservation-delete'] = [
          'title' => t('Delete'),
          'url' => $entity->toUrl('delete-form'),
        ];
      }

      if ($entity->access('update')) {
        $links['reservation-edit'] = [
          'title' => t('Edit'),
          'url' => $entity->toUrl('edit-form'),
        ];
      }
      if ($entity->access('create')) {
        $links['reservation-reply'] = [
          'title' => t('Reply'),
          'url' => Url::fromRoute('reservation.reply', [
            'entity_type' => $entity->getReservationedEntityTypeId(),
            'entity' => $entity->getReservationedEntityId(),
            'field_name' => $entity->getFieldName(),
            'pid' => $entity->id(),
          ]),
        ];
      }
      if (!$entity->isPublished() && $entity->access('approve')) {
        $links['reservation-approve'] = [
          'title' => t('Approve'),
          'url' => Url::fromRoute('reservation.approve', ['reservation' => $entity->id()]),
        ];
      }
      if (empty($links) && $this->currentUser->isAnonymous()) {
        $links['reservation-forbidden']['title'] = $this->reservationManager->forbiddenMessage($reservationed_entity, $entity->getFieldName());
      }
    }

    // Add translations link for translation-enabled reservation bundles.
    if ($this->moduleHandler->moduleExists('content_translation') && $this->access($entity)->isAllowed()) {
      $links['reservation-translations'] = [
        'title' => t('Translate'),
        'url' => $entity->toUrl('drupal:content-translation-overview'),
      ];
    }

    return [
      '#theme' => 'links__reservation__reservation',
      // The "entity" property is specified to be present, so no need to check.
      '#links' => $links,
      '#attributes' => ['class' => ['links', 'inline']],
    ];
  }

  /**
   * Wraps content_translation_translate_access.
   */
  protected function access(EntityInterface $entity) {
    return content_translation_translate_access($entity);
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['renderLinks', 'renderForm'];
  }

}
