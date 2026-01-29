<?php

namespace Drupal\Tests\reservation\Kernel;

use Drupal\reservation\Tests\ReservationTestTrait;
use Drupal\Core\Extension\ModuleUninstallValidatorException;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;

/**
 * Tests reservation module uninstall.
 *
 * @group reservation
 */
class ReservationUninstallTest extends KernelTestBase {

  use ReservationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'reservation',
    'field',
    'node',
    'system',
    'text',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('reservation');
    $this->installConfig(['reservation']);
    $this->installSchema('user', ['users_data']);

    NodeType::create(['type' => 'article'])->save();

    // Create reservation field on article so that it adds 'reservation_body' field.
    FieldStorageConfig::create([
      'type' => 'text_long',
      'entity_type' => 'reservation',
      'field_name' => 'reservation',
    ])->save();
    $this->addDefaultReservationField('node', 'article');
  }

  /**
   * Tests if reservation module uninstall fails if the field exists.
   */
  public function testReservationUninstallWithField() {
    // Ensure that the field exists before uninstalling.
    $field_storage = FieldStorageConfig::loadByName('reservation', 'reservation_body');
    $this->assertNotNull($field_storage);

    // Uninstall the reservation module which should trigger an exception.
    $this->expectException(ModuleUninstallValidatorException::class);
    $this->expectExceptionMessage('The following reasons prevent the modules from being uninstalled: The <em class="placeholder">Reservations</em> field type is used in the following field: node.reservation');
    $this->container->get('module_installer')->uninstall(['reservation']);
  }

  /**
   * Tests if uninstallation succeeds if the field has been deleted beforehand.
   */
  public function testReservationUninstallWithoutField() {
    // Tests if uninstall succeeds if the field has been deleted beforehand.
    // Manually delete the reservation_body field before module uninstall.
    FieldStorageConfig::loadByName('reservation', 'reservation_body')->delete();

    // Check that the field is now deleted.
    $field_storage = FieldStorageConfig::loadByName('reservation', 'reservation_body');
    $this->assertNull($field_storage);

    // Manually delete the reservation field on the node before module uninstall.
    $field_storage = FieldStorageConfig::loadByName('node', 'reservation');
    $this->assertNotNull($field_storage);
    $field_storage->delete();

    // Check that the field is now deleted.
    $field_storage = FieldStorageConfig::loadByName('node', 'reservation');
    $this->assertNull($field_storage);

    field_purge_batch(10);
    // Ensure that uninstall succeeds even if the field has already been deleted
    // manually beforehand.
    $this->container->get('module_installer')->uninstall(['reservation']);
  }

}
