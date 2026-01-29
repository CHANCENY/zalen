<?php

namespace Drupal\Tests\reservation\Functional;

use Drupal\Core\Url;
use Drupal\reservation\Entity\Reservation;
use Drupal\reservation\Entity\ReservationType;
use Drupal\reservation\Plugin\Field\FieldType\ReservationItemInterface;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Drupal\node\Entity\Node;
use Drupal\Tests\BrowserTestBase;

/**
 * Ensures that reservation type functions work correctly.
 *
 * @group reservation
 */
class ReservationTypeTest extends ReservationTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Admin user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $adminUser;

  /**
   * Permissions to grant admin user.
   *
   * @var array
   */
  protected $permissions = [
    'administer reservations',
    'administer reservation fields',
    'administer reservation types',
  ];

  /**
   * Sets the test up.
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalPlaceBlock('page_title_block');

    $this->adminUser = $this->drupalCreateUser($this->permissions);
  }

  /**
   * Tests creating a reservation type programmatically and via a form.
   */
  public function testReservationTypeCreation() {
    // Create a reservation type programmatically.
    $type = $this->createReservationType('other');

    $reservation_type = ReservationType::load('other');
    $this->assertInstanceOf(ReservationType::class, $reservation_type);

    // Log in a test user.
    $this->drupalLogin($this->adminUser);

    // Ensure that the new reservation type admin page can be accessed.
    $this->drupalGet('admin/structure/reservation/manage/' . $type->id());
    $this->assertSession()->statusCodeEquals(200);

    // Create a reservation type via the user interface.
    $edit = [
      'id' => 'foo',
      'label' => 'title for foo',
      'description' => '',
      'target_entity_type_id' => 'node',
    ];
    $this->submitForm('admin/structure/reservation/types/add', $edit, 'Save');
    $reservation_type = ReservationType::load('foo');
    $this->assertInstanceOf(ReservationType::class, $reservation_type);

    // Check that the reservation type was created in site default language.
    $default_langcode = \Drupal::languageManager()->getDefaultLanguage()->getId();
    $this->assertEquals($default_langcode, $reservation_type->language()->getId());

    // Edit the reservation-type and ensure that we cannot change the entity-type.
    $this->drupalGet('admin/structure/reservation/manage/foo');
    $this->assertSession()->fieldNotExists('target_entity_type_id');
    $this->assertSession()->pageTextContains('Target entity type');
    // Save the form and ensure the entity-type value is preserved even though
    // the field isn't present.
    $this->submitForm([], 'Save');
    \Drupal::entityTypeManager()->getStorage('reservation_type')->resetCache(['foo']);
    $reservation_type = ReservationType::load('foo');
    $this->assertEquals('node', $reservation_type->getTargetEntityTypeId());
  }

  /**
   * Tests editing a reservation type using the UI.
   */
  public function testReservationTypeEditing() {
    $this->drupalLogin($this->adminUser);

    $field = FieldConfig::loadByName('reservation', 'reservation', 'reservation_body');
    $this->assertEquals('Reservation', $field->getLabel(), 'Reservation body field was found.');

    // Change the reservation type name.
    $this->drupalGet('admin/structure/reservation');
    $edit = [
      'label' => 'Bar',
    ];
    $this->submitForm('admin/structure/reservation/manage/reservation', $edit, 'Save');

    $this->drupalGet('admin/structure/reservation');
    $this->assertSession()->responseContains('Bar');
    $this->clickLink('Manage fields');
    // Verify that the original machine name was used in the URL.
    $this->assertSession()->addressEquals(Url::fromRoute('entity.reservation.field_ui_fields', ['reservation_type' => 'reservation']));
    $this->assertCount(1, $this->cssSelect('tr#reservation-body'), 'Body field exists.');

    // Remove the body field.
    $this->submitForm('admin/structure/reservation/manage/reservation/fields/reservation.reservation.reservation_body/delete', [], 'Delete');
    // Resave the settings for this type.
    $this->submitForm('admin/structure/reservation/manage/reservation', [], 'Save');
    // Check that the body field doesn't exist.
    $this->drupalGet('admin/structure/reservation/manage/reservation/fields');
    $this->assertCount(0, $this->cssSelect('tr#reservation-body'), 'Body field does not exist.');
  }

  /**
   * Tests deleting a reservation type that still has content.
   */
  public function testReservationTypeDeletion() {
    // Create a reservation type programmatically.
    $type = $this->createReservationType('foo');
    $this->drupalCreateContentType(['type' => 'page']);
    $this->addDefaultReservationField('node', 'page', 'foo', ReservationItemInterface::OPEN, 'foo');
    $field_storage = FieldStorageConfig::loadByName('node', 'foo');

    $this->drupalLogin($this->adminUser);

    // Create a node.
    $node = Node::create([
      'type' => 'page',
      'title' => 'foo',
    ]);
    $node->save();

    // Add a new reservation of this type.
    $reservation = Reservation::create([
      'reservation_type' => 'foo',
      'entity_type' => 'node',
      'field_name' => 'foo',
      'entity_id' => $node->id(),
    ]);
    $reservation->save();

    // Attempt to delete the reservation type, which should not be allowed.
    $this->drupalGet('admin/structure/reservation/manage/' . $type->id() . '/delete');
    $this->assertSession()->responseContains(
      t('%label is used by 1 reservation on your site. You can not remove this reservation type until you have removed all of the %label reservations.', ['%label' => $type->label()])
    );
    $this->assertSession()->responseContains(
      t('%label is used by the %field field on your site. You can not remove this reservation type until you have removed the field.', [
        '%label' => 'foo',
        '%field' => 'node.foo',
      ])
    );
    $this->assertSession()->responseNotContains('This action cannot be undone.');

    // Delete the reservation and the field.
    $reservation->delete();
    $field_storage->delete();
    // Attempt to delete the reservation type, which should now be allowed.
    $this->drupalGet('admin/structure/reservation/manage/' . $type->id() . '/delete');
    $this->assertSession()->responseContains(
      t('Are you sure you want to delete the reservation type %type?', ['%type' => $type->id()])
    );
    $this->assertSession()->pageTextContains('This action cannot be undone.');

    // Test exception thrown when re-using an existing reservation type.
    try {
      $this->addDefaultReservationField('reservation', 'reservation', 'bar');
      $this->fail('Exception not thrown.');
    }
    catch (\InvalidArgumentException $e) {
      // Expected exception; just continue testing.
    }

    // Delete the reservation type.
    $this->submitForm('admin/structure/reservation/manage/' . $type->id() . '/delete', [], 'Delete');
    $this->assertNull(ReservationType::load($type->id()), 'Reservation type deleted.');
    $this->assertSession()->responseContains(t('The reservation type %label has been deleted.', ['%label' => $type->label()]));
  }

}
