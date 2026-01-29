<?php

namespace Drupal\reservation\Controller;

use DateTime;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\reservation\Entity\Reservation;
use Drupal\reservation\ReservationInterface;
use Drupal\reservation\ReservationManagerInterface;
use Drupal\reservation\Plugin\Field\FieldType\ReservationItemInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Controller for the reservation entity.
 *
 * @see \Drupal\reservation\Entity\Reservation.
 */
class ReservationController extends ControllerBase {

  /**
   * The HTTP kernel.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $httpKernel;

  /**
   * The reservation manager service.
   *
   * @var \Drupal\reservation\ReservationManagerInterface
   */
  protected $reservationManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity repository.
   *
   * @var Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * Constructs a ReservationController object.
   *
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $http_kernel
   *   HTTP kernel to handle requests.
   * @param \Drupal\reservation\ReservationManagerInterface $reservation_manager
   *   The reservation manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager service.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   */
  public function __construct(HttpKernelInterface $http_kernel, ReservationManagerInterface $reservation_manager, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, EntityRepositoryInterface $entity_repository) {
    $this->httpKernel = $http_kernel;
    $this->reservationManager = $reservation_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityRepository = $entity_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_kernel'),
      $container->get('reservation.manager'),
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('entity.repository')
    );
  }

  /**
   * Publishes the specified reservation.
   *
   * @param \Drupal\reservation\ReservationInterface $reservation
   *   A reservation entity.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   */
  public function reservationApprove(ReservationInterface $reservation) {
    $reservation->setPublished();
    $reservation->save();

    $this->messenger()->addStatus($this->t('Reservation approved.'));
    $permalink_uri = $reservation->permalink();
    $permalink_uri->setAbsolute();
    return new RedirectResponse($permalink_uri->toString());
  }

  /**
   * Redirects reservation links to the correct page depending on reservation settings.
   *
   * Since reservations are paged there is no way to guarantee which page a reservation
   * appears on. Reservation paging and threading settings may be changed at any
   * time. With threaded reservations, an individual reservation may move between pages
   * as reservations can be added either before or after it in the overall
   * discussion. Therefore we use a central routing function for reservation links,
   * which calculates the page number based on current reservation settings and
   * returns the full reservation view with the pager set dynamically.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request of the page.
   * @param \Drupal\reservation\ReservationInterface $reservation
   *   A reservation entity.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The reservation listing set to the page on which the reservation appears.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   */
  public function reservationPermalink(Request $request, ReservationInterface $reservation) {
    if ($entity = $reservation->getReservationedEntity()) {
      // Check access permissions for the entity.
      if (!$entity->access('view')) {
        throw new AccessDeniedHttpException();
      }
      $field_definition = $this->entityFieldManager->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle())[$reservation->getFieldName()];

      // Find the current display page for this reservation.
      $page = $this->entityTypeManager()->getStorage('reservation')->getDisplayOrdinal($reservation, $field_definition->getSetting('default_mode'), $field_definition->getSetting('per_page'));
      // @todo: Cleaner sub request handling.
      $subrequest_url = $entity->toUrl()->setOption('query', ['page' => $page])->toString(TRUE);
      $redirect_request = Request::create($subrequest_url->getGeneratedUrl(), 'GET', $request->query->all(), $request->cookies->all(), [], $request->server->all());
      // Carry over the session to the subrequest.
      if ($request->hasSession()) {
        $redirect_request->setSession($request->getSession());
      }
      $request->query->set('page', $page);
      $response = $this->httpKernel->handle($redirect_request, HttpKernelInterface::SUB_REQUEST);
      if ($response instanceof CacheableResponseInterface) {
        // @todo Once path aliases have cache tags (see
        //   https://www.drupal.org/node/2480077), add test coverage that
        //   the cache tag for a reservationed entity's path alias is added to the
        //   reservation's permalink response, because there can be blocks or
        //   other content whose renderings depend on the subrequest's URL.
        $response->addCacheableDependency($subrequest_url);
      }
      return $response;
    }
    throw new NotFoundHttpException();
  }

  /**
   * The _title_callback for the page that renders the reservation permalink.
   *
   * @param \Drupal\reservation\ReservationInterface $reservation
   *   The current reservation.
   *
   * @return string
   *   The translated reservation subject.
   */
  public function reservationPermalinkTitle(ReservationInterface $reservation) {
    return $this->entityRepository->getTranslationFromContext($reservation)->label();
  }

  /**
   * Redirects legacy node links to the new path.
   *
   * @param \Drupal\Core\Entity\EntityInterface $node
   *   The node object identified by the legacy URL.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirects user to new url.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   */
  public function redirectNode(EntityInterface $node) {

    $fields = $this->reservationManager->getFields('node');

    // Legacy nodes only had a single reservation field, so use the first reservation
    // field on the entity.
    if (!empty($fields) && ($field_names = array_keys($fields)) && ($field_name = reset($field_names))) {
      return $this->redirect('reservation.reply', [
        'entity_type' => 'node',
        'entity' => $node->id(),
        'field_name' => $field_name,
      ]);
    }
    throw new NotFoundHttpException();
  }

  /**
   * Form constructor for the reservation reply form.
   *
   * There are several cases that have to be handled, including:
   *   - replies to reservations
   *   - replies to entities
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity this reservation belongs to.
   * @param string $field_name
   *   The field_name to which the reservation belongs.
   * @param int $pid
   *   (optional) Some reservations are replies to other reservations. In those cases,
   *   $pid is the parent reservation's reservation ID. Defaults to NULL.
   *
   * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
   *   An associative array containing:
   *   - An array for rendering the entity or parent reservation.
   *     - reservation_entity: If the reservation is a reply to the entity.
   *     - reservation_parent: If the reservation is a reply to another reservation.
   *   - reservation_form: The reservation form as a renderable array.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   * @throws \Exception
   */
  public function getReplyForm(Request $request, EntityInterface $entity, $field_name, $pid = NULL) {
    $account = $this->currentUser();
    $build = [];
    // The user is not just previewing a reservation.
    if ($request->request->get('op') != $this->t('Preview')) {
      // $pid indicates that this is a reply to a reservation.
      if ($pid) {
        // Load the parent reservation.
        $reservation = $this->entityTypeManager()->getStorage('reservation')->load($pid);
        // Display the parent reservation.
        $build['reservation_parent'] = $this->entityTypeManager()->getViewBuilder('reservation')->view($reservation);
      }

      // The reservation is in response to an entity.
      elseif ($entity->access('view', $account)) {
        // We make sure the field value isn't set so we don't end up with a
        // redirect loop.
        $entity = clone $entity;
        $entity->{$field_name}->status = ReservationItemInterface::HIDDEN;
        // Render array of the entity full view mode.
        $build['reservationed_entity'] = $this->entityTypeManager()->getViewBuilder($entity->getEntityTypeId())->view($entity, 'full');
        unset($build['reservationed_entity']['#cache']);
      }
    }
    else {
      $build['#title'] = $this->t('Preview reservation');
    }

//    $inputBag = $request->getContent();
//    $decodedString = urldecode($inputBag);
//    parse_str($decodedString, $dataArray);
//    if (trim($request->request->get('op')) === 'Save') {
//      $reservation = $this->createReservation($dataArray, $pid, $field_name, $entity);
//    }else{
      // Show the actual reply box.
      $reservation = $this->entityTypeManager()->getStorage('reservation')->create([
        'entity_id' => $entity->id(),
        'pid' => $pid,
        'entity_type' => $entity->getEntityTypeId(),
        'field_name' => $field_name,
      ]);
//    }
    $build['reservation_form'] = $this->entityFormBuilder()->getForm($reservation);

    return $build;
  }

  /**
   * Access check for the reply form.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity this reservation belongs to.
   * @param string $field_name
   *   The field_name to which the reservation belongs.
   * @param int $pid
   *   (optional) Some reservations are replies to other reservations. In those cases,
   *   $pid is the parent reservation's reservation ID. Defaults to NULL.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   An access result
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   */
  public function replyFormAccess(EntityInterface $entity, $field_name, $pid = NULL) {
    // Check if entity and field exists.

    $fields = $this->reservationManager->getFields($entity->getEntityTypeId());
    if (empty($fields[$field_name])) {
      throw new NotFoundHttpException();
    }

    $account = $this->currentUser();

    // Check if the user has the proper permissions.
    $access = AccessResult::allowedIfHasPermission($account, 'post reservations');

    // If reservationing is open on the entity.
    $status = $entity->{$field_name}->status;
    $access = $access->andIf(AccessResult::allowedIf($status == ReservationItemInterface::OPEN)
      ->addCacheableDependency($entity))
      // And if user has access to the host entity.
      ->andIf(AccessResult::allowedIf($entity->access('view')));

    // $pid indicates that this is a reply to a reservation.
    if ($pid) {
      // Check if the user has the proper permissions.
      $access = $access->andIf(AccessResult::allowedIfHasPermission($account, 'access reservations'));

      // Load the parent reservation.
      $reservation = $this->entityTypeManager()->getStorage('reservation')->load($pid);
      // Check if the parent reservation is published and belongs to the entity.
      $access = $access->andIf(AccessResult::allowedIf($reservation && $reservation->isPublished() && $reservation->getReservationedEntityId() == $entity->id()));
      if ($reservation) {
        $access->addCacheableDependency($reservation);
      }
    }

    return $access;
  }

  /**
   * Returns a set of nodes' last read timestamps.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request of the page.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   */
  public function renderNewReservationsNodeLinks(Request $request) {

    if ($this->currentUser()->isAnonymous()) {
      throw new AccessDeniedHttpException();
    }

    $nids = $request->request->get('node_ids');
    $field_name = $request->request->get('field_name');
    if (!isset($nids)) {
      throw new NotFoundHttpException();
    }
    // Only handle up to 100 nodes.
    $nids = array_slice($nids, 0, 100);

    $links = [];
    foreach ($nids as $nid) {
      $node = $this->entityTypeManager()->getStorage('node')->load($nid);
      $new = $this->reservationManager->getCountNewReservations($node);
      $page_number = $this->entityTypeManager()->getStorage('reservation')
        ->getNewReservationPageNumber($node->{$field_name}->reservation_count, $new, $node, $field_name);
      $query = $page_number ? ['page' => $page_number] : NULL;
      $links[$nid] = [
        'new_reservation_count' => (int) $new,
        'first_new_reservation_link' => Url::fromRoute('entity.node.canonical', ['node' => $node->id()], ['query' => $query, 'fragment' => 'new'])->toString(),
      ];
    }

    return new JsonResponse($links);
  }

  /**
   * @throws EntityStorageException
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   */
  private function createReservation($dataArray, $pid, $field_name, $entity): EntityInterface
  {
      $startTime = $dataArray['field_date_booking'][0]['value']['date'] . ' ' . $dataArray['field_date_booking'][0]['value']['time'];
      $endTime = $dataArray['field_date_booking'][0]['end_value']['date'] . ' ' . $dataArray['field_date_booking'][0]['value']['time'];
      $startDateTime = new DateTime($startTime);
      $endDateTime = new DateTime($endTime);
      $startTimestamp = $startDateTime->getTimestamp();
      $endTimestamp = $endDateTime->getTimestamp();
      $reservation = $this->entityTypeManager()->getStorage('reservation')->create([
      'subject' => strip_tags($dataArray['reservation_body'][0]['value']) ?? NULL,
      'field_evenement' => $dataArray['field_evenement'] ?? NULL,
      'field_bezetting' => $dataArray['field_bezetting'][0]['value'] ?? NULL,
      'reservation_body' => $dataArray['reservation_body'][0]['value'] ?? NULL,
      'field_date_booking' => ['value' => $startTimestamp, 'end_value' => $endTimestamp, 'duration' =>$dataArray['field_date_booking'][0]['duration'], 'timezone'=> $dataArray['field_date_booking'][0]['timezone']] ?? NULL,
      'entity_id' => $entity->id(),
      'pid' => $pid,
      'entity_type' => $entity->getEntityTypeId(),
      'field_name' => $field_name,
    ]);
      $reservation->save();
    return $reservation;
  }

}
