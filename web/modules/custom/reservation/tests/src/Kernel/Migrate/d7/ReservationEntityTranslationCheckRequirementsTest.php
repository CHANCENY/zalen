<?php

namespace Drupal\Tests\reservation\Kernel\Migrate\d7;

use Drupal\Tests\migrate_drupal\Kernel\d7\MigrateDrupal7TestBase;
use Drupal\migrate\Exception\RequirementsException;

/**
 * Tests check requirements for reservation entity translation source plugin.
 *
 * @group reservation
 */
class ReservationEntityTranslationCheckRequirementsTest extends MigrateDrupal7TestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_translation',
    'reservation',
    'language',
  ];

  /**
   * Tests exception thrown when the given module is not enabled in the source.
   *
   * @dataProvider providerTestCheckRequirements
   */
  public function testCheckRequirements($module) {
    // Disable the module in the source site.
    $this->sourceDatabase->update('system')
      ->condition('name', $module)
      ->fields([
        'status' => '0',
      ])
      ->execute();
    $this->expectException(RequirementsException::class);
    $this->expectExceptionMessage("The module $module is not enabled in the source site");
    $this->getMigration('d7_reservation_entity_translation')
      ->getSourcePlugin()
      ->checkRequirements();
  }

  /**
   * Provides data for testCheckRequirements.
   *
   * @return string[][]
   */
  public function providerTestCheckRequirements() {
    return [
      ['reservation'],
      ['node'],
    ];
  }

}
