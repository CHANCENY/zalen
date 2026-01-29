<?php

namespace Drupal\reservation;

use Drupal\reservation\Plugin\Field\FieldType\ReservationItemInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityConstraintViolationListInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base handler for reservation forms.
 *
 * @internal
 */
class ReservationForm extends ContentEntityForm {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('current_user'),
      $container->get('renderer'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * Constructs a new ReservationForm.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(EntityRepositoryInterface $entity_repository, AccountInterface $current_user, RendererInterface $renderer, EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL, TimeInterface $time = NULL, EntityFieldManagerInterface $entity_field_manager = NULL) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->currentUser = $current_user;
    $this->renderer = $renderer;
    $this->entityFieldManager = $entity_field_manager ?: \Drupal::service('entity_field.manager');
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {

    /** @var \Drupal\reservation\ReservationInterface $reservation */
    $reservation = $this->entity;
    $entity = $this->entityTypeManager->getStorage($reservation->getReservationedEntityTypeId())->load($reservation->getReservationedEntityId());
    $field_name = $reservation->getFieldName();
    $field_definition = $this->entityFieldManager->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle())[$reservation->getFieldName()];
    $config = $this->config('user.settings');

    // In several places within this function, we vary $form on:
    // - The current user's permissions.
    // - Whether the current user is authenticated or anonymous.
    // - The 'user.settings' configuration.
    // - The reservation field's definition.
    $form['#cache']['contexts'][] = 'user.permissions';
    $form['#cache']['contexts'][] = 'user.roles:authenticated';
    $this->renderer->addCacheableDependency($form, $config);
    $this->renderer->addCacheableDependency($form, $field_definition->getConfig($entity->bundle()));

    // Use #reservation-form as unique jump target, regardless of entity type.
    $form['#id'] = Html::getUniqueId('reservation_form');
    $form['#theme'] = ['reservation_form__' . $entity->getEntityTypeId() . '__' . $entity->bundle() . '__' . $field_name, 'reservation_form'];

    $anonymous_contact = $field_definition->getSetting('anonymous');
    $is_admin = $reservation->id() && $this->currentUser->hasPermission('administer reservations');

    if (!$this->currentUser->isAuthenticated() && $anonymous_contact != ReservationInterface::ANONYMOUS_MAYNOT_CONTACT) {
      $form['#attached']['library'][] = 'core/drupal.form';
      $form['#attributes']['data-user-info-from-browser'] = TRUE;
    }

    // If not replying to a reservation, use our dedicated page callback for new
    // Reservations on entities.
    if (!$reservation->id() && !$reservation->hasParentReservation()) {
      $form['#action'] = Url::fromRoute('reservation.reply', ['entity_type' => $entity->getEntityTypeId(), 'entity' => $entity->id(), 'field_name' => $field_name])->toString();
    }

    $reservation_preview = $form_state->get('reservation_preview');
    if (isset($reservation_preview)) {
      $form += $reservation_preview;
    }

    $form['author'] = [];
    // Display author information in a details element for reservation moderators.
    if ($is_admin) {
      $form['author'] += [
        '#type' => 'details',
        '#title' => $this->t('Administration'),
      ];
    }

    // Prepare default values for form elements.
    $author = '';
    if ($is_admin) {
      if (!$reservation->getOwnerId()) {
        $author = $reservation->getAuthorName();
      }
      $status = $reservation->isPublished() ? ReservationInterface::PUBLISHED : ReservationInterface::NOT_PUBLISHED;
      if (empty($reservation_preview)) {
        $form['#title'] = $this->t('Edit reservation %title', [
          '%title' => $reservation->getSubject(),
        ]);
      }
    }
    else {
      $status = ($this->currentUser->hasPermission('skip reservation approval') ? ReservationInterface::PUBLISHED : ReservationInterface::NOT_PUBLISHED);
    }

    $date = '';
    if ($reservation->id()) {
      $date = !empty($reservation->date) ? $reservation->date : DrupalDateTime::createFromTimestamp($reservation->getCreatedTime());
    }

    // The uid field is only displayed when a user with the permission
    // 'administer reservations' is editing an existing reservation from an
    // authenticated user.
    $owner = $reservation->getOwner();
    $form['author']['uid'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'user',
      '#default_value' => $owner->isAnonymous() ? NULL : $owner,
      // A reservation can be made anonymous by leaving this field empty therefore
      // there is no need to list them in the autocomplete.
      '#selection_settings' => ['include_anonymous' => FALSE],
      '#title' => $this->t('Authored by'),
      '#description' => $this->t('Leave blank for %anonymous.', ['%anonymous' => $config->get('anonymous')]),
      '#access' => $is_admin,
    ];

    // The name field is displayed when an anonymous user is adding a reservation or
    // when a user with the permission 'administer reservations' is editing an
    // existing reservation from an anonymous user.
    $form['author']['name'] = [
      '#type' => 'textfield',
      '#title' => $is_admin ? $this->t('Name for @anonymous', ['@anonymous' => $config->get('anonymous')]) : $this->t('Your name'),
      '#default_value' => $author,
      '#required' => ($this->currentUser->isAnonymous() && $anonymous_contact == ReservationInterface::ANONYMOUS_MUST_CONTACT),
      '#maxlength' => 60,
      '#access' => $this->currentUser->isAnonymous() || $is_admin,
      '#size' => 30,
      '#attributes' => [
        'data-drupal-default-value' => $config->get('anonymous'),
      ],
    ];

    if ($is_admin) {
      // When editing a reservation only display the name textfield if the uid field
      // is empty.
      $form['author']['name']['#states'] = [
        'visible' => [
          ':input[name="uid"]' => ['empty' => TRUE],
        ],
      ];
    }

    // Add author email and homepage fields depending on the current user.
    $form['author']['mail'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#default_value' => $reservation->getAuthorEmail(),
      '#required' => ($this->currentUser->isAnonymous() && $anonymous_contact == ReservationInterface::ANONYMOUS_MUST_CONTACT),
      '#maxlength' => 64,
      '#size' => 30,
      '#description' => $this->t('The content of this field is kept private and will not be shown publicly.'),
      '#access' => ($reservation->getOwner()->isAnonymous() && $is_admin) || ($this->currentUser->isAnonymous() && $anonymous_contact != ReservationInterface::ANONYMOUS_MAYNOT_CONTACT),
    ];

    $form['author']['homepage'] = [
      '#type' => 'url',
      '#title' => $this->t('Homepage'),
      '#default_value' => $reservation->getHomepage(),
      '#maxlength' => 255,
      '#size' => 30,
      '#access' => $is_admin || ($this->currentUser->isAnonymous() && $anonymous_contact != ReservationInterface::ANONYMOUS_MAYNOT_CONTACT),
    ];

    // Add administrative reservation publishing options.
    $form['author']['date'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Authored on'),
      '#default_value' => $date,
      '#size' => 20,
      '#access' => $is_admin,
    ];

    $form['author']['status'] = [
      '#type' => 'radios',
      '#title' => $this->t('Status'),
      '#default_value' => $status,
      '#options' => [
        ReservationInterface::PUBLISHED => $this->t('Published'),
        ReservationInterface::NOT_PUBLISHED => $this->t('Not published'),
      ],
      '#access' => $is_admin,
    ];

    return parent::form($form, $form_state, $reservation);
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $element = parent::actions($form, $form_state);
    /* @var \Drupal\reservation\ReservationInterface $reservation */
    $reservation = $this->entity;
    $entity = $reservation->getReservationedEntity();
    $field_definition = $this->entityFieldManager->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle())[$reservation->getFieldName()];
    $preview_mode = $field_definition->getSetting('preview');
    // No delete action on the reservation form.
    unset($element['delete']);

