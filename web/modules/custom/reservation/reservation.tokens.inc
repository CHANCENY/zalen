<?php

/**
 * @file
 * Builds placeholder replacement tokens for reservation-related data.
 */

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Render\BubbleableMetadata;

/**
 * Implements hook_token_info().
 */
function reservation_token_info() {
  $type = [
    'name' => t('Reservations'),
    'description' => t('Tokens for reservations posted on the site.'),
    'needs-data' => 'reservation',
  ];

  $tokens = [];
  // Provides an integration for each entity type except reservation.
  foreach (\Drupal::entityTypeManager()->getDefinitions() as $entity_type_id => $entity_type) {
    if ($entity_type_id == 'reservation' || !$entity_type->entityClassImplements(ContentEntityInterface::class)) {
      continue;
    }

    if (\Drupal::service('reservation.manager')->getFields($entity_type_id)) {
      // Get the correct token type.
      $token_type = ($entity_type_id == 'taxonomy_term') ? 'term' : $entity_type_id;

      // @todo Make this work per field. See https://www.drupal.org/node/2031903.
      $tokens[$token_type]['reservation-count'] = [
        'name' => t("Reservation count"),
        'description' => t("The number of reservations posted on an entity."),
      ];
      $tokens[$token_type]['reservation-count-new'] = [
        'name' => t("New reservation count"),
        'description' => t("The number of reservations posted on an entity since the reader last viewed it."),
      ];
    }
  }

  // Core reservation tokens
  $reservation['cid'] = [
    'name' => t("Reservation ID"),
    'description' => t("The unique ID of the reservation."),
  ];
  $reservation['hostname'] = [
    'name' => t("IP Address"),
    'description' => t("The IP address of the computer the reservation was posted from."),
  ];
  $reservation['mail'] = [
    'name' => t("Email address"),
    'description' => t("The email address left by the reservation author."),
  ];
  $reservation['homepage'] = [
    'name' => t("Home page"),
    'description' => t("The home page URL left by the reservation author."),
  ];
  $reservation['title'] = [
    'name' => t("Title"),
    'description' => t("The title of the reservation."),
  ];
  $reservation['body'] = [
    'name' => t("Content"),
    'description' => t("The formatted content of the reservation itself."),
  ];
  $reservation['langcode'] = [
    'name' => t('Language code'),
    'description' => t('The language code of the language the reservation is written in.'),
  ];
  $reservation['url'] = [
    'name' => t("URL"),
    'description' => t("The URL of the reservation."),
  ];
  $reservation['edit-url'] = [
    'name' => t("Edit URL"),
    'description' => t("The URL of the reservation's edit page."),
  ];
  $reservation['payment-url'] = [
    'name' => t("Payment URL"),
    'description' => t("The URL of the reservation's payment page"),
  ];
  $reservation['booking-start-date'] = [
    'name' => t("Booking start date"),
    'description' => t("The Booking start date of the reservation."),
  ];
  $reservation['booking-end-date'] = [
    'name' => t("Booking end date"),
    'description' => t("The Booking end date of the reservation."),
  ];
  $reservation['booking-confirmation-time'] = [
    'name' => t("Booking confirmation time"),
    'description' => t("The Booking confirmation time of the reservation."),
  ];

  // Chained tokens for reservations
  $reservation['created'] = [
    'name' => t("Date created"),
    'description' => t("The date the reservation was posted."),
    'type' => 'date',
  ];
  $reservation['changed'] = [
    'name' => t("Date changed"),
    'description' => t("The date the reservation was most recently updated."),
    'type' => 'date',
  ];
  $reservation['parent'] = [
    'name' => t("Parent"),
    'description' => t("The reservation's parent, if reservation threading is active."),
    'type' => 'reservation',
  ];
  $reservation['entity'] = [
    'name' => t("Entity"),
    'description' => t("The entity the reservation was posted to."),
    'type' => 'entity',
  ];
  $reservation['author'] = [
    'name' => t("Author"),
    'description' => t("The author name of the reservation."),
    'type' => 'user',
  ];

  return [
    'types' => ['reservation' => $type],
    'tokens' => [
      'reservation' => $reservation,
    ] + $tokens,
  ];
}

