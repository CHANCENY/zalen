<?php

namespace Drupal\Tests\reservation\Kernel\Views;

use Drupal\reservation\Entity\Reservation;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\views\Entity\View;
use Drupal\views\Views;

/**
 * Tests reservation user name field.
 *
 * @group reservation
 */
class ReservationUserNameTest extends ViewsKernelTestBase {

  /**
   * Admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;
  /**
   * {@inheritdoc}
   */
  protected static $modules = ['user', 'reservation', 'entity_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE): void {
    parent::setUp($import_test_views);

    $this->installEntitySchema('user');
    $this->installEntitySchema('reservation');
    $this->installEntitySchema('entity_test');
    // Create the anonymous role.
    $this->installConfig(['user']);

    // Create an anonymous user.
    $storage = \Drupal::entityTypeManager()->getStorage('user');
    // Insert a row for the anonymous user.
    $storage
      ->create([
        'uid' => 0,
        'name' => '',
        'status' => 0,
      ])
      ->save();

    $admin_role = Role::create([
      'id' => 'admin',
      'permissions' => ['administer reservations', 'access user profiles'],
    ]);
    $admin_role->save();

    /* @var \Drupal\user\RoleInterface $anonymous_role */
    $anonymous_role = Role::load(Role::ANONYMOUS_ID);
    $anonymous_role->grantPermission('access reservations');
    $anonymous_role->save();

    $this->adminUser = User::create([
      'name' => $this->randomMachineName(),
      'roles' => [$admin_role->id()],
    ]);
    $this->adminUser->save();

    $host = EntityTest::create(['name' => $this->randomString()]);
    $host->save();

    // Create some reservations.
    $reservation = Reservation::create([
      'subject' => 'My reservation title',
      'uid' => $this->adminUser->id(),
      'name' => $this->adminUser->label(),
      'entity_type' => 'entity_test',
      'field_name' => 'reservation',
      'entity_id' => $host->id(),
      'reservation_type' => 'entity_test',
      'status' => 1,
    ]);
    $reservation->save();

    $reservation_anonymous = Reservation::create([
      'subject' => 'Anonymous reservation title',
      'uid' => 0,
      'name' => 'barry',
      'mail' => 'test@example.com',
      'homepage' => 'https://example.com',
      'entity_type' => 'entity_test',
      'field_name' => 'reservation',
      'entity_id' => $host->id(),
      'reservation_type' => 'entity_test',
      'created' => 123456,
      'status' => 1,
    ]);
    $reservation_anonymous->save();
  }

  /**
   * Test the username formatter.
   */
  public function testUsername() {
    $view_id = $this->randomMachineName();
    $view = View::create([
      'id' => $view_id,
      'base_table' => 'reservation_field_data',
      'display' => [
        'default' => [
          'display_plugin' => 'default',
          'id' => 'default',
          'display_options' => [
            'fields' => [
              'name' => [
                'table' => 'reservation_field_data',
                'field' => 'name',
                'id' => 'name',
                'plugin_id' => 'field',
                'type' => 'reservation_username',
              ],
              'subject' => [
                'table' => 'reservation_field_data',
                'field' => 'subject',
                'id' => 'subject',
                'plugin_id' => 'field',
                'type' => 'string',
                'settings' => [
                  'link_to_entity' => TRUE,
                ],
              ],
            ],
          ],
        ],
      ],
    ]);
    $view->save();

    /* @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');

    /* @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = \Drupal::service('renderer');

    $account_switcher->switchTo($this->adminUser);
    $executable = Views::getView($view_id);
    $build = $executable->preview();
    $this->setRawContent($renderer->renderRoot($build));
    dump($this->getRawContent());

    $this->assertLink('My reservation title');
    $this->assertLink('Anonymous reservation title');
    // Display plugin of the view is showing the name field. When reservation
    // belongs to an authenticated user the name field has no value.
    $reservation_author = $this->xpath('//div[contains(@class, :class)]/span[normalize-space(text())=""]', [
      ':class' => 'views-field-subject',
    ]);
    $this->assertTrue(!empty($reservation_author));
    // When reservation belongs to an anonymous user the name field has a value and
    // it is rendered correctly.
    $this->assertLink('barry (not verified)');

    $account_switcher->switchTo(new AnonymousUserSession());
    $executable = Views::getView($view_id);
    $executable->storage->invalidateCaches();

    $build = $executable->preview();
    $this->setRawContent($renderer->renderRoot($build));

    // No access to user-profiles, so shouldn't be able to see links.
    $this->assertNoLink($this->adminUser->label());
    // Note: External users aren't pointing to drupal user profiles.
    $this->assertLink('barry (not verified)');
    dump($this->getRawContent());
    $this->assertLink('My reservation title');
    $this->assertLink('Anonymous reservation title');
  }

}
