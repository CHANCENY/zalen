<?php

namespace Drupal\Tests\reservation\Kernel\Plugin\migrate\source\d6;

use Drupal\Tests\migrate\Kernel\MigrateSqlSourceTestBase;

/**
 * Tests D6 reservation source plugin.
 *
 * @covers \Drupal\reservation\Plugin\migrate\source\d6\Reservation
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
    $tests[0]['source_data']['reservations'] = [
      [
        'cid' => 1,
        'pid' => 0,
        'nid' => 2,
        'uid' => 3,
        'subject' => 'subject value 1',
        'reservation' => 'reservation value 1',
        'hostname' => 'hostname value 1',
        'timestamp' => 1382255613,
        'status' => 0,
        'thread' => '',
        'name' => '',
        'mail' => '',
        'homepage' => '',
        'format' => 'testformat1',
        'type' => 'story',
      ],
      [
        'cid' => 2,
        'pid' => 1,
        'nid' => 3,
        'uid' => 4,
        'subject' => 'subject value 2',
        'reservation' => 'reservation value 2',
        'hostname' => 'hostname value 2',
        'timestamp' => 1382255662,
        'status' => 0,
        'thread' => '',
        'name' => '',
        'mail' => '',
        'homepage' => '',
        'format' => 'testformat2',
        'type' => 'page',
      ],
    ];

    $tests[0]['source_data']['node'] = [
      [
        'nid' => 2,
        'type' => 'story',
        'language' => 'en',
      ],
      [
        'nid' => 3,
        'type' => 'page',
        'language' => 'fr',
      ],
    ];

    // The expected results.
    $tests[0]['expected_data'] = [
      [
        'cid' => 1,
        'pid' => 0,
        'nid' => 2,
        'uid' => 3,
        'subject' => 'subject value 1',
        'reservation' => 'reservation value 1',
        'hostname' => 'hostname value 1',
        'timestamp' => 1382255613,
        'status' => 1,
        'thread' => '',
        'name' => '',
        'mail' => '',
        'homepage' => '',
        'format' => 'testformat1',
        'type' => 'story',
        'language' => 'en',
      ],
      [
        'cid' => 2,
        'pid' => 1,
        'nid' => 3,
        'uid' => 4,
        'subject' => 'subject value 2',
        'reservation' => 'reservation value 2',
        'hostname' => 'hostname value 2',
        'timestamp' => 1382255662,
        'status' => 1,
        'thread' => '',
        'name' => '',
        'mail' => '',
        'homepage' => '',
        'format' => 'testformat2',
        'type' => 'page',
        'language' => 'fr',
      ],
    ];

    return $tests;
  }

}
