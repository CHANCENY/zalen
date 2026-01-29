<?php

namespace Drupal\Tests\reservation\Kernel;

use Drupal\reservation\ReservationInterface;
use Drupal\reservation\Entity\Reservation;
use Drupal\reservation\Entity\ReservationType;
use Drupal\reservation\Tests\ReservationTestTrait;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\Tests\Traits\Core\GeneratePermutationsTrait;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use Drupal\Tests\BrowserTestBase;


/**
 * Tests reservation field level access.
 *
 * @group reservation
 * @group Access
 */
class ReservationFieldAccessTest extends EntityKernelTestBase {

  use ReservationTestTrait;
  use GeneratePermutationsTrait;

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = ['reservation', 'entity_test', 'user'];

  /**
   * Fields that only users with administer reservations permissions can change.
   *
   * @var array
   */
  protected $administrativeFields = [
    'uid',
    'status',
    'created',
  ];

  /**
   * These fields are automatically managed and can not be changed by any user.
   *
   * @var array
   */
  protected $readOnlyFields = [
    'changed',
    'hostname',
    'cid',
    'thread',
  ];

  /**
   * These fields can be edited on create only.
   *
   * @var array
   */
  protected $createOnlyFields = [
    'uuid',
    'pid',
    'reservation_type',
    'entity_id',
    'entity_type',
    'field_name',
  ];

