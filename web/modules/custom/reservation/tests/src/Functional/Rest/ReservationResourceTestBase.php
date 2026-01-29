<?php

namespace Drupal\Tests\reservation\Functional\Rest;

use Drupal\reservation\Entity\Reservation;
use Drupal\reservation\Entity\ReservationType;
use Drupal\reservation\Tests\ReservationTestTrait;
use Drupal\Core\Cache\Cache;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\Tests\rest\Functional\EntityResource\EntityResourceTestBase;
use Drupal\user\Entity\User;
use GuzzleHttp\RequestOptions;

abstract class ReservationResourceTestBase extends EntityResourceTestBase {

  use ReservationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['reservation', 'entity_test'];

  /**
   * {@inheritdoc}
   */
  protected static $entityTypeId = 'reservation';

  /**
   * {@inheritdoc}
   */
  protected static $patchProtectedFieldNames = [
    'status' => "The 'administer reservations' permission is required.",
    'uid' => "The 'administer reservations' permission is required.",
    'pid' => NULL,
    'entity_id' => NULL,
    'name' => "The 'administer reservations' permission is required.",
    'homepage' => "The 'administer reservations' permission is required.",
    'created' => "The 'administer reservations' permission is required.",
    'changed' => NULL,
    'thread' => NULL,
    'entity_type' => NULL,
    'field_name' => NULL,
  ];

