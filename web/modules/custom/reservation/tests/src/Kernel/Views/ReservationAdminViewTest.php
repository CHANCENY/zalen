<?php

namespace Drupal\Tests\reservation\Kernel\Views;

use Drupal\reservation\Entity\Reservation;
use Drupal\reservation\Entity\ReservationType;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\views\Views;

/**
 * Tests reservation admin view filters.
 *
 * @group reservation
 */
class ReservationAdminViewTest extends ViewsKernelTestBase {

  /**
   * Reservations.
   *
   * @var \Drupal\reservation\Entity\Reservation[]
   */
  protected $reservations = [];

  /**
   * Admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;
  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'reservation',
    'entity_test',
    'language',
    'locale',
  ];

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

    // Create user 1 so that the user created later in the test has a different
    // user ID.
    // @todo Remove in https://www.drupal.org/node/540008.
    User::create(['uid' => 1, 'name' => 'user1'])->save();

    // Enable another language.
    ConfigurableLanguage::createFromLangcode('ur')->save();
    // Rebuild the container to update the default language container variable.
    $this->container->get('kernel')->rebuildContainer();

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
    // Created admin role.
    $admin_role = Role::create([
      'id' => 'admin',
      'permissions' => ['administer reservations', 'skip reservation approval'],
    ]);
    $admin_role->save();
    // Create the admin user.
    $this->adminUser = User::create([
      'name' => $this->randomMachineName(),
      'roles' => [$admin_role->id()],
    ]);
    $this->adminUser->save();
    // Create a reservation type.
    ReservationType::create([
      'id' => 'reservation',
      'label' => 'Default reservations',
      'target_entity_type_id' => 'entity_test',
      'description' => 'Default reservation field',
    ])->save();
    // Create a reservationed entity.
    $entity = EntityTest::create();
    $entity->name->value = $this->randomMachineName();
    $entity->save();

    // Create some reservations.
    $reservation = Reservation::create([
      'subject' => 'My reservation title',
      'uid' => $this->adminUser->id(),
      'entity_type' => 'entity_test',
      'field_name' => 'reservation',
      'reservation_type' => 'reservation',
      'status' => 1,
      'entity_id' => $entity->id(),
    ]);
    $reservation->save();

    $this->reservations[] = $reservation;

    $reservation_anonymous = Reservation::create([
      'subject' => 'Anonymous reservation title',
      'uid' => 0,
      'name' => 'barry',
      'mail' => 'test@example.com',
      'homepage' => 'https://example.com',
      'entity_type' => 'entity_test',
      'field_name' => 'reservation',
      'reservation_type' => 'reservation',
      'created' => 123456,
      'status' => 1,
      'entity_id' => $entity->id(),
    ]);
    $reservation_anonymous->save();
    $this->reservations[] = $reservation_anonymous;
  }

  /**
   * Tests reservation admin view filters.
   */
  public function testFilters() {
    $this->doTestFilters('page_published');
    // Unpublish the reservations to test the Unapproved reservations tab.
    foreach ($this->reservations as $reservation) {
      $reservation->setUnpublished();
      $reservation->save();
    }
    $this->doTestFilters('page_unapproved');
  }

