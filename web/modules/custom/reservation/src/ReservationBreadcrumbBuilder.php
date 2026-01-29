<?php

namespace Drupal\reservation;

use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Class to define the reservation breadcrumb builder.
 */
class ReservationBreadcrumbBuilder implements BreadcrumbBuilderInterface {
  use StringTranslationTrait;

  /**
   * The reservation storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

  /**
   * Constructs the ReservationBreadcrumbBuilder.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->storage = $entity_type_manager->getStorage('reservation');
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    return $route_match->getRouteName() == 'reservation.reply' && $route_match->getParameter('entity');
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $breadcrumb = new Breadcrumb();
    $breadcrumb->addCacheContexts(['route']);
    $breadcrumb->addLink(Link::createFromRoute($this->t('Home'), '<front>'));

    $entity = $route_match->getParameter('entity');
    $breadcrumb->addLink(new Link($entity->label(), $entity->toUrl()));
    $breadcrumb->addCacheableDependency($entity);

    if (($pid = $route_match->getParameter('pid')) && ($reservation = $this->storage->load($pid))) {
      /** @var \Drupal\reservation\ReservationInterface $reservation */
      $breadcrumb->addCacheableDependency($reservation);
      // Display link to parent reservation.
      // @todo Clean-up permalink in https://www.drupal.org/node/2198041
      $breadcrumb->addLink(new Link($reservation->getSubject(), $reservation->toUrl()));
    }

    return $breadcrumb;
  }

}
