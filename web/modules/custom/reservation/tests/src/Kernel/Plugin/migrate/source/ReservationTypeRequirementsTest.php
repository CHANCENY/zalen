<?php

namespace Drupal\Tests\reservation\Kernel\Plugin\migrate\source;

use Drupal\migrate\Exception\RequirementsException;
use Drupal\Tests\migrate_drupal\Kernel\d7\MigrateDrupal7TestBase;

/**
 * Tests check requirements for reservation type source plugin.
 *
 * @group reservation
 */
class ReservationTypeRequirementsTest extends MigrateDrupal7TestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['reservation'];

  /**
   * Tests thrown exceptions when node or reservation aren't enabled on source.
   *
   * @param string[] $disabled_source_modules
   *   List of the modules to disable in the source Drupal database.
   * @param string $exception_message
   *   The expected message of the RequirementsException.
   * @param string $migration_plugin_id
   *   The plugin ID of the reservation type migration to test.
   *
   * @dataProvider providerTestCheckReservationTypeRequirements
   */
  public function testCheckReservationTypeRequirements(array $disabled_source_modules, string $exception_message, string $migration_plugin_id) {
    if (!empty($disabled_source_modules)) {
      $this->sourceDatabase->update('system')
        ->condition('name', $disabled_source_modules, 'IN')
        ->fields(['status' => 0])
        ->execute();
    }

    $this->expectException(RequirementsException::class);
    $this->expectExceptionMessage($exception_message);
    $this->getMigration($migration_plugin_id)
      ->getSourcePlugin()
      ->checkRequirements();
  }

  /**
   * Test cases for ::testCheckReservationTypeRequirements().
   */
  public function providerTestCheckReservationTypeRequirements() {
    return [
      'D6 reservation is disabled on source' => [
        'Disabled source modules' => ['reservation'],
        'RequirementsException message' => 'The module reservation is not enabled in the source site.',
        'migration' => 'd6_reservation_type',
      ],
      'D6 node is disabled on source' => [
        'Disabled source modules' => ['node'],
        'RequirementsException message' => 'The node module is not enabled in the source site.',
        'migration' => 'd6_reservation_type',
      ],
      'D6 reservation and node are disabled on source' => [
        'Disabled source modules' => ['reservation', 'node'],
        'RequirementsException message' => 'The module reservation is not enabled in the source site.',
        'migration' => 'd6_reservation_type',
      ],
      'D7 reservation is disabled on source' => [
        'Disabled source modules' => ['reservation'],
        'RequirementsException message' => 'The module reservation is not enabled in the source site.',
        'migration' => 'd7_reservation_type',
      ],
      'D7 node is disabled on source' => [
        'Disabled source modules' => ['node'],
        'RequirementsException message' => 'The node module is not enabled in the source site.',
        'migration' => 'd7_reservation_type',
      ],
      'D7 reservation and node are disabled on source' => [
        'Disabled source modules' => ['reservation', 'node'],
        'RequirementsException message' => 'The module reservation is not enabled in the source site.',
        'migration' => 'd7_reservation_type',
      ],
    ];
  }

}
