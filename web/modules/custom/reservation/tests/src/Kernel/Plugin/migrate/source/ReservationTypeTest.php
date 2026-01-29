<?php

namespace Drupal\Tests\reservation\Kernel\Plugin\migrate\source;

use Drupal\Tests\migrate\Kernel\MigrateSqlSourceTestBase;

/**
 * Tests the reservation type source plugin.
 *
 * @covers \Drupal\reservation\Plugin\migrate\source\ReservationType
 *
 * @group reservation
 */
class ReservationTypeTest extends MigrateSqlSourceTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'reservation', 'migrate_drupal'];

  /**
   * {@inheritdoc}
   */
  public function providerSource() {
    $node_type_rows = [
      [
        'type' => 'page',
        'name' => 'Page',
      ],
      [
        'type' => 'story',
        'name' => 'Story',
      ],
    ];
    $reservation_variable_rows = [
      [
        'name' => 'reservation_anonymous_page',
        'value' => serialize(0),
      ],
      [
        'name' => 'reservation_anonymous_story',
        'value' => serialize(1),
      ],
      [
        'name' => 'reservation_default_mode_page',
        'value' => serialize(0),
      ],
      [
        'name' => 'reservation_default_mode_story',
        'value' => serialize(1),
      ],
      [
        'name' => 'reservation_default_per_page_page',
        'value' => serialize('10'),
      ],
      [
        'name' => 'reservation_default_per_page_story',
        'value' => serialize('20'),
      ],
      [
        'name' => 'reservation_form_location_page',
        'value' => serialize(0),
      ],
      [
        'name' => 'reservation_form_location_story',
        'value' => serialize(1),
      ],
      [
        'name' => 'reservation_page',
        'value' => serialize('0'),
      ],
      [
        'name' => 'reservation_preview_page',
        'value' => serialize('0'),
      ],
      [
        'name' => 'reservation_preview_story',
        'value' => serialize('1'),
      ],
      [
        'name' => 'reservation_story',
        'value' => serialize('1'),
      ],
      [
        'name' => 'reservation_subject_field_page',
        'value' => serialize(0),
      ],
      [
        'name' => 'reservation_subject_field_story',
        'value' => serialize(1),
      ],
    ];

    return [
      'Node and reservation enabled, two node types' => [
        'source_data' => [
          'node_type' => $node_type_rows,
          'variable' => $reservation_variable_rows,
        ],
        'expected_data' => [
          [
            'type' => 'page',
            'name' => 'Page',
            'reservation' => 0,
            'reservation_default_mode' => 0,
            'reservation_default_per_page' => '10',
            'reservation_anonymous' => 0,
            'reservation_subject_field' => 0,
            'reservation_preview' => 0,
            'reservation_form_location' => 0,
          ],
          [
            'type' => 'story',
            'name' => 'Story',
            'reservation' => 1,
            'reservation_default_mode' => 1,
            'reservation_default_per_page' => '20',
            'reservation_anonymous' => 1,
            'reservation_subject_field' => 1,
            'reservation_preview' => 1,
            'reservation_form_location' => 1,
          ],
        ],
      ],
      'Node and reservation enabled, two node types, no reservation variables' => [
        'source_data' => [
          'node_type' => $node_type_rows,
          'variable' => [
            [
              'name' => 'css_js_query_string',
              'value' => serialize('foobar'),
            ],
          ],
        ],
        'expected_data' => [
          [
            'type' => 'page',
            'name' => 'Page',
            'reservation' => NULL,
            'reservation_default_mode' => NULL,
            'reservation_default_per_page' => NULL,
            'reservation_anonymous' => NULL,
            'reservation_subject_field' => NULL,
            'reservation_preview' => NULL,
            'reservation_form_location' => NULL,
          ],
          [
            'type' => 'story',
            'name' => 'Story',
            'reservation' => NULL,
            'reservation_default_mode' => NULL,
            'reservation_default_per_page' => NULL,
            'reservation_anonymous' => NULL,
            'reservation_subject_field' => NULL,
            'reservation_preview' => NULL,
            'reservation_form_location' => NULL,
          ],
        ],
      ],
    ];
  }

}