  /**
   * @var \Drupal\reservation\ReservationInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  protected function setUpAuthorization($method) {
    switch ($method) {
      case 'GET':
        $this->grantPermissionsToTestedRole(['access reservations', 'view test entity']);
        break;

      case 'POST':
        $this->grantPermissionsToTestedRole(['post reservations']);
        break;

      case 'PATCH':
        // Anonymous users are not ever allowed to edit their own reservations. To
        // be able to test PATCHing reservations as the anonymous user, the more
        // permissive 'administer reservations' permission must be granted.
        // @see \Drupal\reservation\ReservationAccessControlHandler::checkAccess
        if (static::$auth) {
          $this->grantPermissionsToTestedRole(['edit own reservations']);
        }
        else {
          $this->grantPermissionsToTestedRole(['administer reservations']);
        }
        break;

      case 'DELETE':
        $this->grantPermissionsToTestedRole(['administer reservations']);
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function createEntity() {
    // Create a "bar" bundle for the "entity_test" entity type and create.
    $bundle = 'bar';
    entity_test_create_bundle($bundle, NULL, 'entity_test');

    // Create a reservation field on this bundle.
    $this->addDefaultReservationField('entity_test', 'bar', 'reservation');

    // Create a "Camelids" test entity that the reservation will be assigned to.
    $reservationed_entity = EntityTest::create([
      'name' => 'Camelids',
      'type' => 'bar',
    ]);
    $reservationed_entity->save();

    // Create a "Llama" reservation.
    $reservation = Reservation::create([
      'reservation_body' => [
        'value' => 'The name "llama" was adopted by European settlers from native Peruvians.',
        'format' => 'plain_text',
      ],
      'entity_id' => $reservationed_entity->id(),
      'entity_type' => 'entity_test',
      'field_name' => 'reservation',
    ]);
    $reservation->setSubject('Llama')
      ->setOwnerId(static::$auth ? $this->account->id() : 0)
      ->setPublished()
      ->setCreatedTime(123456789)
      ->setChangedTime(123456789);
    $reservation->save();

    return $reservation;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedNormalizedEntity() {
    $author = User::load($this->entity->getOwnerId());
    return [
      'cid' => [
        ['value' => 1],
      ],
      'uuid' => [
        ['value' => $this->entity->uuid()],
      ],
      'langcode' => [
        [
          'value' => 'en',
        ],
      ],
      'reservation_type' => [
        [
          'target_id' => 'reservation',
          'target_type' => 'reservation_type',
          'target_uuid' => ReservationType::load('reservation')->uuid(),
        ],
      ],
      'subject' => [
        [
          'value' => 'Llama',
        ],
      ],
      'status' => [
        [
          'value' => TRUE,
        ],
      ],
      'created' => [
        [
          'value' => (new \DateTime())->setTimestamp(123456789)->setTimezone(new \DateTimeZone('UTC'))->format(\DateTime::RFC3339),
          'format' => \DateTime::RFC3339,
        ],
      ],
      'changed' => [
        [
          'value' => (new \DateTime())->setTimestamp((int) $this->entity->getChangedTime())->setTimezone(new \DateTimeZone('UTC'))->format(\DateTime::RFC3339),
          'format' => \DateTime::RFC3339,
        ],
      ],
      'default_langcode' => [
        [
          'value' => TRUE,
        ],
      ],
      'uid' => [
        [
          'target_id' => (int) $author->id(),
          'target_type' => 'user',
          'target_uuid' => $author->uuid(),
          'url' => base_path() . 'user/' . $author->id(),
        ],
      ],
      'pid' => [],
      'entity_type' => [
        [
          'value' => 'entity_test',
        ],
      ],
      'entity_id' => [
        [
          'target_id' => 1,
          'target_type' => 'entity_test',
          'target_uuid' => EntityTest::load(1)->uuid(),
          'url' => base_path() . 'entity_test/1',
        ],
      ],
      'field_name' => [
        [
          'value' => 'reservation',
        ],
      ],
      'name' => [],
      'homepage' => [],
      'thread' => [
        [
          'value' => '01/',
        ],
      ],
      'reservation_body' => [
        [
          'value' => 'The name "llama" was adopted by European settlers from native Peruvians.',
          'format' => 'plain_text',
          'processed' => '<p>The name &quot;llama&quot; was adopted by European settlers from native Peruvians.</p>' . "\n",
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getNormalizedPostEntity() {
    return [
      'reservation_type' => [
        [
          'target_id' => 'reservation',
        ],
      ],
      'entity_type' => [
        [
          'value' => 'entity_test',
        ],
      ],
      'entity_id' => [
        [
          'target_id' => (int) EntityTest::load(1)->id(),
        ],
      ],
      'field_name' => [
        [
          'value' => 'reservation',
        ],
      ],
      'subject' => [
        [
          'value' => 'Dramallama',
        ],
      ],
      'reservation_body' => [
        [
          'value' => 'Llamas are awesome.',
          'format' => 'plain_text',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getNormalizedPatchEntity() {
    return array_diff_key($this->getNormalizedPostEntity(), ['entity_type' => TRUE, 'entity_id' => TRUE, 'field_name' => TRUE]);
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedCacheTags() {
    return Cache::mergeTags(parent::getExpectedCacheTags(), ['config:filter.format.plain_text']);
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedCacheContexts() {
    return Cache::mergeContexts(['languages:language_interface', 'theme'], parent::getExpectedCacheContexts());
  }

  /**
   * Tests POSTing a reservation without critical base fields.
   *
   * Tests with the most minimal normalization possible: the one returned by
   * ::getNormalizedPostEntity().
   *
   * But Reservation entities have some very special edge cases:
   * - base fields that are not marked as required in
   *   \Drupal\reservation\Entity\Reservation::baseFieldDefinitions() yet in fact are
   *   required.
   * - base fields that are marked as required, but yet can still result in
   *   validation errors other than "missing required field".
   */
  public function testPostDxWithoutCriticalBaseFields() {
    $this->initAuthentication();
    $this->provisionEntityResource();
    $this->setUpAuthorization('POST');

    $url = $this->getEntityResourcePostUrl()->setOption('query', ['_format' => static::$format]);
    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = static::$mimeType;
    $request_options[RequestOptions::HEADERS]['Content-Type'] = static::$mimeType;
    $request_options = array_merge_recursive($request_options, $this->getAuthenticationRequestOptions('POST'));

    // DX: 422 when missing 'entity_type' field.
    $request_options[RequestOptions::BODY] = $this->serializer->encode(array_diff_key($this->getNormalizedPostEntity(), ['entity_type' => TRUE]), static::$format);
    $response = $this->request('POST', $url, $request_options);
    $this->assertResourceErrorResponse(422, "Unprocessable Entity: validation failed.\nentity_type: This value should not be null.\n", $response);

    // DX: 422 when missing 'entity_id' field.
    $request_options[RequestOptions::BODY] = $this->serializer->encode(array_diff_key($this->getNormalizedPostEntity(), ['entity_id' => TRUE]), static::$format);
    $response = $this->request('POST', $url, $request_options);
    $this->assertResourceErrorResponse(422, "Unprocessable Entity: validation failed.\nentity_id: This value should not be null.\n", $response);

    // DX: 422 when missing 'field_name' field.
    $request_options[RequestOptions::BODY] = $this->serializer->encode(array_diff_key($this->getNormalizedPostEntity(), ['field_name' => TRUE]), static::$format);
    $response = $this->request('POST', $url, $request_options);
    $this->assertResourceErrorResponse(422, "Unprocessable Entity: validation failed.\nfield_name: This value should not be null.\n", $response);
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedUnauthorizedAccessMessage($method) {
    switch ($method) {
      case 'GET';
        return "The 'access reservations' permission is required and the reservation must be published.";

      case 'POST';
        return "The 'post reservations' permission is required.";

      case 'PATCH';
        return "The 'edit own reservations' permission is required, the user must be the reservation author, and the reservation must be published.";

      case 'DELETE':
        // \Drupal\reservation\ReservationAccessControlHandler::checkAccess() does not
        // specify a reason for not allowing a reservation to be deleted.
        return '';
    }
  }

  /**
   * Tests POSTing a reservation with and without 'skip reservation approval'
   */
  public function testPostSkipReservationApproval() {
    $this->initAuthentication();
    $this->provisionEntityResource();
    $this->setUpAuthorization('POST');

    // Create request.
    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = static::$mimeType;
    $request_options[RequestOptions::HEADERS]['Content-Type'] = static::$mimeType;
    $request_options = array_merge_recursive($request_options, $this->getAuthenticationRequestOptions('POST'));
    $request_options[RequestOptions::BODY] = $this->serializer->encode($this->getNormalizedPostEntity(), static::$format);

    $url = $this->getEntityResourcePostUrl()->setOption('query', ['_format' => static::$format]);

    // Status should be FALSE when posting as anonymous.
    $response = $this->request('POST', $url, $request_options);
    $unserialized = $this->serializer->deserialize((string) $response->getBody(), get_class($this->entity), static::$format);
    $this->assertResourceResponse(201, FALSE, $response);
    $this->assertFalse($unserialized->isPublished());

    // Grant anonymous permission to skip reservation approval.
    $this->grantPermissionsToTestedRole(['skip reservation approval']);

    // Status should be TRUE when posting as anonymous and skip reservation approval.
    $response = $this->request('POST', $url, $request_options);
    $unserialized = $this->serializer->deserialize((string) $response->getBody(), get_class($this->entity), static::$format);
    $this->assertResourceResponse(201, FALSE, $response);
    $this->assertTrue($unserialized->isPublished());
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedUnauthorizedEntityAccessCacheability($is_authenticated) {
    // @see \Drupal\reservation\ReservationAccessControlHandler::checkAccess()
    return parent::getExpectedUnauthorizedEntityAccessCacheability($is_authenticated)
      ->addCacheTags(['reservation:1']);
  }

}
