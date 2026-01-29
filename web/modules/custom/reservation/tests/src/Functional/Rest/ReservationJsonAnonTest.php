<?php

namespace Drupal\Tests\reservation\Functional\Rest;

use Drupal\Tests\rest\Functional\AnonResourceTestTrait;

/**
 * @group rest
 */
class ReservationJsonAnonTest extends ReservationResourceTestBase {

  use AnonResourceTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $format = 'json';

  /**
   * {@inheritdoc}
   */
  protected static $mimeType = 'application/json';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   *
   * Anonymous users cannot edit their own reservations.
   *
   * @see \Drupal\reservation\ReservationAccessControlHandler::checkAccess
   *
   * Therefore we grant them the 'administer reservations' permission for the
   * purpose of this test.
   *
   * @see ::setUpAuthorization
   */
  protected static $patchProtectedFieldNames = [
    'pid' => NULL,
    'entity_id' => NULL,
    'changed' => NULL,
    'thread' => NULL,
    'entity_type' => NULL,
    'field_name' => NULL,
  ];

}