  /**
   * These fields can only be edited by the admin or anonymous users if allowed.
   *
   * @var array
   */
  protected $contactFields = [
    'name',
    'mail',
    'homepage',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['user', 'reservation']);
    $this->installSchema('reservation', ['reservation_entity_statistics']);
  }

  /**
   * Test permissions on reservation fields.
   */
  public function testAccessToAdministrativeFields() {
    // Create a reservation type.
    $reservation_type = ReservationType::create([
      'id' => 'reservation',
      'label' => 'Default reservations',
      'description' => 'Default reservation field',
      'target_entity_type_id' => 'entity_test',
    ]);
    $reservation_type->save();

    // Create a reservation against a test entity.
    $host = EntityTest::create();
    $host->save();

    // An administrator user. No user exists yet, ensure that the first user
    // does not have UID 1.
    $reservation_admin_user = $this->createUser(['uid' => 2, 'name' => 'admin'], [
      'administer reservations',
      'access reservations',
    ]);

    // Two reservation enabled users, one with edit access.
    $reservation_enabled_user = $this->createUser(['name' => 'enabled'], [
      'post reservations',
      'skip reservation approval',
      'edit own reservations',
      'access reservations',
    ]);
    $reservation_no_edit_user = $this->createUser(['name' => 'no edit'], [
      'post reservations',
      'skip reservation approval',
      'access reservations',
    ]);

    // An unprivileged user.
    $reservation_disabled_user = $this->createUser(['name' => 'disabled'], ['access content']);

    $role = Role::load(RoleInterface::ANONYMOUS_ID);
    $role->grantPermission('post reservations')
      ->save();

    $anonymous_user = new AnonymousUserSession();

    // Add two fields.
    $this->addDefaultReservationField('entity_test', 'entity_test', 'reservation');
    $this->addDefaultReservationField('entity_test', 'entity_test', 'reservation_other');

    // Change the second field's anonymous contact setting.
    $instance = FieldConfig::loadByName('entity_test', 'entity_test', 'reservation_other');
    // Default is 'May not contact', for this field - they may contact.
    $instance->setSetting('anonymous', ReservationInterface::ANONYMOUS_MAY_CONTACT);
    $instance->save();

    // Create three "Reservations". One is owned by our edit-enabled user.
    $reservation1 = Reservation::create([
      'entity_type' => 'entity_test',
      'name' => 'Tony',
      'hostname' => 'magic.example.com',
      'mail' => 'tonythemagicalpony@example.com',
      'subject' => 'Bruce the Mesopotamian moose',
      'entity_id' => $host->id(),
      'reservation_type' => 'reservation',
      'field_name' => 'reservation',
      'pid' => 0,
      'uid' => 0,
      'status' => 1,
    ]);
    $reservation1->save();
    $reservation2 = Reservation::create([
      'entity_type' => 'entity_test',
      'hostname' => 'magic.example.com',
      'subject' => 'Brian the messed up lion',
      'entity_id' => $host->id(),
      'reservation_type' => 'reservation',
      'field_name' => 'reservation',
      'status' => 1,
      'pid' => 0,
      'uid' => $reservation_enabled_user->id(),
    ]);
    $reservation2->save();
    $reservation3 = Reservation::create([
      'entity_type' => 'entity_test',
      'hostname' => 'magic.example.com',
      // Unpublished.
      'status' => 0,
      'subject' => 'Gail the minky whale',
      'entity_id' => $host->id(),
      'reservation_type' => 'reservation',
      'field_name' => 'reservation_other',
      'pid' => $reservation2->id(),
      'uid' => $reservation_no_edit_user->id(),
    ]);
    $reservation3->save();
    // Note we intentionally don't save this reservation so it remains 'new'.
    $reservation4 = Reservation::create([
      'entity_type' => 'entity_test',
      'hostname' => 'magic.example.com',
      // Unpublished.
      'status' => 0,
      'subject' => 'Daniel the Cocker-Spaniel',
      'entity_id' => $host->id(),
      'reservation_type' => 'reservation',
      'field_name' => 'reservation_other',
      'pid' => 0,
      'uid' => $anonymous_user->id(),
    ]);

    // Generate permutations.
    $combinations = [
      'reservation' => [$reservation1, $reservation2, $reservation3, $reservation4],
      'user' => [$reservation_admin_user, $reservation_enabled_user, $reservation_no_edit_user, $reservation_disabled_user, $anonymous_user],
    ];
    $permutations = $this->generatePermutations($combinations);

    // Check access to administrative fields.
    foreach ($this->administrativeFields as $field) {
      foreach ($permutations as $set) {
        $may_view = $set['reservation']->{$field}->access('view', $set['user']);
        $may_update = $set['reservation']->{$field}->access('edit', $set['user']);
        $this->assertTrue($may_view, new FormattableMarkup('User @user can view field @field on reservation @reservation', [
          '@user' => $set['user']->getAccountName(),
          '@reservation' => $set['reservation']->getSubject(),
          '@field' => $field,
        ]));
        $this->assertEquals($may_update, $set['user']->hasPermission('administer reservations'), new FormattableMarkup('User @user @state update field @field on reservation @reservation', [
          '@user' => $set['user']->getAccountName(),
          '@state' => $may_update ? 'can' : 'cannot',
          '@reservation' => $set['reservation']->getSubject(),
          '@field' => $field,
        ]));
      }
    }

    // Check access to normal field.
    foreach ($permutations as $set) {
      $may_update = $set['reservation']->access('update', $set['user']) && $set['reservation']->subject->access('edit', $set['user']);
      $this->assertEquals($may_update, $set['user']->hasPermission('administer reservations') || ($set['user']->hasPermission('edit own reservations') && $set['user']->id() == $set['reservation']->getOwnerId()), new FormattableMarkup('User @user @state update field subject on reservation @reservation', [
        '@user' => $set['user']->getAccountName(),
        '@state' => $may_update ? 'can' : 'cannot',
        '@reservation' => $set['reservation']->getSubject(),
      ]));
    }

    // Check read-only fields.
    foreach ($this->readOnlyFields as $field) {
      // Check view operation.
      foreach ($permutations as $set) {
        $may_view = $set['reservation']->{$field}->access('view', $set['user']);
        $may_update = $set['reservation']->{$field}->access('edit', $set['user']);
        // Nobody has access to view the hostname field.
        if ($field === 'hostname') {
          $view_access = FALSE;
          $state = 'cannot';
        }
        else {
          $view_access = TRUE;
          $state = 'can';
        }
        $this->assertEquals($may_view, $view_access, new FormattableMarkup('User @user @state view field @field on reservation @reservation', [
          '@user' => $set['user']->getAccountName(),
          '@reservation' => $set['reservation']->getSubject(),
          '@field' => $field,
          '@state' => $state,
        ]));
        $this->assertFalse($may_update, new FormattableMarkup('User @user @state update field @field on reservation @reservation', [
          '@user' => $set['user']->getAccountName(),
          '@state' => $may_update ? 'can' : 'cannot',
          '@reservation' => $set['reservation']->getSubject(),
          '@field' => $field,
        ]));
      }
    }

    // Check create-only fields.
    foreach ($this->createOnlyFields as $field) {
      // Check view operation.
      foreach ($permutations as $set) {
        $may_view = $set['reservation']->{$field}->access('view', $set['user']);
        $may_update = $set['reservation']->{$field}->access('edit', $set['user']);
        $this->assertTrue($may_view, new FormattableMarkup('User @user can view field @field on reservation @reservation', [
          '@user' => $set['user']->getAccountName(),
          '@reservation' => $set['reservation']->getSubject(),
          '@field' => $field,
        ]));
        $this->assertEquals($may_update, $set['user']->hasPermission('post reservations') && $set['reservation']->isNew(), new FormattableMarkup('User @user @state update field @field on reservation @reservation', [
          '@user' => $set['user']->getAccountName(),
          '@state' => $may_update ? 'can' : 'cannot',
          '@reservation' => $set['reservation']->getSubject(),
          '@field' => $field,
        ]));
      }
    }

    // Check contact fields.
    foreach ($this->contactFields as $field) {
      // Check view operation.
      foreach ($permutations as $set) {
        $may_update = $set['reservation']->{$field}->access('edit', $set['user']);
        // To edit the 'mail' or 'name' field, either the user has the
        // "administer reservations" permissions or the user is anonymous and
        // adding a new reservation using a field that allows contact details.
        $this->assertEquals($may_update, $set['user']->hasPermission('administer reservations') || (
            $set['user']->isAnonymous() &&
            $set['reservation']->isNew() &&
            $set['user']->hasPermission('post reservations') &&
            $set['reservation']->getFieldName() == 'reservation_other'
          ), new FormattableMarkup('User @user @state update field @field on reservation @reservation', [
          '@user' => $set['user']->getAccountName(),
          '@state' => $may_update ? 'can' : 'cannot',
          '@reservation' => $set['reservation']->getSubject(),
          '@field' => $field,
        ]));
      }
    }
    foreach ($permutations as $set) {
      // Check no view-access to mail field for other than admin.
      $may_view = $set['reservation']->mail->access('view', $set['user']);
      $this->assertEquals($may_view, $set['user']->hasPermission('administer reservations'));
    }
  }

}
