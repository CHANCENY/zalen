<?php

namespace Drupal\Tests\reservation\Functional;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\reservation\Plugin\Field\FieldType\ReservationItemInterface;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Drupal\reservation\Entity\ReservationType;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests fields on reservations.
 *
 * @group reservation
 */
class ReservationFieldsTest extends ReservationTestBase {

  /**
   * Install the field UI.
   *
   * @var array
   */
  protected static $modules = ['field_ui'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'classy';

  /**
   * Tests that the default 'reservation_body' field is correctly added.
   */
  public function testReservationDefaultFields() {
    // Do not make assumptions on default node types created by the test
    // installation profile, and create our own.
    $this->drupalCreateContentType(['type' => 'test_node_type']);
    $this->addDefaultReservationField('node', 'test_node_type');

    // Check that the 'reservation_body' field is present on the reservation bundle.
    $field = FieldConfig::loadByName('reservation', 'reservation', 'reservation_body');
    $this->assertTrue(!empty($field), 'The reservation_body field is added when a reservation bundle is created');

    $field->delete();

    // Check that the 'reservation_body' field is not deleted since it is persisted
    // even if it has no fields.
    $field_storage = FieldStorageConfig::loadByName('reservation', 'reservation_body');
    $this->assertInstanceOf(FieldStorageConfig::class, $field_storage);

    // Create a new content type.
    $type_name = 'test_node_type_2';
    $this->drupalCreateContentType(['type' => $type_name]);
    $this->addDefaultReservationField('node', $type_name);

    // Check that the 'reservation_body' field exists and has an instance on the
    // new reservation bundle.
    $field_storage = FieldStorageConfig::loadByName('reservation', 'reservation_body');
    $this->assertInstanceOf(FieldStorageConfig::class, $field_storage);
    $field = FieldConfig::loadByName('reservation', 'reservation', 'reservation_body');
    $this->assertTrue(isset($field), new FormattableMarkup('The reservation_body field is present for reservations on type @type', ['@type' => $type_name]));

    // Test adding a field that defaults to ReservationItemInterface::CLOSED.
    $this->addDefaultReservationField('node', 'test_node_type', 'who_likes_ponies', ReservationItemInterface::CLOSED, 'who_likes_ponies');
    $field = FieldConfig::load('node.test_node_type.who_likes_ponies');
    $this->assertEquals(ReservationItemInterface::CLOSED, $field->getDefaultValueLiteral()[0]['status']);
  }

  /**
   * Tests that you can remove a reservation field.
   */
  public function testReservationFieldDelete() {
    $this->drupalCreateContentType(['type' => 'test_node_type']);
    $this->addDefaultReservationField('node', 'test_node_type');
    // We want to test the handling of removing the primary reservation field, so we
    // ensure there is at least one other reservation field attached to a node type
    // so that reservation_entity_load() runs for nodes.
    $this->addDefaultReservationField('node', 'test_node_type', 'reservation2');

    // Create a sample node.
    $node = $this->drupalCreateNode([
      'title' => 'Baloney',
      'type' => 'test_node_type',
    ]);

    $this->drupalLogin($this->webUser);

    $this->drupalGet('node/' . $node->nid->value);
    $elements = $this->cssSelect('.field--type-reservation');
    $this->assertCount(2, $elements, 'There are two reservation fields on the node.');

    // Delete the first reservation field.
    FieldStorageConfig::loadByName('node', 'reservation')->delete();
    $this->drupalGet('node/' . $node->nid->value);
    $elements = $this->cssSelect('.field--type-reservation');
    $this->assertCount(1, $elements, 'There is one reservation field on the node.');
  }

  /**
   * Tests link building with non-default reservation field names.
   */
  public function testReservationFieldLinksNonDefaultName() {
    $this->drupalCreateContentType(['type' => 'test_node_type']);
    $this->addDefaultReservationField('node', 'test_node_type', 'reservation2');

    $web_user2 = $this->drupalCreateUser([
      'access reservations',
      'post reservations',
      'create article content',
      'edit own reservations',
      'skip reservation approval',
      'access content',
    ]);

    // Create a sample node.
    $node = $this->drupalCreateNode([
      'title' => 'Baloney',
      'type' => 'test_node_type',
    ]);

    // Go to the node first so that webuser2 see new reservations.
    $this->drupalLogin($web_user2);
    $this->drupalGet($node->toUrl());
    $this->drupalLogout();

    // Test that buildReservationedEntityLinks() does not break when the 'reservation'
    // field does not exist. Requires at least one reservation.
    $this->drupalLogin($this->webUser);
    $this->postReservation($node, 'Here is a reservation', '', NULL, 'reservation2');
    $this->drupalLogout();

    $this->drupalLogin($web_user2);

    // We want to check the attached drupalSettings of
    // \Drupal\reservation\ReservationLinkBuilder::buildReservationedEntityLinks. Therefore
    // we need a node listing, let's use views for that.
    $this->container->get('module_installer')->install(['views'], TRUE);
    $this->drupalGet('node');

    $link_info = $this->getDrupalSettings()['reservation']['newReservationsLinks']['node']['reservation2']['2'];
    $this->assertSame(1, $link_info['new_reservation_count']);
    $this->assertSame($node->toUrl('canonical', ['fragment' => 'new'])->toString(), $link_info['first_new_reservation_link']);
  }

  /**
   * Tests creating a reservation field through the interface.
   */
  public function testReservationFieldCreate() {
    // Create user who can administer user fields.
    $user = $this->drupalCreateUser([
      'administer user fields',
    ]);
    $this->drupalLogin($user);

    // Create reservation field in account settings.
    $edit = [
      'new_storage_type' => 'reservation',
      'label' => 'User reservation',
      'field_name' => 'user_reservation',
    ];
    $this->submitForm('admin/config/people/accounts/fields/add-field', $edit, 'Save and continue');

    // Try to save the reservation field without selecting a reservation type.
    $edit = [];
    $this->submitForm('admin/config/people/accounts/fields/user.user.field_user_reservation/storage', $edit, 'Save field settings');
    // We should get an error message.
    $this->assertSession()->pageTextContains('An illegal choice has been detected. Please contact the site administrator.');

    // Create a reservation type for users.
    $bundle = ReservationType::create([
      'id' => 'user_reservation_type',
      'label' => 'user_reservation_type',
      'description' => '',
      'target_entity_type_id' => 'user',
    ]);
    $bundle->save();

    // Select a reservation type and try to save again.
    $edit = [
      'settings[reservation_type]' => 'user_reservation_type',
    ];
    $this->submitForm('admin/config/people/accounts/fields/user.user.field_user_reservation/storage', $edit, 'Save field settings');
    // We shouldn't get an error message.
    $this->assertSession()->pageTextNotContains('An illegal choice has been detected. Please contact the site administrator.');
  }

  /**
   * Tests that reservation module works when installed after a content module.
   */
  public function testReservationInstallAfterContentModule() {
    // Create a user to do module administration.
    $this->adminUser = $this->drupalCreateUser([
      'access administration pages',
      'administer modules',
    ]);
    $this->drupalLogin($this->adminUser);

    // Drop default reservation field added in ReservationTestBase::setUp().
    FieldStorageConfig::loadByName('node', 'reservation')->delete();
    if ($field_storage = FieldStorageConfig::loadByName('node', 'reservation_forum')) {
      $field_storage->delete();
    }

    // Purge field data now to allow reservation module to be uninstalled once the
    // field has been deleted.
    field_purge_batch(10);

    // Uninstall the reservation module.
    $edit = [];
    $edit['uninstall[reservation]'] = TRUE;
    $this->submitForm('admin/modules/uninstall', $edit, 'Uninstall');
    $this->submitForm([], 'Uninstall');
    $this->rebuildContainer();
    $this->assertFalse($this->container->get('module_handler')->moduleExists('reservation'), 'Reservation module uninstalled.');

    // Install core content type module (book).
    $edit = [];
    $edit['modules[book][enable]'] = 'book';
    $this->submitForm('admin/modules', $edit, 'Install');

    // Now install the reservation module.
    $edit = [];
    $edit['modules[reservation][enable]'] = 'reservation';
    $this->submitForm('admin/modules', $edit, 'Install');
    $this->rebuildContainer();
    $this->assertTrue($this->container->get('module_handler')->moduleExists('reservation'), 'Reservation module enabled.');

    // Create nodes of each type.
    $this->addDefaultReservationField('node', 'book');
    $book_node = $this->drupalCreateNode(['type' => 'book']);

    $this->drupalLogout();

    // Try to post a reservation on each node. A failure will be triggered if the
    // reservation body is missing on one of these forms, due to postReservation()
    // asserting that the body is actually posted correctly.
    $this->webUser = $this->drupalCreateUser([
      'access content',
      'access reservations',
      'post reservations',
      'skip reservation approval',
    ]);
    $this->drupalLogin($this->webUser);
    $this->postReservation($book_node, $this->randomMachineName(), $this->randomMachineName());
  }

}
