<?php

namespace Drupal\Tests\reservation\Functional\Rest;

use Drupal\Tests\rest\Functional\AnonResourceTestTrait;
use Drupal\Tests\rest\Functional\EntityResource\XmlEntityNormalizationQuirksTrait;

/**
 * @group rest
 */
class ReservationXmlAnonTest extends ReservationResourceTestBase {

  use AnonResourceTestTrait;
  use XmlEntityNormalizationQuirksTrait;

  /**
   * {@inheritdoc}
   */
  protected static $format = 'xml';

  /**
   * {@inheritdoc}
   */
  protected static $mimeType = 'text/xml; charset=UTF-8';

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
    'pid',
    'entity_id',
    'changed',
    'thread',
    'entity_type',
    'field_name',
  ];

  /**
   * {@inheritdoc}
   */
  public function testPostDxWithoutCriticalBaseFields() {
    // Deserialization of the XML format is not supported.
    $this->markTestSkipped();
  }

  /**
   * {@inheritdoc}
   */
  public function testPostSkipReservationApproval() {
    // Deserialization of the XML format is not supported.
    $this->markTestSkipped();
  }

}
