<?php

namespace Drupal\Tests\reservation\Kernel\Views;

use Drupal\reservation\ReservationManagerInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\views\Views;
use Drupal\Tests\BrowserTestBase;


/**
 * Tests the reservation link field handlers.
 *
 * @group reservation
 */
class ReservationLinksTest extends ReservationViewsKernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['entity_test'];

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_reservation'];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE): void {
    parent::setUp($import_test_views);

    $this->installEntitySchema('entity_test');
  }

  /**
   * Test the reservation approve link.
   */
  public function testLinkApprove() {
    $host = EntityTest::create(['name' => $this->randomString()]);
    $host->save();

    // Create an unapproved reservation.
    $reservation = $this->reservationStorage->create([
      'uid' => $this->adminUser->id(),
      'entity_type' => 'entity_test',
      'field_name' => 'reservation',
      'entity_id' => $host->id(),
      'reservation_type' => 'entity_test',
      'status' => 0,
    ]);
    $reservation->save();

    $view = Views::getView('test_reservation');
    $view->setDisplay();

    $view->displayHandlers->get('default')->overrideOption('fields', [
      'approve_reservation' => [
        'table' => 'reservation',
        'field' => 'approve_reservation',
        'id' => 'approve_reservation',
        'plugin_id' => 'reservation_link_approve',
      ],
    ]);
    $view->save();

    /* @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');
    $account_switcher->switchTo($this->adminUser);

    $view->preview();

    // Check if I can see the reservation approve link on an unapproved reservation.
    $approve_reservation = $view->style_plugin->getField(0, 'approve_reservation');
    $options = ['query' => ['destination' => '/']];
    $url = Url::fromRoute('reservation.approve', ['reservation' => $reservation->id()], $options);
    $this->assertEquals((string) $approve_reservation, Link::fromTextAndUrl('Approve', $url)->toString(), 'Found a reservation approve link for an unapproved reservation.');

    // Approve the reservation.
    $reservation->setPublished();
    $reservation->save();
    $view = Views::getView('test_reservation');
    $view->preview();

    // Check if I can see the reservation approve link on an approved reservation.
    $approve_reservation = $view->style_plugin->getField(1, 'approve_reservation');
    $this->assertEmpty((string) $approve_reservation, "Didn't find a reservation approve link for an already approved reservation.");

    // Check if I can see the reservation approve link on an approved reservation as an
    // anonymous user.
    $account_switcher->switchTo(new AnonymousUserSession());
    // Set the reservation as unpublished again.
    $reservation->setUnpublished();
    $reservation->save();

    $view = Views::getView('test_reservation');
    $view->preview();
    $replyto_reservation = $view->style_plugin->getField(0, 'approve_reservation');
    $this->assertEmpty((string) $replyto_reservation, "I can't approve the reservation as an anonymous user.");
  }

  /**
   * Test the reservation reply link.
   */
  public function testLinkReply() {
    $this->enableModules(['field']);
    $this->installSchema('reservation', ['reservation_entity_statistics']);
    $this->installConfig(['field']);

    $field_storage_reservation = FieldStorageConfig::create([
      'field_name' => 'reservation',
      'type' => 'reservation',
      'entity_type' => 'entity_test',
    ]);
    $field_storage_reservation->save();
    // Create a reservation field which allows threading.
    $field_reservation = FieldConfig::create([
      'field_name' => 'reservation',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'settings' => [
        'default_mode' => ReservationManagerInterface::RESERVATION_MODE_THREADED,
      ],
    ]);
    $field_reservation->save();

    $host = EntityTest::create(['name' => $this->randomString()]);
    $host->save();
    // Attach an unapproved reservation to the test entity.
    $reservation = $this->reservationStorage->create([
      'uid' => $this->adminUser->id(),
      'entity_type' => 'entity_test',
      'entity_id' => $host->id(),
      'reservation_type' => 'entity_test',
      'field_name' => $field_storage_reservation->getName(),
      'status' => 0,
    ]);
    $reservation->save();

    $view = Views::getView('test_reservation');
    $view->setDisplay();

    $view->displayHandlers->get('default')->overrideOption('fields', [
      'replyto_reservation' => [
        'table' => 'reservation',
        'field' => 'replyto_reservation',
        'id' => 'replyto_reservation',
        'plugin_id' => 'reservation_link_reply',
        'entity_type' => 'reservation',
      ],
    ]);
    $view->save();

    /* @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');
    $account_switcher->switchTo($this->adminUser);
    $view->preview();

    // Check if I can see the reply link on an unapproved reservation.
    $replyto_reservation = $view->style_plugin->getField(0, 'replyto_reservation');
    $this->assertEmpty((string) $replyto_reservation, "I can't reply to an unapproved reservation.");

    // Approve the reservation.
    $reservation->setPublished();
    $reservation->save();
    $view = Views::getView('test_reservation');
    $view->preview();

    // Check if I can see the reply link on an approved reservation.
    $replyto_reservation = $view->style_plugin->getField(0, 'replyto_reservation');
    $url = Url::fromRoute('reservation.reply', [
      'entity_type' => 'entity_test',
      'entity' => $host->id(),
      'field_name' => 'reservation',
      'pid' => $reservation->id(),
    ]);
    $this->assertEquals((string) $replyto_reservation, Link::fromTextAndUrl('Reply', $url)->toString(), 'Found the reservation reply link as an admin user.');

    // Check if I can see the reply link as an anonymous user.
    $account_switcher->switchTo(new AnonymousUserSession());
    $view = Views::getView('test_reservation');
    $view->preview();
    $replyto_reservation = $view->style_plugin->getField(0, 'replyto_reservation');
    $this->assertEmpty((string) $replyto_reservation, "Didn't find the reservation reply link as an anonymous user.");
  }

}
