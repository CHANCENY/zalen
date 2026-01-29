<?php

namespace Drupal\Tests\reservation\Kernel;

use Drupal\reservation\ReservationInterface;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests reservation validation constraints.
 *
 * @group reservation
 */
class ReservationValidationTest extends EntityKernelTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = ['reservation', 'node'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('reservation', ['reservation_entity_statistics']);
  }

  /**
   * Tests the reservation validation constraints.
   */
  public function testValidation() {
    // Add a user.
    $user = User::create(['name' => 'test', 'status' => TRUE]);
    $user->save();

    // Add reservation type.
    $this->entityTypeManager->getStorage('reservation_type')->create([
      'id' => 'reservation',
      'label' => 'reservation',
      'target_entity_type_id' => 'node',
    ])->save();

    // Add reservation field to content.
    $this->entityTypeManager->getStorage('field_storage_config')->create([
      'entity_type' => 'node',
      'field_name' => 'reservation',
      'type' => 'reservation',
      'settings' => [
        'reservation_type' => 'reservation',
      ],
    ])->save();

    // Create a page node type.
    $this->entityTypeManager->getStorage('node_type')->create([
      'type' => 'page',
      'name' => 'page',
    ])->save();

    // Add reservation field to page content.
    /** @var \Drupal\field\FieldConfigInterface $field */
    $field = $this->entityTypeManager->getStorage('field_config')->create([
      'field_name' => 'reservation',
      'entity_type' => 'node',
      'bundle' => 'page',
      'label' => 'Reservation settings',
    ]);
    $field->save();

    $node = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'page',
      'title' => 'test',
    ]);
    $node->save();

    $reservation = $this->entityTypeManager->getStorage('reservation')->create([
      'entity_id' => $node->id(),
      'entity_type' => 'node',
      'field_name' => 'reservation',
      'reservation_body' => $this->randomMachineName(),
    ]);

    $violations = $reservation->validate();
    $this->assertCount(0, $violations, 'No violations when validating a default reservation.');

    $reservation->set('subject', $this->randomString(65));
    $this->assertLengthViolation($reservation, 'subject', 64);

    // Make the subject valid.
    $reservation->set('subject', $this->randomString());
    $reservation->set('name', $this->randomString(61));
    $this->assertLengthViolation($reservation, 'name', 60);

    // Validate a name collision between an anonymous reservation author name and an
    // existing user account name.
    $reservation->set('name', 'test');
    $reservation->set('uid', 0);
    $violations = $reservation->validate();
    $this->assertCount(1, $violations, "Violation found on author name collision");
    $this->assertEquals("name", $violations[0]->getPropertyPath());
    $this->assertEquals(t('The name you used (%name) belongs to a registered user.', ['%name' => 'test']), $violations[0]->getMessage());

    // Make the name valid.
    $reservation->set('name', 'valid unused name');
    $reservation->set('mail', 'invalid');
    $violations = $reservation->validate();
    $this->assertCount(1, $violations, 'Violation found when email is invalid');
    $this->assertEquals('mail.0.value', $violations[0]->getPropertyPath());
    $this->assertEquals(t('This value is not a valid email address.'), $violations[0]->getMessage());

    $reservation->set('mail', NULL);
    $reservation->set('homepage', 'http://example.com/' . $this->randomMachineName(237));
    $this->assertLengthViolation($reservation, 'homepage', 255);

    $reservation->set('homepage', 'invalid');
    $violations = $reservation->validate();
    $this->assertCount(1, $violations, 'Violation found when homepage is invalid');
    $this->assertEquals('homepage.0.value', $violations[0]->getPropertyPath());

    // @todo This message should be improved in
    //   https://www.drupal.org/node/2012690.
    $this->assertEquals(t('This value should be of the correct primitive type.'), $violations[0]->getMessage());

    $reservation->set('homepage', NULL);
    $reservation->set('hostname', $this->randomString(129));
    $this->assertLengthViolation($reservation, 'hostname', 128);

    $reservation->set('hostname', NULL);
    $reservation->set('thread', $this->randomString(256));
    $this->assertLengthViolation($reservation, 'thread', 255);

    $reservation->set('thread', NULL);

    // Force anonymous users to enter contact details.
    $field->setSetting('anonymous', ReservationInterface::ANONYMOUS_MUST_CONTACT);
    $field->save();
    // Reset the node entity.
    \Drupal::entityTypeManager()->getStorage('node')->resetCache([$node->id()]);
    $node = Node::load($node->id());
    // Create a new reservation with the new field.
    $reservation = $this->entityTypeManager->getStorage('reservation')->create([
      'entity_id' => $node->id(),
      'entity_type' => 'node',
      'field_name' => 'reservation',
      'reservation_body' => $this->randomMachineName(),
      'uid' => 0,
      'name' => '',
    ]);
    $violations = $reservation->validate();
    $this->assertCount(1, $violations, 'Violation found when name is required, but empty and UID is anonymous.');
    $this->assertEquals('name', $violations[0]->getPropertyPath());
    $this->assertEquals(t('You have to specify a valid author.'), $violations[0]->getMessage());

    // Test creating a default reservation with a given user id works.
    $reservation = $this->entityTypeManager->getStorage('reservation')->create([
      'entity_id' => $node->id(),
      'entity_type' => 'node',
      'field_name' => 'reservation',
      'reservation_body' => $this->randomMachineName(),
      'uid' => $user->id(),
    ]);
    $violations = $reservation->validate();
    $this->assertCount(0, $violations, 'No violations when validating a default reservation with an author.');

    // Test specifying a wrong author name does not work.
    $reservation = $this->entityTypeManager->getStorage('reservation')->create([
      'entity_id' => $node->id(),
      'entity_type' => 'node',
      'field_name' => 'reservation',
      'reservation_body' => $this->randomMachineName(),
      'uid' => $user->id(),
      'name' => 'not-test',
    ]);
    $violations = $reservation->validate();
    $this->assertCount(1, $violations, 'Violation found when author name and reservation author do not match.');
    $this->assertEquals('name', $violations[0]->getPropertyPath());
    $this->assertEquals(t('The specified author name does not match the reservation author.'), $violations[0]->getMessage());
  }

  /**
   * Verifies that a length violation exists for the given field.
   *
   * @param \Drupal\reservation\ReservationInterface $reservation
   *   The reservation object to validate.
   * @param string $field_name
   *   The field that violates the maximum length.
   * @param int $length
   *   Number of characters that was exceeded.
   */
  protected function assertLengthViolation(ReservationInterface $reservation, $field_name, $length) {
    $violations = $reservation->validate();
    $this->assertCount(1, $violations, "Violation found when $field_name is too long.");
    $this->assertEquals("{$field_name}.0.value", $violations[0]->getPropertyPath());
    $field_label = $reservation->get($field_name)->getFieldDefinition()->getLabel();
    $this->assertEquals(t('%name: may not be longer than @max characters.', ['%name' => $field_label, '@max' => $length]), $violations[0]->getMessage());
  }

}