  /**
   * Tests reservation admin view display.
   *
   * @param string $display_id
   *   The display ID.
   */
  protected function doTestFilters($display_id) {
    $reservation = $this->reservations[0];
    $reservation_anonymous = $this->reservations[1];
    /* @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');

    /* @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = \Drupal::service('renderer');

    $account_switcher->switchTo($this->adminUser);
    $executable = Views::getView('reservation');
    $build = $executable->preview($display_id);
    $this->setRawContent($renderer->renderRoot($build));
    dump($this->getRawContent());

    // Assert the exposed filters on the admin page.
    $this->assertField('subject');
    $this->assertField('author_name');
    $this->assertField('langcode');

    $elements = $this->cssSelect('input[type="checkbox"]');
    $this->assertCount(2, $elements, 'There are two reservations on the page.');
    $this->assertText($reservation->label());
    $this->assertText($reservation_anonymous->label());
    $executable->destroy();

    // Test the Subject filter.
    $executable->setExposedInput(['subject' => 'Anonymous']);
    $build = $executable->preview($display_id);
    $this->setRawContent($renderer->renderRoot($build));
    dump($this->getRawContent());

    $elements = $this->cssSelect('input[type="checkbox"]');
    $this->assertCount(1, $elements, 'Only anonymous reservation is visible.');
    $this->assertNoText($reservation->label());
    $this->assertText($reservation_anonymous->label());
    $executable->destroy();

    $executable->setExposedInput(['subject' => 'My reservation']);
    $build = $executable->preview($display_id);
    $this->setRawContent($renderer->renderRoot($build));
    dump($this->getRawContent());

    $elements = $this->cssSelect('input[type="checkbox"]');
    $this->assertCount(1, $elements, 'Only admin reservation is visible.');
    $this->assertText($reservation->label());
    $this->assertNoText($reservation_anonymous->label());
    $executable->destroy();

    // Test the combine filter using author name.
    $executable->setExposedInput(['author_name' => 'barry']);
    $build = $executable->preview($display_id);
    $this->setRawContent($renderer->renderRoot($build));
    dump($this->getRawContent());

    $elements = $this->cssSelect('input[type="checkbox"]');
    $this->assertCount(1, $elements, 'Only anonymous reservation is visible.');
    $this->assertNoText($reservation->label());
    $this->assertText($reservation_anonymous->label());
    $executable->destroy();

    // Test the combine filter using username.
    $executable->setExposedInput(['author_name' => $this->adminUser->label()]);
    $build = $executable->preview($display_id);
    $this->setRawContent($renderer->renderRoot($build));
    dump($this->getRawContent());

    $elements = $this->cssSelect('input[type="checkbox"]');
    $this->assertCount(1, $elements, 'Only admin reservation is visible.');
    $this->assertText($reservation->label());
    $this->assertNoText($reservation_anonymous->label());
    $executable->destroy();

    // Test the language filter.
    $executable->setExposedInput(['langcode' => '***LANGUAGE_site_default***']);
    $build = $executable->preview($display_id);
    $this->setRawContent($renderer->renderRoot($build));
    dump($this->getRawContent());

    $elements = $this->cssSelect('input[type="checkbox"]');
    $this->assertCount(2, $elements, 'Both reservations are visible.');
    $this->assertText($reservation->label());
    $this->assertText($reservation_anonymous->label());
    $executable->destroy();

    // Tests reservation translation filter.
    if (!$reservation->hasTranslation('ur')) {
      // If we don't have the translation then create one.
      $reservation_translation = $reservation->addTranslation('ur', ['subject' => 'ur title']);
      $reservation_translation->save();
    }
    else {
      // If we have the translation then unpublish it.
      $reservation_translation = $reservation->getTranslation('ur');
      $reservation_translation->setUnpublished();
      $reservation_translation->save();
    }
    if (!$reservation_anonymous->hasTranslation('ur')) {
      // If we don't have the translation then create one.
      $reservation_anonymous_translation = $reservation_anonymous->addTranslation('ur', ['subject' => 'ur Anonymous title']);
      $reservation_anonymous_translation->save();
    }
    else {
      // If we have the translation then unpublish it.
      $reservation_anonymous_translation = $reservation_anonymous->getTranslation('ur');
      $reservation_anonymous_translation->setUnpublished();
      $reservation_anonymous_translation->save();
    }

    $executable->setExposedInput(['langcode' => 'ur']);
    $build = $executable->preview($display_id);
    $this->setRawContent($renderer->renderRoot($build));
    dump($this->getRawContent());

    $elements = $this->cssSelect('input[type="checkbox"]');
    $this->assertCount(2, $elements, 'Both reservations are visible.');
    $this->assertNoText($reservation->label());
    $this->assertNoText($reservation_anonymous->label());
    $this->assertText($reservation_translation->label());
    $this->assertText($reservation_anonymous_translation->label());
    $executable->destroy();
  }

}
