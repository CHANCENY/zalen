<?php

namespace Drupal\Tests\reservation\Kernel\Plugin\migrate\source\d7;

use Drupal\Tests\migrate\Kernel\MigrateSqlSourceTestBase;

/**
 * Tests D7 reservation source plugin.
 *
 * @covers \Drupal\reservation\Plugin\migrate\source\d7\Reservation
 * @group reservation
 */
class ReservationTest extends MigrateSqlSourceTestBase {

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
        'language' => 'und',
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
    $tests[0]['source_data']['field_config'] = [
      [
        'id' => '1',
        'translatable' => '0',
      ],
      [
        'id' => '2',
        'translatable' => '1',
      ],
    ];
    $tests[0]['source_data']['field_config_instance'] = [
      [
        'id' => '14',
        'field_id' => '1',
        'field_name' => 'reservation_body',
        'entity_type' => 'reservation',
        'bundle' => 'reservation_node_test_content_type',
        'data' => 'a:0:{}',
        'deleted' => '0',
      ],
      [
        'id' => '15',
        'field_id' => '2',
        'field_name' => 'subject_field',
        'entity_type' => 'reservation',
        'bundle' => 'reservation_node_test_content_type',
        'data' => 'a:0:{}',
        'deleted' => '0',
      ],
    ];
    $tests[0]['source_data']['field_data_reservation_body'] = [
      [
        'entity_type' => 'reservation',
        'bundle' => 'reservation_node_test_content_type',
        'deleted' => '0',
        'entity_id' => '1',
        'revision_id' => '1',
        'language' => 'und',
        'delta' => '0',
        'reservation_body_value' => 'This is a reservation',
        'reservation_body_format' => 'filtered_html',
      ],
    ];
    $tests[0]['source_data']['field_data_subject_field'] = [
      [
        'entity_type' => 'reservation',
        'bundle' => 'reservation_node_test_content_type',
        'deleted' => '0',
        'entity_id' => '1',
        'revision_id' => '1',
        'language' => 'und',
        'delta' => '0',
        'subject_field_value' => 'A reservation (subject_field)',
        'subject_field_format' => NULL,
      ],
    ];
    $tests[0]['source_data']['system'] = [
      [
        'name' => 'title',
        'type' => 'module',
        'status' => 1,
      ],
    ];

    // The expected results.
    $tests[0]['expected_data'] = [
      [
        'cid' => '1',
        'pid' => '0',
        'nid' => '1',
        'uid' => '1',
        'subject' => 'A reservation (subject_field)',
        'hostname' => '::1',
        'created' => '1421727536',
        'changed' => '1421727536',
        'status' => '1',
        'thread' => '01/',
        'name' => 'admin',
        'mail' => '',
        'homepage' => '',
        'language' => 'und',
        'reservation_body' => [
          [
            'value' => 'This is a reservation',
            'format' => 'filtered_html',
          ],
        ],
      ],
    ];

    return $tests;
  }

}
