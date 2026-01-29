<?php

namespace Drupal\Tests\reservation\Functional;

use Drupal\reservation\ReservationInterface;
use Drupal\reservation\Entity\Reservation;
use Drupal\reservation\Entity\ReservationType;
use Drupal\reservation\Plugin\Field\FieldType\ReservationItemInterface;
use Drupal\reservation\Tests\ReservationTestTrait;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\BrowserTestBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Tests\field_ui\Traits\FieldUiTestTrait;
use Drupal\user\RoleInterface;

/**
 * Tests reservationing on a test entity.
 *
 * @group reservation
 */
class ReservationNonNodeTest extends BrowserTestBase {

  use FieldUiTestTrait;
  use ReservationTestTrait;

  protected static $modules = [
    'reservation',
    'user',
    'field_ui',
    'entity_test',
    'block',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'classy';

  /**
   * An administrative user with permission to configure reservation settings.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * The entity to use within tests.
   *
   * @var \Drupal\entity_test\Entity\EntityTest
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalPlaceBlock('system_breadcrumb_block');
    $this->drupalPlaceBlock('page_title_block');

    // Create a bundle for entity_test.
    entity_test_create_bundle('entity_test', 'Entity Test', 'entity_test');
    ReservationType::create([
      'id' => 'reservation',
      'label' => 'Reservation settings',
      'description' => 'Reservation settings',
      'target_entity_type_id' => 'entity_test',
    ])->save();
    // Create reservation field on entity_test bundle.
    $this->addDefaultReservationField('entity_test', 'entity_test');

    // Verify that bundles are defined correctly.
    $bundles = \Drupal::service('entity_type.bundle.info')->getBundleInfo('reservation');
    $this->assertEquals('Reservation settings', $bundles['reservation']['label']);

    // Create test user.
    $this->adminUser = $this->drupalCreateUser([
      'administer reservations',
      'skip reservation approval',
      'post reservations',
      'access reservations',
      'view test entity',
      'administer entity_test content',
    ]);

    // Enable anonymous and authenticated user reservations.
    user_role_grant_permissions(RoleInterface::ANONYMOUS_ID, [
      'access reservations',
      'post reservations',
      'skip reservation approval',
    ]);
    user_role_grant_permissions(RoleInterface::AUTHENTICATED_ID, [
      'access reservations',
      'post reservations',
      'skip reservation approval',
    ]);

    // Create a test entity.
    $random_label = $this->randomMachineName();
    $data = ['type' => 'entity_test', 'name' => $random_label];
    $this->entity = EntityTest::create($data);
    $this->entity->save();
  }

  /**
   * Posts a reservation.
   *
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   Entity to post reservation on or NULL to post to the previously loaded page.
   * @param string $reservation
   *   Reservation body.
   * @param string $subject
   *   Reservation subject.
   * @param mixed $contact
   *   Set to NULL for no contact info, TRUE to ignore success checking, and
   *   array of values to set contact info.
   *
   * @return \Drupal\reservation\ReservationInterface
   *   The new reservation entity.
   */
  public function postReservation(EntityInterface $entity, $reservation, $subject = '', $contact = NULL) {
    $edit = [];
    $edit['reservation_body[0][value]'] = $reservation;

    $field = FieldConfig::loadByName('entity_test', 'entity_test', 'reservation');
    $preview_mode = $field->getSetting('preview');

    // Must get the page before we test for fields.
    if ($entity !== NULL) {
      $this->drupalGet('reservation/reply/entity_test/' . $entity->id() . '/reservation');
    }

    // Determine the visibility of subject form field.
    $display_repository = $this->container->get('entity_display.repository');
    if ($display_repository->getFormDisplay('reservation', 'reservation')->getComponent('subject')) {
      // Subject input allowed.
      $edit['subject[0][value]'] = $subject;
    }
    else {
      $this->assertSession()->fieldValueNotEquals('subject[0][value]', '');
    }

    if ($contact !== NULL && is_array($contact)) {
      $edit += $contact;
    }
    switch ($preview_mode) {
      case DRUPAL_REQUIRED:
        // Preview required so no save button should be found.
        $this->assertSession()->buttonNotExists(t('Save'));
        $this->submitForm($edit, 'Preview');
        // Don't break here so that we can test post-preview field presence and
        // function below.
      case DRUPAL_OPTIONAL:
        $this->assertSession()->buttonExists(t('Preview'));
        $this->assertSession()->buttonExists(t('Save'));
        $this->submitForm($edit, 'Save');
        break;

      case DRUPAL_DISABLED:
        $this->assertSession()->buttonNotExists(t('Preview'));
        $this->assertSession()->buttonExists(t('Save'));
        $this->submitForm($edit, 'Save');
        break;
    }
    $match = [];
    // Get reservation ID
    preg_match('/#reservation-([0-9]+)/', $this->getURL(), $match);

    // Get reservation.
    if ($contact !== TRUE) {
      // If true then attempting to find error message.
      if ($subject) {
        $this->assertSession()->pageTextContains($subject);
      }
      $this->assertSession()->pageTextContains($reservation);
      // Check the reservation ID was extracted.
      $this->assertArrayHasKey(1, $match);
    }

    if (isset($match[1])) {
      return Reservation::load($match[1]);
    }
  }

  /**
   * Checks current page for specified reservation.
   *
   * @param \Drupal\reservation\ReservationInterface $reservation
   *   The reservation object.
   * @param bool $reply
   *   Boolean indicating whether the reservation is a reply to another reservation.
   *
   * @return bool
   *   Boolean indicating whether the reservation was found.
   */
  public function reservationExists(ReservationInterface $reservation = NULL, $reply = FALSE) {
    if ($reservation) {
      $regex = '/' . ($reply ? '<div class="indented">(.*?)' : '');
      $regex .= '<article(.*?)id="reservation-' . $reservation->id() . '"(.*?)';
      $regex .= $reservation->getSubject() . '(.*?)';
      $regex .= $reservation->reservation_body->value . '(.*?)';
      $regex .= '/s';

      return (boolean) preg_match($regex, $this->getSession()->getPage()->getContent());
    }
    else {
      return FALSE;
    }
  }

  /**
   * Checks whether the reservationer's contact information is displayed.
   *
   * @return bool
   *   Contact info is available.
   */
  public function reservationContactInfoAvailable() {
    return (bool) preg_match('/(input).*?(name="name").*?(input).*?(name="mail").*?(input).*?(name="homepage")/s', $this->getSession()->getPage()->getContent());
  }

  /**
   * Performs the specified operation on the specified reservation.
   *
   * @param object $reservation
   *   Reservation to perform operation on.
   * @param string $operation
   *   Operation to perform.
   * @param bool $approval
   *   Operation is found on approval page.
   */
  public function performReservationOperation($reservation, $operation, $approval = FALSE) {
    $edit = [];
    $edit['operation'] = $operation;
    $edit['reservations[' . $reservation->id() . ']'] = TRUE;
    $this->submitForm('admin/content/reservation' . ($approval ? '/approval' : ''), $edit, 'Update');

    if ($operation == 'delete') {
      $this->submitForm([], 'Delete');
      $this->assertSession()->responseContains(\Drupal::translation()->formatPlural(1, 'Deleted 1 reservation.', 'Deleted @count reservations.'));
    }
    else {
      $this->assertSession()->pageTextContains('The update has been performed.');
    }
  }

  /**
   * Gets the reservation ID for an unapproved reservation.
   *
   * @param string $subject
   *   Reservation subject to find.
   *
   * @return int
   *   Reservation ID.
   */
  public function getUnapprovedReservation($subject) {
    $this->drupalGet('admin/content/reservation/approval');
    preg_match('/href="(.*?)#reservation-([^"]+)"(.*?)>(' . $subject . ')/', $this->getSession()->getPage()->getContent(), $match);

    return $match[2];
  }

  /**
   * Tests anonymous reservation functionality.
   */
  public function testReservationFunctionality() {
    $limited_user = $this->drupalCreateUser([
      'administer entity_test fields',
    ]);
    $this->drupalLogin($limited_user);
    // Test that default field exists.
    $this->drupalGet('entity_test/structure/entity_test/fields');
    $this->assertSession()->pageTextContains('Reservations');
    $this->assertSession()->linkByHrefExists('entity_test/structure/entity_test/fields/entity_test.entity_test.reservation');
    // Test widget hidden option is not visible when there's no reservations.
    $this->drupalGet('entity_test/structure/entity_test/fields/entity_test.entity_test.reservation');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldNotExists('edit-default-value-input-reservation-und-0-status-0');
    // Test that field to change cardinality is not available.
    $this->drupalGet('entity_test/structure/entity_test/fields/entity_test.entity_test.reservation/storage');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldNotExists('cardinality_number');
    $this->assertSession()->fieldNotExists('cardinality');

    $this->drupalLogin($this->adminUser);

    // Test breadcrumb on reservation add page.
    $this->drupalGet('reservation/reply/entity_test/' . $this->entity->id() . '/reservation');
    $xpath = '//nav[@class="breadcrumb"]/ol/li[last()]/a';
    $this->assertEquals($this->entity->label(), current($this->xpath($xpath))->getText(), 'Last breadcrumb item is equal to node title on reservation reply page.');

    // Post a reservation.
    /** @var \Drupal\reservation\ReservationInterface $reservation1 */
    $reservation1 = $this->postReservation($this->entity, $this->randomMachineName(), $this->randomMachineName());
    $this->assertTrue($this->reservationExists($reservation1), 'Reservation on test entity exists.');

    // Test breadcrumb on reservation reply page.
    $this->drupalGet('reservation/reply/entity_test/' . $this->entity->id() . '/reservation/' . $reservation1->id());
    $xpath = '//nav[@class="breadcrumb"]/ol/li[last()]/a';
    $this->assertEquals($reservation1->getSubject(), current($this->xpath($xpath))->getText(), 'Last breadcrumb item is equal to reservation title on reservation reply page.');

    // Test breadcrumb on reservation edit page.
    $this->drupalGet('reservation/' . $reservation1->id() . '/edit');
    $xpath = '//nav[@class="breadcrumb"]/ol/li[last()]/a';
    $this->assertEquals($reservation1->getSubject(), current($this->xpath($xpath))->getText(), 'Last breadcrumb item is equal to reservation subject on edit page.');

    // Test breadcrumb on reservation delete page.
    $this->drupalGet('reservation/' . $reservation1->id() . '/delete');
    $xpath = '//nav[@class="breadcrumb"]/ol/li[last()]/a';
    $this->assertEquals($reservation1->getSubject(), current($this->xpath($xpath))->getText(), 'Last breadcrumb item is equal to reservation subject on delete confirm page.');

    // Unpublish the reservation.
    $this->performReservationOperation($reservation1, 'unpublish');
    $this->drupalGet('admin/content/reservation/approval');
    $this->assertSession()->responseContains('reservations[' . $reservation1->id() . ']');

    // Publish the reservation.
    $this->performReservationOperation($reservation1, 'publish', TRUE);
    $this->drupalGet('admin/content/reservation');
    $this->assertSession()->responseContains('reservations[' . $reservation1->id() . ']');

    // Delete the reservation.
    $this->performReservationOperation($reservation1, 'delete');
    $this->drupalGet('admin/content/reservation');
    $this->assertSession()->responseNotContains('reservations[' . $reservation1->id() . ']');

    // Post another reservation.
    $reservation1 = $this->postReservation($this->entity, $this->randomMachineName(), $this->randomMachineName());
    $this->assertTrue($this->reservationExists($reservation1), 'Reservation on test entity exists.');

    // Check that the reservation was found.
    $this->drupalGet('admin/content/reservation');
    $this->assertSession()->responseContains('reservations[' . $reservation1->id() . ']');

    // Check that entity access applies to administrative page.
    $this->assertSession()->pageTextContains($this->entity->label());
    $limited_user = $this->drupalCreateUser([
      'administer reservations',
    ]);
    $this->drupalLogin($limited_user);
    $this->drupalGet('admin/content/reservation');
    $this->assertSession()->pageTextNotContains($this->entity->label());

    $this->drupalLogout();

    // Deny anonymous users access to reservations.
    user_role_change_permissions(RoleInterface::ANONYMOUS_ID, [
      'access reservations' => FALSE,
      'post reservations' => FALSE,
      'skip reservation approval' => FALSE,
      'view test entity' => TRUE,
    ]);

    // Attempt to view reservations while disallowed.
    $this->drupalGet('entity-test/' . $this->entity->id());
    // Verify that reservations were not displayed.
    $this->assertSession()->responseNotMatches('@<h2[^>]*>Reservations</h2>@');
    $this->assertSession()->linkNotExists('Add new reservation', 'Link to add reservation was found.');

    // Attempt to view test entity reservation form while disallowed.
    $this->drupalGet('reservation/reply/entity_test/' . $this->entity->id() . '/reservation');
    $this->assertSession()->statusCodeEquals(403);
    $this->assertSession()->fieldNotExists('subject[0][value]');
    $this->assertSession()->fieldNotExists('reservation_body[0][value]');

    user_role_change_permissions(RoleInterface::ANONYMOUS_ID, [
      'access reservations' => TRUE,
      'post reservations' => FALSE,
      'view test entity' => TRUE,
      'skip reservation approval' => FALSE,
    ]);
    $this->drupalGet('entity_test/' . $this->entity->id());
    // Verify that the reservation field title is displayed.
    $this->assertSession()->responseMatches('@<h2[^>]*>Reservations</h2>@');
    $this->assertSession()->linkExists('Log in', 0, 'Link to login was found.');
    $this->assertSession()->linkExists('register', 0, 'Link to register was found.');
    $this->assertSession()->fieldNotExists('subject[0][value]');
    $this->assertSession()->fieldNotExists('reservation_body[0][value]');

    // Test the combination of anonymous users being able to post, but not view
    // reservations, to ensure that access to post reservations doesn't grant access to
    // view them.
    user_role_change_permissions(RoleInterface::ANONYMOUS_ID, [
      'access reservations' => FALSE,
      'post reservations' => TRUE,
      'skip reservation approval' => TRUE,
      'view test entity' => TRUE,
    ]);
    $this->drupalGet('entity_test/' . $this->entity->id());
    // Verify that reservations were not displayed.
    $this->assertSession()->responseNotMatches('@<h2[^>]*>Reservations</h2>@');
    $this->assertSession()->fieldValueEquals('subject[0][value]', '');
    $this->assertSession()->fieldValueEquals('reservation_body[0][value]', '');

    $this->drupalGet('reservation/reply/entity_test/' . $this->entity->id() . '/reservation/' . $reservation1->id());
    $this->assertSession()->statusCodeEquals(403);
    $this->assertSession()->pageTextNotContains($reservation1->getSubject());

    // Test reservation field widget changes.
    $limited_user = $this->drupalCreateUser([
      'administer entity_test fields',
      'view test entity',
      'administer entity_test content',
      'administer reservations',
    ]);
    $this->drupalLogin($limited_user);
    $this->drupalGet('entity_test/structure/entity_test/fields/entity_test.entity_test.reservation');
    $this->assertSession()->checkboxNotChecked('edit-default-value-input-reservation-0-status-0');
    $this->assertSession()->checkboxNotChecked('edit-default-value-input-reservation-0-status-1');
    $this->assertSession()->checkboxChecked('edit-default-value-input-reservation-0-status-2');
    // Test reservation option change in field settings.
    $edit = [
      'default_value_input[reservation][0][status]' => ReservationItemInterface::CLOSED,
      'settings[anonymous]' => ReservationInterface::ANONYMOUS_MAY_CONTACT,
    ];
    $this->submitForm($edit, 'Save settings');
    $this->drupalGet('entity_test/structure/entity_test/fields/entity_test.entity_test.reservation');
    $this->assertSession()->checkboxNotChecked('edit-default-value-input-reservation-0-status-0');
    $this->assertSession()->checkboxChecked('edit-default-value-input-reservation-0-status-1');
    $this->assertSession()->checkboxNotChecked('edit-default-value-input-reservation-0-status-2');
    $this->assertSession()->fieldValueEquals('settings[anonymous]', ReservationInterface::ANONYMOUS_MAY_CONTACT);

    // Add a new reservation-type.
    $bundle = ReservationType::create([
      'id' => 'foobar',
      'label' => 'Foobar',
      'description' => '',
      'target_entity_type_id' => 'entity_test',
    ]);
    $bundle->save();

    // Add a new reservation field.
    $storage_edit = [
      'settings[reservation_type]' => 'foobar',
    ];
    $this->fieldUIAddNewField('entity_test/structure/entity_test', 'foobar', 'Foobar', 'reservation', $storage_edit);

    // Add a third reservation field.
    $this->fieldUIAddNewField('entity_test/structure/entity_test', 'barfoo', 'BarFoo', 'reservation', $storage_edit);

    // Check the field contains the correct reservation type.
    $field_storage = FieldStorageConfig::load('entity_test.field_barfoo');
    $this->assertInstanceOf(FieldStorageConfig::class, $field_storage);
    $this->assertEquals('foobar', $field_storage->getSetting('reservation_type'));
    $this->assertEquals(1, $field_storage->getCardinality());

    // Test the new entity reservationing inherits default.
    $random_label = $this->randomMachineName();
    $data = ['bundle' => 'entity_test', 'name' => $random_label];
    $new_entity = EntityTest::create($data);
    $new_entity->save();
    $this->drupalGet('entity_test/manage/' . $new_entity->id() . '/edit');
    $this->assertSession()->checkboxNotChecked('edit-field-foobar-0-status-1');
    $this->assertSession()->checkboxChecked('edit-field-foobar-0-status-2');
    $this->assertSession()->fieldNotExists('edit-field-foobar-0-status-0');

    // @todo Check proper url and form https://www.drupal.org/node/2458323
    $this->drupalGet('reservation/reply/entity_test/reservation/' . $new_entity->id());
    $this->assertSession()->fieldNotExists('subject[0][value]');
    $this->assertSession()->fieldNotExists('reservation_body[0][value]');

    // Test removal of reservation_body field.
    $limited_user = $this->drupalCreateUser([
      'administer entity_test fields',
      'post reservations',
      'administer reservation fields',
      'administer reservation types',
      'view test entity',
    ]);
    $this->drupalLogin($limited_user);

    $this->drupalGet('reservation/reply/entity_test/' . $this->entity->id() . '/reservation');
    $this->assertSession()->fieldValueEquals('reservation_body[0][value]', '');
    $this->fieldUIDeleteField('admin/structure/reservation/manage/reservation', 'reservation.reservation.reservation_body', 'Reservation', 'Reservation settings');
    $this->drupalGet('reservation/reply/entity_test/' . $this->entity->id() . '/reservation');
    $this->assertSession()->fieldNotExists('reservation_body[0][value]');
    // Set subject field to autogenerate it.
    $edit = ['subject[0][value]' => ''];
    $this->submitForm($edit, 'Save');
  }

  /**
   * Tests reservation fields cannot be added to entity types without integer IDs.
   */
  public function testsNonIntegerIdEntities() {
    // Create a bundle for entity_test_string_id.
    entity_test_create_bundle('entity_test', 'Entity Test', 'entity_test_string_id');
    $limited_user = $this->drupalCreateUser([
      'administer entity_test_string_id fields',
    ]);
    $this->drupalLogin($limited_user);
    // Visit the Field UI field add page.
    $this->drupalGet('entity_test_string_id/structure/entity_test/fields/add-field');
    // Ensure field isn't shown for string IDs.
    $this->assertSession()->optionNotExists('edit-new-storage-type', 'reservation');
    // Ensure a core field type shown.
    $this->assertSession()->optionExists('edit-new-storage-type', 'boolean');

    // Create a bundle for entity_test_no_id.
    entity_test_create_bundle('entity_test', 'Entity Test', 'entity_test_no_id');
    $this->drupalLogin($this->drupalCreateUser([
      'administer entity_test_no_id fields',
    ]));
    // Visit the Field UI field add page.
    $this->drupalGet('entity_test_no_id/structure/entity_test/fields/add-field');
    // Ensure field isn't shown for empty IDs.
    $this->assertSession()->optionNotExists('edit-new-storage-type', 'reservation');
    // Ensure a core field type shown.
    $this->assertSession()->optionExists('edit-new-storage-type', 'boolean');
  }

}
