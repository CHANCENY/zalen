<?php

/**
 * @file
 * Hooks provided by the Reservation module.
 */

use Drupal\reservation\ReservationInterface;
use Drupal\Core\Url;

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alter the links of a reservation.
 *
 * @param array &$links
 *   A renderable array representing the reservation links.
 * @param \Drupal\reservation\ReservationInterface $entity
 *   The reservation being rendered.
 * @param array &$context
 *   Various aspects of the context in which the reservation links are going to be
 *   displayed, with the following keys:
 *   - 'view_mode': the view mode in which the reservation is being viewed
 *   - 'langcode': the language in which the reservation is being viewed
 *   - 'reservationed_entity': the entity to which the reservation is attached
 *
 * @see \Drupal\reservation\ReservationViewBuilder::renderLinks()
 * @see \Drupal\reservation\ReservationViewBuilder::buildLinks()
 */
function hook_reservation_links_alter(array &$links, ReservationInterface $entity, array &$context) {
  $links['mymodule'] = [
    '#theme' => 'links__reservation__mymodule',
    '#attributes' => ['class' => ['links', 'inline']],
    '#links' => [
      'reservation-report' => [
        'title' => t('Report'),
        'url' => Url::fromRoute('reservation_test.report', ['reservation' => $entity->id()], ['query' => ['token' => \Drupal::getContainer()->get('csrf_token')->get("reservation/{$entity->id()}/report")]]),
      ],
    ],
  ];
}

/**
 * @} End of "addtogroup hooks".
 */
