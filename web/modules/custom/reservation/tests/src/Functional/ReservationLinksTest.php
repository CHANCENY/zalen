<?php

namespace Drupal\Tests\reservation\Functional;

use Drupal\reservation\Plugin\Field\FieldType\ReservationItemInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\reservation\ReservationInterface;
use Drupal\user\RoleInterface;
use Drupal\reservation\Entity\Reservation;
use Drupal\Tests\BrowserTestBase;

/**
 * Basic reservation links tests to ensure markup present.
 *
 * @group reservation
 */
class ReservationLinksTest extends ReservationTestBase {

  /**
   * Reservation being tested.
   *
   * @var \Drupal\reservation\ReservationInterface
   */
  protected $reservation;

  /**
   * Seen reservations, array of reservation IDs.
   *
   * @var array
   */
  protected $seen = [];

  /**
   * Use the main node listing to test rendering on teasers.
   *
   * @var array
   *
   * @todo Remove this dependency.
   */
  protected static $modules = ['views'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that reservation links are output and can be hidden.
   */
  public function testReservationLinks() {
    // Bartik theme alters reservation links, so use a different theme.
    \Drupal::service('theme_installer')->install(['stark']);
    $this->config('system.theme')
      ->set('default', 'stark')
      ->save();

    // Remove additional user permissions from $this->webUser added by setUp(),
    // since this test is limited to anonymous and authenticated roles only.
    $roles = $this->webUser->getRoles();
    \Drupal::entityTypeManager()->getStorage('user_role')->load(reset($roles))->delete();

    // Create a reservation via CRUD API functionality, since
    // $this->postReservation() relies on actual user permissions.
    $reservation = Reservation::create([
      'cid' => NULL,
      'entity_id' => $this->node->id(),
      'entity_type' => 'node',
      'field_name' => 'reservation',
      'pid' => 0,
      'uid' => 0,
      'status' => ReservationInterface::PUBLISHED,
      'subject' => $this->randomMachineName(),
      'hostname' => '127.0.0.1',
      'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
      'reservation_body' => [['value' => $this->randomMachineName()]],
    ]);
    $reservation->save();
    $this->reservation = $reservation;

    // Change reservation settings.
    $this->setReservationSettings('form_location', ReservationItemInterface::FORM_BELOW, 'Set reservation form location');
    $this->setReservationAnonymous(TRUE);
    $this->node->reservation = ReservationItemInterface::OPEN;
    $this->node->save();

    // Change user permissions.
    $perms = [
      'access reservations' => 1,
      'post reservations' => 1,
      'skip reservation approval' => 1,
      'edit own reservations' => 1,
    ];
    user_role_change_permissions(RoleInterface::ANONYMOUS_ID, $perms);

    $nid = $this->node->id();

    // Assert basic link is output, actual functionality is unit-tested in
    // \Drupal\reservation\Tests\ReservationLinkBuilderTest.
    foreach (['node', "node/$nid"] as $path) {
      $this->drupalGet($path);

      // In teaser view, a link containing the reservation count is always
      // expected.
      if ($path == 'node') {
        $this->assertSession()->linkExists('1 reservation');
      }
      $this->assertSession()->linkExists('Add new reservation');
    }

    $display_repository = $this->container->get('entity_display.repository');

    // Change weight to make links go before reservation body.
    $display_repository->getViewDisplay('reservation', 'reservation')
      ->setComponent('links', ['weight' => -100])
      ->save();
    $this->drupalGet($this->node->toUrl());
    $element = $this->cssSelect('article.js-reservation > div');
    // Get last child element.
    $element = end($element);
    $this->assertSame('div', $element->getTagName(), 'Last element is reservation body.');

    // Change weight to make links go after reservation body.
    $display_repository->getViewDisplay('reservation', 'reservation')
      ->setComponent('links', ['weight' => 100])
      ->save();
    $this->drupalGet($this->node->toUrl());
    $element = $this->cssSelect('article.js-reservation > div');
    // Get last child element.
    $element = end($element);
    $this->assertNotEmpty($element->find('css', 'ul.links'), 'Last element is reservation links.');

    // Make sure we can hide node links.
    $display_repository->getViewDisplay('node', $this->node->bundle())
      ->removeComponent('links')
      ->save();
    $this->drupalGet($this->node->toUrl());
    $this->assertSession()->linkNotExists('1 reservation');
    $this->assertSession()->linkNotExists('Add new reservation');

    // Visit the full node, make sure there are links for the reservation.
    $this->drupalGet('node/' . $this->node->id());
    $this->assertSession()->pageTextContains($reservation->getSubject());
    $this->assertSession()->linkExists('Reply');

    // Make sure we can hide reservation links.
    $display_repository->getViewDisplay('reservation', 'reservation')
      ->removeComponent('links')
      ->save();
    $this->drupalGet('node/' . $this->node->id());
    $this->assertSession()->pageTextContains($reservation->getSubject());
    $this->assertSession()->linkNotExists('Reply');
  }

}
