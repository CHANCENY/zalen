<?php

namespace Drupal\reservation;

use Drupal\reservation\Plugin\Field\FieldType\ReservationItemInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;

/**
 * Defines a class for building markup for reservation links on a reservationed entity.
 *
 * Reservation links include 'log in to post new reservation', 'add new reservation' etc.
 */
class ReservationLinkBuilder implements ReservationLinkBuilderInterface {

  use StringTranslationTrait;

  /**
   * Current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Reservation manager service.
   *
   * @var \Drupal\reservation\ReservationManagerInterface
   */
  protected $reservationManager;

  /**
   * Module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new ReservationLinkBuilder object.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   Current user.
   * @param \Drupal\reservation\ReservationManagerInterface $reservation_manager
   *   Reservation manager service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Module handler service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   String translation service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(AccountInterface $current_user, ReservationManagerInterface $reservation_manager, ModuleHandlerInterface $module_handler, TranslationInterface $string_translation, EntityTypeManagerInterface $entity_type_manager) {
    $this->currentUser = $current_user;
    $this->reservationManager = $reservation_manager;
    $this->moduleHandler = $module_handler;
    $this->stringTranslation = $string_translation;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function buildReservationedEntityLinks(FieldableEntityInterface $entity, array &$context) {
    $entity_links = [];
    $view_mode = $context['view_mode'];
    if ($view_mode == 'search_index' || $view_mode == 'search_result' || $view_mode == 'print' || $view_mode == 'rss') {
      // Do not add any links if the entity is displayed for:
      // - search indexing.
      // - constructing a search result excerpt.
      // - print.
      // - rss.
      return [];
    }

    $fields = $this->reservationManager->getFields($entity->getEntityTypeId());
    foreach ($fields as $field_name => $detail) {
      // Skip fields that the entity does not have.
      if (!$entity->hasField($field_name)) {
        continue;
      }
      $links = [];
      $reservationing_status = $entity->get($field_name)->status;
      if ($reservationing_status != ReservationItemInterface::HIDDEN) {
        // Entity has reservationing status open or closed.
        $field_definition = $entity->getFieldDefinition($field_name);
        if ($view_mode == 'teaser') {
          // Teaser view: display the number of reservations that have been posted,
          // or a link to add new reservations if the user has permission, the
          // entity is open to new reservations, and there currently are none.
          if ($this->currentUser->hasPermission('access reservations')) {
            if (!empty($entity->get($field_name)->reservation_count)) {
              $links['reservation-reservations'] = [
                'title' => $this->formatPlural($entity->get($field_name)->reservation_count, '1 reservation', '@count reservations'),
                'attributes' => ['title' => $this->t('Jump to the first reservation.')],
                'fragment' => 'reservations',
                'url' => $entity->toUrl(),
              ];
              if ($this->moduleHandler->moduleExists('history')) {
                $links['reservation-new-reservations'] = [
                  'title' => '',
                  'url' => Url::fromRoute('<current>'),
                  'attributes' => [
                    'class' => 'hidden',
                    'title' => $this->t('Jump to the first new reservation.'),
                    'data-history-node-last-reservation-timestamp' => $entity->get($field_name)->last_reservation_timestamp,
                    'data-history-node-field-name' => $field_name,
                  ],
                ];
              }
            }
          }
          // Provide a link to new reservation form.
          if ($reservationing_status == ReservationItemInterface::OPEN) {
            $reservation_form_location = $field_definition->getSetting('form_location');
            if ($this->currentUser->hasPermission('post reservations')) {
              $links['reservation-add'] = [
                'title' => $this->t('Add new reservation'),
                'language' => $entity->language(),
                'attributes' => ['title' => $this->t('Share your thoughts and opinions.')],
                'fragment' => 'reservation-form',
              ];
              if ($reservation_form_location == ReservationItemInterface::FORM_SEPARATE_PAGE) {
                $links['reservation-add']['url'] = Url::fromRoute('reservation.reply', [
                  'entity_type' => $entity->getEntityTypeId(),
                  'entity' => $entity->id(),
                  'field_name' => $field_name,
                ]);
              }
              else {
                $links['reservation-add'] += ['url' => $entity->toUrl()];
              }
            }
            elseif ($this->currentUser->isAnonymous()) {
              $links['reservation-forbidden'] = [
                'title' => $this->reservationManager->forbiddenMessage($entity, $field_name),
              ];
            }
          }
        }
        else {
          // Entity in other view modes: add a "post reservation" link if the user
          // is allowed to post reservations and if this entity is allowing new
          // reservations.
          if ($reservationing_status == ReservationItemInterface::OPEN) {
            $reservation_form_location = $field_definition->getSetting('form_location');
            if ($this->currentUser->hasPermission('post reservations')) {
              // Show the "post reservation" link if the form is on another page, or
              // if there are existing reservations that the link will skip past.
              if ($reservation_form_location == ReservationItemInterface::FORM_SEPARATE_PAGE || (!empty($entity->get($field_name)->reservation_count) && $this->currentUser->hasPermission('access reservations'))) {
                $links['reservation-add'] = [
                  'title' => $this->t('Add new reservation'),
                  'attributes' => ['title' => $this->t('Share your thoughts and opinions.')],
                  'fragment' => 'reservation-form',
                ];
                if ($reservation_form_location == ReservationItemInterface::FORM_SEPARATE_PAGE) {
                  $links['reservation-add']['url'] = Url::fromRoute('reservation.reply', [
                    'entity_type' => $entity->getEntityTypeId(),
                    'entity' => $entity->id(),
                    'field_name' => $field_name,
                  ]);
                }
                else {
                  $links['reservation-add']['url'] = $entity->toUrl();
                }
              }
            }
            elseif ($this->currentUser->isAnonymous()) {
              $links['reservation-forbidden'] = [
                'title' => $this->reservationManager->forbiddenMessage($entity, $field_name),
              ];
            }
          }
        }
      }

      if (!empty($links)) {
        $entity_links['reservation__' . $field_name] = [
          '#theme' => 'links__entity__reservation__' . $field_name,
          '#links' => $links,
          '#attributes' => ['class' => ['links', 'inline']],
        ];
        if ($view_mode == 'teaser' && $this->moduleHandler->moduleExists('history') && $this->currentUser->isAuthenticated()) {
          $entity_links['reservation__' . $field_name]['#cache']['contexts'][] = 'user';
          $entity_links['reservation__' . $field_name]['#attached']['library'][] = 'reservation/drupal.node-new-reservations-link';
          // Embed the metadata for the "X new reservations" link (if any) on this
          // entity.
          $entity_links['reservation__' . $field_name]['#attached']['drupalSettings']['history']['lastReadTimestamps'][$entity->id()] = (int) history_read($entity->id());
          $new_reservations = $this->reservationManager->getCountNewReservations($entity);
          if ($new_reservations > 0) {
            $page_number = $this->entityTypeManager
              ->getStorage('reservation')
              ->getNewReservationPageNumber($entity->{$field_name}->reservation_count, $new_reservations, $entity, $field_name);
            $query = $page_number ? ['page' => $page_number] : NULL;
            $value = [
              'new_reservation_count' => (int) $new_reservations,
              'first_new_reservation_link' => $entity->toUrl('canonical', [
                'query' => $query,
                'fragment' => 'new',
              ])->toString(),
            ];
            $parents = ['reservation', 'newReservationsLinks', $entity->getEntityTypeId(), $field_name, $entity->id()];
            NestedArray::setValue($entity_links['reservation__' . $field_name]['#attached']['drupalSettings'], $parents, $value);
          }
        }
      }
    }
    return $entity_links;
  }

}
