<?php

namespace Drupal\Tests\reservation\Kernel\Plugin\migrate\source\d7;

use Drupal\Tests\migrate\Kernel\MigrateSqlSourceTestBase;

/**
 * Tests D7 reservation entity translation source plugin.
 *
 * @covers \Drupal\reservation\Plugin\migrate\source\d7\ReservationEntityTranslation
 * @group reservation
 */
class ReservationEntityTranslationTest extends MigrateSqlSourceTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['reservation', 'migrate_drupal'];

  /**
   * {@inheritdoc}
   */
  public function providerSource() {
    $tests = [];

    // The source data.
    $tests[0]['source_data']['reservation'] = [
      [
        'cid' => '1',
        'pid' => '0',
        'nid' => '1',
        'uid' => '1',
        'subject' => 'A reservation',
        'hostname' => '::1',
        'created' => '1421727536',
        'changed' => '1421727536',
        'status' => '1',
        'thread' => '01/',
        'name' => 'admin',
        'mail' => '',
        'homepage' => '',
        'language' => 'en',
      ],
    ];
    $tests[0]['source_data']['entity_translation'] = [
      [
        'entity_type' => 'reservation',
        'entity_id' => 1,
        'revision_id' => 1,
        'language' => 'en',
        'source' => '',
        'uid' => 1,
        'status' => 1,
        'translate' => 0,
        'created' => '1421727536',
        'changed' => '1421727536',
      ],
      [
        'entity_type' => 'reservation',
        'entity_id' => 1,
        'revision_id' => 1,
        'language' => 'fr',
        'source' => 'en',
        'uid' => 1,
        'status' => 0,
        'translate' => 0,
        'created' => 1531343508,
        'changed' => 1531343508,
      ],
      [
        'entity_type' => 'reservation',
        'entity_id' => 1,
        'revision_id' => 1,
        'language' => 'es',
        'source' => 'en',
        'uid' => 2,
        'status' => 1,
        'translate' => 1,
        'created' => 1531343528,
        'changed' => 1531343528,
      ],
    ];
    $tests[0]['source_data']['field_config'] = [
      [
        'id' => 1,
        'field_name' => 'field_test',
        'type' => 'text',
        'module' => 'text',
        'active' => 1,
        'storage_type' => 'field_sql_storage',
        'storage_module' => 'field_sql_storage',
        'storage_active' => 1,
        'locked' => 1,
        'data' => 'a:0:{}',
        'cardinality' => 1,
        'translatable' => 1,
        'deleted' => 0,
      ],
      [
        'id' => 2,
        'field_name' => 'subject_field',
        'type' => 'text',
        'module' => 'text',
        'active' => 1,
        'storage_type' => 'field_sql_storage',
        'storage_module' => 'field_sql_storage',
        'storage_active' => 1,
        'locked' => 1,
        'data' => 'a:0:{}',
        'cardinality' => 1,
        'translatable' => 1,
        'deleted' => 0,
      ],
    ];
    $tests[0]['source_data']['field_config_instance'] = [
      [
        'id' => '1',
        'field_id' => '1',
        'field_name' => 'field_test',
        'entity_type' => 'reservation',
        'bundle' => 'reservation_node_test_content_type',
        'data' => 'a:0:{}',
        'deleted' => '0',
      ],
      [
        'id' => '2',
        'field_id' => '2',
        'field_name' => 'subject_field',
        'entity_type' => 'reservation',
        'bundle' => 'reservation_node_test_content_type',
        'data' => 'a:0:{}',
        'deleted' => '0',
      ],
    ];
    $tests[0]['source_data']['field_data_field_test'] = [
      [
        'entity_type' => 'reservation',
        'bundle' => 'reservation_node_test_content_type',
        'deleted' => '0',
        'entity_id' => '1',
        'revision_id' => '1',
        'language' => 'en',
        'delta' => '0',
        'field_test_value' => 'This is an English reservation',
        'field_test_format' => NULL,
      ],
      [
        'entity_type' => 'reservation',
        'bundle' => 'reservation_node_test_content_type',
        'deleted' => '0',
        'entity_id' => '1',
        'revision_id' => '1',
        'language' => 'fr',
        'delta' => '0',
        'field_test_value' => 'This is a French reservation',
        'field_test_format' => NULL,
      ],
      [
        'entity_type' => 'reservation',
        'bundle' => 'reservation_node_test_content_type',
        'deleted' => '0',
        'entity_id' => '1',
        'revision_id' => '1',
        'language' => 'es',
        'delta' => '0',
        'field_test_value' => 'This is a Spanish reservation',
        'field_test_format' => NULL,
      ],
    ];
    $tests[0]['source_data']['field_data_subject_field'] = [
      [
        'entity_type' => 'reservation',
        'bundle' => 'reservation_node_test_content_type',
        'deleted' => '0',
        'entity_id' => '1',
        'revision_id' => '1',
        'language' => 'en',
        'delta' => '0',
        'subject_field_value' => 'Reservation subject in English',
        'subject_field_format' => NULL,
      ],
      [
        'entity_type' => 'reservation',
        'bundle' => 'reservation_node_test_content_type',
        'deleted' => '0',
        'entity_id' => '1',
        'revision_id' => '1',
        'language' => 'fr',
        'delta' => '0',
        'subject_field_value' => 'Reservation subject in French',
        'subject_field_format' => NULL,
      ],
      [
        'entity_type' => 'reservation',
        'bundle' => 'reservation_node_test_content_type',
        'deleted' => '0',
        'entity_id' => '1',
        'revision_id' => '1',
        'language' => 'es',
        'delta' => '0',
        'subject_field_value' => 'Reservation subject in Spanish',
        'subject_field_format' => NULL,
      ],
    ];
    $tests[0]['source_data']['node'] = [
      [
        'nid' => '1',
        'vid' => '1',
        'type' => 'test_content_type',
        'language' => 'en',
        'title' => 'A Node',
        'uid' => '1',
        'status' => '1',
        'created' => '1421727515',
        'changed' => '1421727515',
        'reservation' => '2',
        'promote' => '1',
        'sticky' => '0',
        'tnid' => '0',
        'translate' => '0',
      ],
    ];

    // The expected results.
    $tests[0]['expected_data'] = [
      [
        'subject' => 'A reservation',
        'entity_type' => 'reservation',
        'entity_id' => '1',
        'revision_id' => '1',
        'language' => 'fr',
        'source' => 'en',
        'uid' => '1',
        'status' => '0',
        'translate' => '0',
        'created' => '1531343508',
        'changed' => '1531343508',
        'field_test' => [
          [
            'value' => 'This is a French reservation',
            'format' => NULL,
          ],
        ],
        'subject_field' => [
          [
            'value' => 'Reservation subject in French',
            'format' => NULL,
          ],
        ],
      ],
      [
        'subject' => 'A reservation',
        'entity_type' => 'reservation',
        'entity_id' => '1',
        'revision_id' => '1',
        'language' => 'es',
        'source' => 'en',
        'uid' => '2',
        'status' => '1',
        'translate' => '1',
        'created' => '1531343528',
        'changed' => '1531343528',
        'field_test' => [
          [
            'value' => 'This is a Spanish reservation',
            'format' => NULL,
          ],
        ],
        'subject_field' => [
          [
            'value' => 'Reservation subject in Spanish',
            'format' => NULL,
          ],
        ],
      ],
    ];

    return $tests;
  }

}
