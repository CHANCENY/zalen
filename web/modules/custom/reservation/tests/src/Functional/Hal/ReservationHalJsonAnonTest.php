<?php

namespace Drupal\Tests\reservation\Functional\Hal;

use Drupal\Tests\rest\Functional\AnonResourceTestTrait;

/**
 * @group hal
 */
class ReservationHalJsonAnonTest extends ReservationHalJsonTestBase {

  use AnonResourceTestTrait;

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
   * purpose of this test. Then they are able to edit their own reservations, but
   * some fields are still not editable, even with that permission.
   *
   * @see ::setUpAuthorization
   */
  protected static $patchProtectedFieldNames = [
    'changed' => NULL,
    'thread' => NULL,
    'entity_type' => NULL,
    'field_name' => NULL,
    'entity_id' => NULL,
  ];

}
