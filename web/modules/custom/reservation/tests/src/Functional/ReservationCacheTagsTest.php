<?php

namespace Drupal\Tests\reservation\Functional;

use Drupal\reservation\ReservationInterface;
use Drupal\reservation\ReservationManagerInterface;
use Drupal\reservation\Entity\Reservation;
use Drupal\reservation\Tests\ReservationTestTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\Tests\system\Functional\Entity\EntityWithUriCacheTagsTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the Reservation entity's cache tags.
 *
 * @group reservation
 */
class ReservationCacheTagsTest extends EntityWithUriCacheTagsTestBase {

  use ReservationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['reservation'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * @var \Drupal\entity_test\Entity\EntityTest
   */
  protected $entityTestCamelid;

  /**
   * @var \Drupal\entity_test\Entity\EntityTest
   */
  protected $entityTestHippopotamidae;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Give anonymous users permission to view reservations, so that we can verify
    // the cache tags of cached versions of reservation pages.
    $user_role = Role::load(RoleInterface::ANONYMOUS_ID);
    $user_role->grantPermission('access reservations');
    $user_role->save();
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

    // Display reservations in a flat list; threaded reservations are not render cached.
    $field = FieldConfig::loadByName('entity_test', 'bar', 'reservation');
    $field->setSetting('default_mode', ReservationManagerInterface::RESERVATION_MODE_FLAT);
    $field->save();

    // Create a "Camelids" test entity that the reservation will be assigned to.
    $this->entityTestCamelid = EntityTest::create([
      'name' => 'Camelids',
      'type' => 'bar',
    ]);
    $this->entityTestCamelid->save();

    // Create a "Llama" reservation.
    $reservation = Reservation::create([
      'subject' => 'Llama',
      'reservation_body' => [
        'value' => 'The name "llama" was adopted by European settlers from native Peruvians.',
        'format' => 'plain_text',
      ],
      'entity_id' => $this->entityTestCamelid->id(),
      'entity_type' => 'entity_test',
      'field_name' => 'reservation',
      'status' => ReservationInterface::PUBLISHED,
    ]);
    $reservation->save();

    return $reservation;
  }

  /**
   * Test that reservations correctly invalidate the cache tag of their host entity.
   */
  public function testReservationEntity() {
    $this->verifyPageCache($this->entityTestCamelid->toUrl(), 'MISS');
    $this->verifyPageCache($this->entityTestCamelid->toUrl(), 'HIT');

    // Create a "Hippopotamus" reservation.
    $this->entityTestHippopotamidae = EntityTest::create([
      'name' => 'Hippopotamus',
      'type' => 'bar',
    ]);
    $this->entityTestHippopotamidae->save();

    $this->verifyPageCache($this->entityTestHippopotamidae->toUrl(), 'MISS');
    $this->verifyPageCache($this->entityTestHippopotamidae->toUrl(), 'HIT');

    $hippo_reservation = Reservation::create([
      'subject' => 'Hippopotamus',
      'reservation_body' => [
        'value' => 'The common hippopotamus (Hippopotamus amphibius), or hippo, is a large, mostly herbivorous mammal in sub-Saharan Africa',
        'format' => 'plain_text',
      ],
      'entity_id' => $this->entityTestHippopotamidae->id(),
      'entity_type' => 'entity_test',
      'field_name' => 'reservation',
      'status' => ReservationInterface::PUBLISHED,
    ]);
    $hippo_reservation->save();

    // Ensure that a new reservation only invalidates the reservationed entity.
    $this->verifyPageCache($this->entityTestCamelid->toUrl(), 'HIT');
    $this->verifyPageCache($this->entityTestHippopotamidae->toUrl(), 'MISS');
    $this->assertSession()->pageTextContains($hippo_reservation->getSubject());

    // Ensure that updating an existing reservation only invalidates the reservationed
    // entity.
    $this->entity->save();
    $this->verifyPageCache($this->entityTestCamelid->toUrl(), 'MISS');
    $this->verifyPageCache($this->entityTestHippopotamidae->toUrl(), 'HIT');
  }

  /**
   * {@inheritdoc}
   */
  protected function getAdditionalCacheContextsForEntity(EntityInterface $entity) {
    return [];
  }

  /**
   * {@inheritdoc}
   *
   * Each reservation must have a reservation body, which always has a text format.
   */
  protected function getAdditionalCacheTagsForEntity(EntityInterface $entity) {
    /** @var \Drupal\reservation\ReservationInterface $entity */
    return [
      'config:filter.format.plain_text',
      'user:' . $entity->getOwnerId(),
      'user_view',
    ];
  }

}
