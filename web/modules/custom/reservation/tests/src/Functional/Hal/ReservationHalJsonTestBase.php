<?php

namespace Drupal\Tests\reservation\Functional\Hal;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\Tests\reservation\Functional\Rest\ReservationResourceTestBase;
use Drupal\Tests\hal\Functional\EntityResource\HalEntityNormalizationTrait;
use Drupal\user\Entity\User;

abstract class ReservationHalJsonTestBase extends ReservationResourceTestBase {

  use HalEntityNormalizationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['hal'];

  /**
   * {@inheritdoc}
   */
  protected static $format = 'hal_json';

  /**
   * {@inheritdoc}
   */
  protected static $mimeType = 'application/hal+json';

  /**
   * {@inheritdoc}
   *
   * The HAL+JSON format causes different PATCH-protected fields. For some
   * reason, the 'pid' and 'homepage' fields are NOT PATCH-protected, even
   * though they are for non-HAL+JSON serializations.
   *
   * @todo fix in https://www.drupal.org/node/2824271
   */
  protected static $patchProtectedFieldNames = [
    'status' => "The 'administer reservations' permission is required.",
    'created' => "The 'administer reservations' permission is required.",
    'changed' => NULL,
    'thread' => NULL,
    'entity_type' => NULL,
    'field_name' => NULL,
    'uid' => "The 'administer reservations' permission is required.",
    'entity_id' => NULL,
  ];

  /**
   * {@inheritdoc}
   */
  protected function getExpectedNormalizedEntity() {
    $default_normalization = parent::getExpectedNormalizedEntity();

    $normalization = $this->applyHalFieldNormalization($default_normalization);

    // Because \Drupal\reservation\Entity\Reservation::getOwner() generates an in-memory
    // User entity without a UUID, we cannot use it.
    $author = User::load($this->entity->getOwnerId());
    $reservationed_entity = EntityTest::load(1);
    return $normalization + [
      '_links' => [
        'self' => [
          'href' => $this->baseUrl . '/reservation/1?_format=hal_json',
        ],
        'type' => [
          'href' => $this->baseUrl . '/rest/type/reservation/reservation',
        ],
        $this->baseUrl . '/rest/relation/reservation/reservation/entity_id' => [
          [
            'href' => $this->baseUrl . '/entity_test/1?_format=hal_json',
          ],
        ],
        $this->baseUrl . '/rest/relation/reservation/reservation/uid' => [
          [
            'href' => $this->baseUrl . '/user/' . $author->id() . '?_format=hal_json',
            'lang' => 'en',
          ],
        ],
      ],
      '_embedded' => [
        $this->baseUrl . '/rest/relation/reservation/reservation/entity_id' => [
          [
            '_links' => [
              'self' => [
                'href' => $this->baseUrl . '/entity_test/1?_format=hal_json',
              ],
              'type' => [
                'href' => $this->baseUrl . '/rest/type/entity_test/bar',
              ],
            ],
            'uuid' => [
              ['value' => $reservationed_entity->uuid()],
            ],
          ],
        ],
        $this->baseUrl . '/rest/relation/reservation/reservation/uid' => [
          [
            '_links' => [
              'self' => [
                'href' => $this->baseUrl . '/user/' . $author->id() . '?_format=hal_json',
              ],
              'type' => [
                'href' => $this->baseUrl . '/rest/type/user/user',
              ],
            ],
            'uuid' => [
              ['value' => $author->uuid()],
            ],
            'lang' => 'en',
          ],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getNormalizedPostEntity() {
    return parent::getNormalizedPostEntity() + [
      '_links' => [
        'type' => [
          'href' => $this->baseUrl . '/rest/type/reservation/reservation',
        ],
      ],
    ];
  }

}
