<?php

namespace Drupal\Tests\reservation\Kernel\Migrate\d6;

use Drupal\reservation\Entity\ReservationType;
use Drupal\Tests\migrate_drupal\Kernel\d6\MigrateDrupal6TestBase;

/**
 * Tests the migration of reservation types from Drupal 6.
 *
 * @group reservation
 * @group migrate_drupal_6
 */
class MigrateReservationTypeTest extends MigrateDrupal6TestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['reservation'];

  /**
   * Asserts a reservation type entity.
   *
   * @param string $id
   *   The entity ID.
   * @param string $label
   *   The entity label.
   */
  protected function assertEntity($id, $label) {
    $entity = ReservationType::load($id);
    $this->assertInstanceOf(ReservationType::class, $entity);
    $this->assertSame($label, $entity->label());
    $this->assertSame('node', $entity->getTargetEntityTypeId());
  }

  /**
   * Tests the migrated reservation types.
   */
  public function testMigration() {
    $this->installConfig(['reservation']);
    $this->executeMigration('d6_reservation_type');

    $this->assertEntity('reservation_node_article', 'Article reservation');
    $this->assertEntity('reservation_node_company', 'Company reservation');
    $this->assertEntity('reservation_node_employee', 'Employee reservation');
    $this->assertEntity('reservation_node_event', 'Event reservation');
    $this->assertEntity('reservation_forum', 'Forum topic reservation');
    $this->assertEntity('reservation_node_page', 'Page reservation');
    $this->assertEntity('reservation_node_sponsor', 'Sponsor reservation');
    $this->assertEntity('reservation_node_story', 'Story reservation');
    $this->assertEntity('reservation_node_test_event', 'Migrate test event reservation');
    $this->assertEntity('reservation_node_test_page', 'Migrate test page reservation');
    $this->assertEntity('reservation_node_test_planet', 'Migrate test planet reservation');
    $this->assertEntity('reservation_node_test_story', 'Migrate test story reservation');
    $this->assertEntity('reservation_node_a_thirty_two_char', 'Test long name reservation');
  }

  /**
   * Tests reservation type migration without node or / and reservation on source.
   *
   * Usually, MigrateDumpAlterInterface::migrateDumpAlter() should be used when
   * the source fixture needs to be changed in a Migrate kernel test, but that
   * would end in three additional tests and an extra overhead in maintenance.
   *
   * @param string[] $disabled_source_modules
   *   List of the modules to disable in the source Drupal database.
   * @param string[][] $expected_messages
   *   List of the expected migration messages, keyed by the message type.
   *   Message type should be "status" "warning" or "error".
   *
   * @dataProvider providerTestNoReservationTypeMigration
   */
  public function testNoReservationTypeMigration(array $disabled_source_modules, array $expected_messages) {
    if (!empty($disabled_source_modules)) {
      $this->sourceDatabase->update('system')
        ->condition('name', $disabled_source_modules, 'IN')
        ->fields(['status' => 0])
        ->execute();
    }

    $this->startCollectingMessages();
    $this->executeMigration('d6_reservation_type');

    $expected_messages += [
      'status' => [],
      'warning' => [],
      'error' => [],
    ];
    $actual_messages = $this->migrateMessages + [
      'status' => [],
      'warning' => [],
      'error' => [],
    ];

    foreach ($expected_messages as $type => $expected_messages_by_type) {
      $this->assertEquals(count($expected_messages_by_type), count($actual_messages[$type]));
      // Cast the actual messages to string.
      $actual_messages_by_type = array_reduce($actual_messages[$type], function (array $carry, $actual_message) {
        $carry[] = (string) $actual_message;
        return $carry;
      }, []);
      $missing_expected_messages_by_type = array_diff($expected_messages_by_type, $actual_messages_by_type);
      $unexpected_messages_by_type = array_diff($actual_messages_by_type, $expected_messages_by_type);
      $this->assertEmpty($unexpected_messages_by_type, sprintf('No additional messages are present with type "%s". This expectation is wrong, because there are additional messages present: "%s"', $type, implode('", "', $unexpected_messages_by_type)));
      $this->assertEmpty($missing_expected_messages_by_type, sprintf('Every expected messages are present with type "%s". This expectation is wrong, because the following messages aren\'t present: "%s"', $type, implode('", "', $missing_expected_messages_by_type)));
    }

    $this->assertEmpty(ReservationType::loadMultiple());
  }

  /**
   * Provides test cases for ::testNoReservationTypeMigration().
   */
  public function providerTestNoReservationTypeMigration() {
    return [
      'Node module is disabled in source' => [
        'Disabled source modules' => ['node'],
        'Expected messages' => [
          'error' => [
            'Migration d6_reservation_type did not meet the requirements. The node module is not enabled in the source site. source_module_additional: node.',
          ],
        ],
      ],
      'Reservation module is disabled in source' => [
        'Disabled source modules' => ['reservation'],
        'Expected messages' => [
          'error' => [
            'Migration d6_reservation_type did not meet the requirements. The module reservation is not enabled in the source site. source_module: reservation.',
          ],
        ],
      ],
      'Node and reservation modules are disabled in source' => [
        'Disabled source modules' => ['reservation', 'node'],
        'Expected messages' => [
          'error' => [
            'Migration d6_reservation_type did not meet the requirements. The module reservation is not enabled in the source site. source_module: reservation.',
          ],
        ],
      ],
    ];
  }

}