/**
 * Implements hook_tokens().
 */
function reservation_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
  $token_service = \Drupal::token();

  $url_options = ['absolute' => TRUE];
  if (isset($options['langcode'])) {
    $url_options['language'] = \Drupal::languageManager()->getLanguage($options['langcode']);
    $langcode = $options['langcode'];
  }
  else {
    $langcode = NULL;
  }
  $replacements = [];

  if ($type == 'reservation' && !empty($data['reservation'])) {
    /** @var \Drupal\reservation\ReservationInterface $reservation */
    $reservation = $data['reservation'];

    foreach ($tokens as $name => $original) {
      switch ($name) {
        // Simple key values on the reservation.
        case 'cid':
          $replacements[$original] = $reservation->id();
          break;

        // Poster identity information for reservations.
        case 'hostname':
          $replacements[$original] = $reservation->getHostname();
          break;

        case 'mail':
          $mail = $reservation->getAuthorEmail();
          // Add the user cacheability metadata in case the author of the reservation
          // is not the anonymous user.
          if ($reservation->getOwnerId()) {
            $bubbleable_metadata->addCacheableDependency($reservation->getOwner());
          }
          $replacements[$original] = $mail;
          break;

        case 'homepage':
          $replacements[$original] = UrlHelper::stripDangerousProtocols($reservation->getHomepage());
          break;

        case 'title':
          $replacements[$original] = $reservation->getSubject();
          break;

        case 'body':
          // "processed" returns a \Drupal\Component\Render\MarkupInterface via
          // check_markup().
          $replacements[$original] = $reservation->reservation_body->processed ?? null;
          break;

        case 'langcode':
          $replacements[$original] = $reservation->language()->getId();
          break;

        // Reservation related URLs.
        case 'url':
          $url_options['fragment'] = 'reservation-' . $reservation->id();
          $replacements[$original] = $reservation->toUrl('canonical', $url_options)->toString();
          break;

        case 'edit-url':
          $url_options['fragment'] = NULL;
          $replacements[$original] = $reservation->toUrl('edit-form', $url_options)->toString();
          break;

        case 'author':
          $name = $reservation->getAuthorName();
          // Add the user cacheability metadata in case the author of the reservation
          // is not the anonymous user.
          if ($reservation->getOwnerId()) {
            $bubbleable_metadata->addCacheableDependency($reservation->getOwner());
          }
          $replacements[$original] = $name;
          break;

        case 'parent':
          if ($reservation->hasParentReservation()) {
            $parent = $reservation->getParentReservation();
            $bubbleable_metadata->addCacheableDependency($parent);
            $replacements[$original] = $parent->getSubject();
          }
          break;

        case 'created':
          $date_format = DateFormat::load('medium');
          $bubbleable_metadata->addCacheableDependency($date_format);
          $replacements[$original] = \Drupal::service('date.formatter')->format($reservation->getCreatedTime(), 'medium', '', NULL, $langcode);
          break;

        case 'changed':
          $date_format = DateFormat::load('medium');
          $bubbleable_metadata->addCacheableDependency($date_format);
          $replacements[$original] = \Drupal::service('date.formatter')->format($reservation->getChangedTime(), 'medium', '', NULL, $langcode);
          break;

        case 'entity':
          $entity = $reservation->getReservationedEntity();
          $bubbleable_metadata->addCacheableDependency($entity);
          $title = $entity->label();
          $replacements[$original] = $title;
          break;
        case 'payment-url':
          $reservation_id = $reservation->id();
          $payment_url = \Drupal::service('url_generator')->generateFromRoute('payment_provider.payment.reservation', ['reservation_id' => $reservation_id], ['absolute' => TRUE]);
          $replacements[$original] = $payment_url;
          break;
        case 'booking-start-date':
          $booking_date = $reservation->get('field_date_booking')->getValue();
          if($booking_date && array_key_exists(0, $booking_date)) {
            $booking_date = $booking_date[0];
            $replacements[$original] = \Drupal::service('date.formatter')->format($booking_date['value'], 'medium', '', NULL, $langcode);
          }
          else {
            $replacements[$original] = null;
          }
          break;
        case 'booking-end-date':
          $booking_date = $reservation->get('field_date_booking')->getValue();
          if($booking_date && array_key_exists(0, $booking_date)) {
            $booking_date = $booking_date[0];
            $replacements[$original] = \Drupal::service('date.formatter')->format($booking_date['end_value'], 'medium', '', NULL, $langcode);
          }
          else {
            $replacements[$original] = null;
          }
          break;
        case 'booking-confirmation-time':
          $room_node = $data['node'] ?? null;
          if($room_node instanceof \Drupal\node\Entity\Node && $room_node->bundle() == 'zaal') {
            $confirmation_time = $room_node->get('field_confirmatie')->getValue();
            if($confirmation_time && array_key_exists(0, $confirmation_time)) {
              $confirmation_time = (int) $confirmation_time[0]['value'] ?? null;
              if($confirmation_time) {
                $replacements[$original] = ($confirmation_time / 3600) . ' hour(s)';
              }
              else {
                $replacements[$original] = null;
              }
            }
            else {
              $replacements[$original] = null;
            }
          }
          else {
            $replacements[$original] = null;
          }
          break;
      }
    }

    // Chained token relationships.
    if ($entity_tokens = $token_service->findwithPrefix($tokens, 'entity')) {
      $entity = $reservation->getReservationedEntity();
      $replacements += $token_service->generate($reservation->getReservationedEntityTypeId(), $entity_tokens, [$reservation->getReservationedEntityTypeId() => $entity], $options, $bubbleable_metadata);
    }

    if ($date_tokens = $token_service->findwithPrefix($tokens, 'created')) {
      $replacements += $token_service->generate('date', $date_tokens, ['date' => $reservation->getCreatedTime()], $options, $bubbleable_metadata);
    }

    if ($date_tokens = $token_service->findwithPrefix($tokens, 'changed')) {
      $replacements += $token_service->generate('date', $date_tokens, ['date' => $reservation->getChangedTime()], $options, $bubbleable_metadata);
    }

    if (($parent_tokens = $token_service->findwithPrefix($tokens, 'parent')) && $parent = $reservation->getParentReservation()) {
      $replacements += $token_service->generate('reservation', $parent_tokens, ['reservation' => $parent], $options, $bubbleable_metadata);
    }

    if (($author_tokens = $token_service->findwithPrefix($tokens, 'author')) && $account = $reservation->getOwner()) {
      $replacements += $token_service->generate('user', $author_tokens, ['user' => $account], $options, $bubbleable_metadata);
    }
  }
  // Replacement tokens for any content entities that have reservation field.
  elseif (!empty($data[$type]) && $data[$type] instanceof FieldableEntityInterface) {
    /** @var $entity \Drupal\Core\Entity\FieldableEntityInterface */
    $entity = $data[$type];

    foreach ($tokens as $name => $original) {
      switch ($name) {
        case 'reservation-count':
          $count = 0;
          $fields = array_keys(\Drupal::service('reservation.manager')->getFields($entity->getEntityTypeId()));
          $definitions = array_keys($entity->getFieldDefinitions());
          $valid_fields = array_intersect($fields, $definitions);
          foreach ($valid_fields as $field_name) {
            $count += $entity->get($field_name)->reservation_count;
          }
          $replacements[$original] = $count;
          break;

        case 'reservation-count-new':
          $replacements[$original] = \Drupal::service('reservation.manager')->getCountNewReservations($entity);
          break;
      }
    }
  }

  return $replacements;
}
