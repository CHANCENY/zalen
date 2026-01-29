<?php

namespace Drupal\Tests\reservation\Kernel;

use Drupal\reservation\Tests\ReservationTestTrait;
use Drupal\Core\Cache\Cache;
use Drupal\reservation\ReservationInterface;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Drupal\reservation\Entity\Reservation;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\Tests\BrowserTestBase;


/**
 * Tests the bubbling up of reservation cache tags when using the Reservation list
 * formatter on an entity.
 *
 * @group reservation
 */
class ReservationDefaultFormatterCacheTagsTest extends EntityKernelTestBase {

  use ReservationTestTrait;

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = ['entity_test', 'reservation'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create user 1 so that the user created later in the test has a different
    // user ID.
    // @todo Remove in https://www.drupal.org/node/540008.
    $this->createUser(['uid' => 1, 'name' => 'user1'])->save();

    $this->container->get('module_handler')->loadInclude('reservation', 'install');
    reservation_install();

    $session = new Session();

    $request = Request::create('/');
    $request->setSession($session);

    /** @var \Symfony\Component\HttpFoundation\RequestStack $stack */
    $stack = $this->container->get('request_stack');
    $stack->pop();
    $stack->push($request);

    // Set the current user to one that can access reservations. Specifically, this
    // user does not have access to the 'administer reservations' permission, to
    // ensure only published reservations are visible to the end user.
    $current_user = $this->container->get('current_user');
    $current_user->setAccount($this->createUser([], ['access reservations', 'post reservations']));

    // Install tables and config needed to render reservations.
    $this->installSchema('reservation', ['reservation_entity_statistics']);
    $this->installConfig(['system', 'filter', 'reservation']);

    // Set up a field, so that the entity that'll be referenced bubbles up a
    // cache tag when rendering it entirely.
    $this->addDefaultReservationField('entity_test', 'entity_test');
  }

  /**
   * Tests the bubbling of cache tags.
   */
  public function testCacheTags() {
    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = $this->container->get('renderer');

    // Create the entity that will be reservationed upon.
    $reservationed_entity = EntityTest::create(['name' => $this->randomMachineName()]);
    $reservationed_entity->save();

    // Verify cache tags on the rendered entity before it has reservations.
    $build = \Drupal::entityTypeManager()
      ->getViewBuilder('entity_test')
      ->view($reservationed_entity);
    $renderer->renderRoot($build);
    $expected_cache_tags = [
      'entity_test_view',
      'entity_test:' . $reservationed_entity->id(),
      'config:core.entity_form_display.reservation.reservation.default',
      'config:field.field.reservation.reservation.reservation_body',
      'config:field.field.entity_test.entity_test.reservation',
      'config:field.storage.reservation.reservation_body',
      'config:user.settings',
    ];
    sort($expected_cache_tags);
    $this->assertEquals($expected_cache_tags, $build['#cache']['tags']);

    // Create a reservation on that entity. Reservation loading requires that the uid
    // also exists in the {users} table.
    $user = $this->createUser();
    $user->save();
    $reservation = Reservation::create([
      'subject' => 'Llama',
      'reservation_body' => [
        'value' => 'Llamas are cool!',
        'format' => 'plain_text',
      ],
      'entity_id' => $reservationed_entity->id(),
      'entity_type' => 'entity_test',
      'field_name' => 'reservation',
      'reservation_type' => 'reservation',
      'status' => ReservationInterface::PUBLISHED,
      'uid' => $user->id(),
    ]);
    $reservation->save();

    // Load reservationed entity so reservation_count gets computed.
    // @todo Remove the $reset = TRUE parameter after
    //   https://www.drupal.org/node/597236 lands. It's a temporary work-around.
    $storage = $this->container->get('entity_type.manager')->getStorage('entity_test');
    $storage->resetCache([$reservationed_entity->id()]);
    $reservationed_entity = $storage->load($reservationed_entity->id());

    // Verify cache tags on the rendered entity when it has reservations.
    $build = \Drupal::entityTypeManager()
      ->getViewBuilder('entity_test')
      ->view($reservationed_entity);
    $renderer->renderRoot($build);
    $expected_cache_tags = [
      'entity_test_view',
      'entity_test:' . $reservationed_entity->id(),
      'reservation_view',
      'reservation:' . $reservation->id(),
      'config:filter.format.plain_text',
      'user_view',
      'user:' . $user->id(),
      'config:core.entity_form_display.reservation.reservation.default',
      'config:field.field.reservation.reservation.reservation_body',
      'config:field.field.entity_test.entity_test.reservation',
      'config:field.storage.reservation.reservation_body',
      'config:user.settings',
    ];
    sort($expected_cache_tags);
    $this->assertEquals($expected_cache_tags, $build['#cache']['tags']);

    // Build a render array with the entity in a sub-element so that lazy
    // builder elements bubble up outside of the entity and we can check that
    // it got the correct cache max age.
    $build = ['#type' => 'container'];
    $build['entity'] = \Drupal::entityTypeManager()
      ->getViewBuilder('entity_test')
      ->view($reservationed_entity);
    $renderer->renderRoot($build);

    // The entity itself was cached but the top-level element is max-age 0 due
    // to the bubbled up max age due to the lazy-built reservation form.
    $this->assertSame(Cache::PERMANENT, $build['entity']['#cache']['max-age']);
    $this->assertSame(0, $build['#cache']['max-age'], 'Top level render array has max-age 0');

    // The children (fields) of the entity render array are only built in case
    // of a cache miss.
    $this->assertFalse(isset($build['entity']['reservation']), 'Cache hit');
  }

}
