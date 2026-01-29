<?php

namespace Drupal\reservation;

use Drupal\reservation\Plugin\Field\FieldType\ReservationItemInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Drupal\user\RoleInterface;
use Drupal\user\UserInterface;

/**
 * Reservation manager contains common functions to manage reservation fields.
 */
class ReservationManager implements ReservationManagerInterface {
  use StringTranslationTrait;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Whether the \Drupal\user\RoleInterface::AUTHENTICATED_ID can post reservations.
   *
   * @var bool
   */
  protected $authenticatedCanPostReservations;

  /**
   * The user settings config object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $userConfig;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Construct the ReservationManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager service.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory, TranslationInterface $string_translation, ModuleHandlerInterface $module_handler, AccountInterface $current_user, EntityFieldManagerInterface $entity_field_manager, EntityDisplayRepositoryInterface $entity_display_repository) {
    $this->entityTypeManager = $entity_type_manager;
    $this->userConfig = $config_factory->get('user.settings');
    $this->stringTranslation = $string_translation;
    $this->moduleHandler = $module_handler;
    $this->currentUser = $current_user;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityDisplayRepository = $entity_display_repository;
  }

  /**
   * {@inheritdoc}
   */
  public function getFields($entity_type_id) {
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    if (!$entity_type->entityClassImplements(FieldableEntityInterface::class)) {
      return [];
    }

    $map = $this->entityFieldManager->getFieldMapByFieldType('reservation');
    return isset($map[$entity_type_id]) ? $map[$entity_type_id] : [];
  }

  /**
   * {@inheritdoc}
   */
  public function addBodyField($reservation_type_id) {
    if (!FieldConfig::loadByName('reservation', $reservation_type_id, 'reservation_body')) {
      // Attaches the body field by default.
      $field = $this->entityTypeManager->getStorage('field_config')->create([
        'label' => 'Reservation',
        'bundle' => $reservation_type_id,
        'required' => TRUE,
        'field_storage' => FieldStorageConfig::loadByName('reservation', 'reservation_body'),
      ]);
      $field->save();

      // Assign widget settings for the default form mode.
      $this->entityDisplayRepository->getFormDisplay('reservation', $reservation_type_id)
        ->setComponent('reservation_body', [
          'type' => 'text_textarea',
        ])
        ->save();

      // Assign display settings for the default view mode.
      $this->entityDisplayRepository->getViewDisplay('reservation', $reservation_type_id)
        ->setComponent('reservation_body', [
          'label' => 'hidden',
          'type' => 'text_default',
          'weight' => 0,
        ])
        ->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function forbiddenMessage(EntityInterface $entity, $field_name) {
    if (!isset($this->authenticatedCanPostReservations)) {
      // We only output a link if we are certain that users will get the
      // permission to post reservations by logging in.
      $this->authenticatedCanPostReservations = $this->entityTypeManager
        ->getStorage('user_role')
        ->load(RoleInterface::AUTHENTICATED_ID)
        ->hasPermission('post reservations');
    }

    if ($this->authenticatedCanPostReservations) {
      // We cannot use the redirect.destination service here because these links
      // sometimes appear on /node and taxonomy listing pages.
      if ($entity->get($field_name)->getFieldDefinition()->getSetting('form_location') == ReservationItemInterface::FORM_SEPARATE_PAGE) {
        $reservation_reply_parameters = [
          'entity_type' => $entity->getEntityTypeId(),
          'entity' => $entity->id(),
          'field_name' => $field_name,
        ];
        $destination = ['destination' => Url::fromRoute('reservation.reply', $reservation_reply_parameters, ['fragment' => 'reservation-form'])->toString()];
      }
      else {
        $destination = ['destination' => $entity->toUrl('canonical', ['fragment' => 'reservation-form'])->toString()];
      }

      if ($this->userConfig->get('register') != UserInterface::REGISTER_ADMINISTRATORS_ONLY) {
        // Users can register themselves.
        return $this->t('<a href=":login" data-drupal-link-system-path="user/login" class="use-ajax" data-dialog-type="modal" data-dialog-options="{&quot;width&quot;:&quot;300&quot;,&quot;height&quot;:&quot;auto&quot;,&quot;dialogClass&quot;:&quot;tat-popup&quot;}">Log in</a> or 
        <a href=":register" class="use-ajax" data-dialog-type="modal" data-dialog-options="{&quot;width&quot;:600}">register</a> 
        to post reservations', [
          ':login' => Url::fromRoute('user.login', [], ['query' => $destination])->toString(),
          ':register' => Url::fromRoute('user.register', [], ['query' => $destination])->toString(),
        ]);
      }
      else {
        // Only admins can add new users, no public registration.
        return $this->t('<a href=":login">Log in</a> to post reservations', [
          ':login' => Url::fromRoute('user.login', [], ['query' => $destination])->toString(),
        ]);
      }
    }
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getCountNewReservations(EntityInterface $entity, $field_name = NULL, $timestamp = 0) {
    // @todo Replace module handler with optional history service injection
    //   after https://www.drupal.org/node/2081585.
    if ($this->currentUser->isAuthenticated() && $this->moduleHandler->moduleExists('history')) {
      // Retrieve the timestamp at which the current user last viewed this entity.
      if (!$timestamp) {
        if ($entity->getEntityTypeId() == 'node') {
          $timestamp = history_read($entity->id());
        }
        else {
          $function = $entity->getEntityTypeId() . '_last_viewed';
          if (function_exists($function)) {
            $timestamp = $function($entity->id());
          }
          else {
            // Default to 30 days ago.
            // @todo Remove once https://www.drupal.org/node/1029708 lands.
            $timestamp = RESERVATION_NEW_LIMIT;
          }
        }
      }
      $timestamp = ($timestamp > HISTORY_READ_LIMIT ? $timestamp : HISTORY_READ_LIMIT);

      // Use the timestamp to retrieve the number of new reservations.
      $query = $this->entityTypeManager->getStorage('reservation')->getQuery()
        ->accessCheck(FALSE)
        ->condition('entity_type', $entity->getEntityTypeId())
        ->condition('entity_id', $entity->id())
        ->condition('created', $timestamp, '>')
        ->condition('status', ReservationInterface::PUBLISHED);
      if ($field_name) {
        // Limit to a particular field.
        $query->condition('field_name', $field_name);
      }

      return $query->count()->execute();
    }
    return FALSE;
  }

}