    // Mark the submit action as the primary action, when it appears.
    $element['submit']['#button_type'] = 'primary';

    // Only show the save button if reservation previews are optional or if we are
    // already previewing the submission.
    $element['submit']['#access'] = ($reservation->id() && $this->currentUser->hasPermission('administer reservations')) || $preview_mode != DRUPAL_REQUIRED || $form_state->get('reservation_preview');
    // $element['submit']['#access'] = false;

    $element['preview'] = [
      '#type' => 'submit',
      '#value' => $this->t('PreviewMode'),
      '#access' => $preview_mode != DRUPAL_DISABLED,
      '#submit' => ['::submitForm', '::preview'],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntity(array $form, FormStateInterface $form_state) {

    /** @var \Drupal\reservation\ReservationInterface $reservation */
    $reservation = parent::buildEntity($form, $form_state);
    if (!$form_state->isValueEmpty('date') && $form_state->getValue('date') instanceof DrupalDateTime) {
      $reservation->setCreatedTime($form_state->getValue('date')->getTimestamp());
    }
    else {
      //$reservation->setCreatedTime(REQUEST_TIME);
      $request_time = \Drupal::time()->getRequestTime();
      $reservation->setCreatedTime($request_time);
    }
    // Empty author ID should revert to anonymous.
    $author_id = $form_state->getValue('uid');
    if ($reservation->id() && $this->currentUser->hasPermission('administer reservations')) {
      // Admin can leave the author ID blank to revert to anonymous.
      $author_id = $author_id ?: 0;
    }
    if (!is_null($author_id)) {
      if ($author_id === 0 && $form['author']['name']['#access']) {
        // Use the author name value when the form has access to the element and
        // the author ID is anonymous.
        $reservation->setAuthorName($form_state->getValue('name'));
      }
      else {
        // Ensure the author name is not set.
        $reservation->setAuthorName(NULL);
      }
    }
    else {
      $author_id = $this->currentUser->id();
    }
    $reservation->setOwnerId($author_id);

    // Validate the reservation's subject. If not specified, extract from reservation
    // body.
    // dd($reservation->getSubject());
    if ($reservation->getSubject() == '') {
      if ($reservation->hasField('reservation_body')) {
        $reservation_text = $reservation->reservation_body->processed;
        $reservation->setSubject(Unicode::truncate(trim(Html::decodeEntities(strip_tags($reservation_text))), 29, TRUE, TRUE));
      } else {
        $reservation->setSubject($this->t('(No subject)'));
      }
    }
    return $reservation;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditedFieldNames(FormStateInterface $form_state) {
    return array_merge(['created', 'name'], parent::getEditedFieldNames($form_state));
  }

  /**
   * {@inheritdoc}
   */
  protected function flagViolations(EntityConstraintViolationListInterface $violations, array $form, FormStateInterface $form_state) {
    // Manually flag violations of fields not handled by the form display.
    foreach ($violations->getByField('created') as $violation) {
      $form_state->setErrorByName('date', $violation->getMessage());
    }
    foreach ($violations->getByField('name') as $violation) {
      $form_state->setErrorByName('name', $violation->getMessage());
    }
    parent::flagViolations($violations, $form, $form_state);
  }

  /**
   * Form submission handler for the 'preview' action.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function preview(array &$form, FormStateInterface $form_state) {

    $reservation_preview = reservation_preview($this->entity, $form_state);
    $reservation_preview['#title'] = $this->t('Preview reservation');
    $form_state->set('reservation_preview', $reservation_preview);
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $reservation = $this->entity;
    $entity = $reservation->getReservationedEntity();
    $field_name = $reservation->getFieldName();
    $uri = $entity->toUrl();
    $logger = $this->logger('reservation');

    if ($this->currentUser->hasPermission('post reservations') && ($this->currentUser->hasPermission('administer reservations') || $entity->{$field_name}->status == ReservationItemInterface::OPEN)) {
      $reservation->save();
      $form_state->setValue('cid', $reservation->id());

      // Add a log entry.
      $logger->notice('Reservation posted: %subject.', [
          '%subject' => $reservation->getSubject(),
          'link' => Link::fromTextAndUrl(t('View'), $reservation->toUrl()->setOption('fragment', 'reservation-' . $reservation->id()))->toString(),
        ]);

      // Explain the approval queue if necessary.
      if (!$reservation->isPublished()) {
        if (!$this->currentUser->hasPermission('administer reservations')) {
          $this->messenger()->addStatus($this->t('Your reservation has been queued for review by site administrators and will be published after approval.'));
        }
      }
      else {
        $this->messenger()->addStatus($this->t('Your reservation has been posted.'));
      }
      $query = [];
      // Find the current display page for this reservation.
      $field_definition = $this->entityFieldManager->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle())[$field_name];
      $page = $this->entityTypeManager->getStorage('reservation')->getDisplayOrdinal($reservation, $field_definition->getSetting('default_mode'), $field_definition->getSetting('per_page'));
      if ($page > 0) {
        $query['page'] = $page;
      }
      // Redirect to the newly posted reservation.
      $uri->setOption('query', $query);
      $uri->setOption('fragment', 'reservation-' . $reservation->id());
    }
    else {
      $logger->warning('Reservation: unauthorized reservation submitted or reservation submitted to a closed post %subject.', ['%subject' => $reservation->getSubject()]);
      $this->messenger()->addError($this->t('Reservation: unauthorized reservation submitted or reservation submitted to a closed post %subject.', ['%subject' => $reservation->getSubject()]));
      // Redirect the user to the entity they are reservationing on.
    }
    $form_state->setRedirectUrl($uri);
  }

}
