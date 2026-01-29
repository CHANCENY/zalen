<?php

namespace Drupal\Tests\reservation\Kernel\Views;

use Drupal\reservation\Entity\Reservation;
use Drupal\reservation\Tests\ReservationTestTrait;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\views\Tests\ViewResultAssertionTrait;
use Drupal\views\Tests\ViewTestData;
use Drupal\views\Views;

/**
 * Tests the user posted or reservationed filter and argument handlers.
 *
 * @group reservation
 */
class FilterAndArgumentUserUidTest extends KernelTestBase {

  use ReservationTestTrait;
  use NodeCreationTrait;
  use UserCreationTrait;
  use ViewResultAssertionTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'reservation',
    'reservation_test_views',
    'field',
    'filter',
    'node',
    'system',
    'text',
    'user',
    'views',
  ];

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_reservation_user_uid'];

  /**
   * Tests the user posted or reservationed filter and argument handlers.
   */
  public function testHandlers() {
    $this->installEntitySchema('user');
    $this->installSchema('system', ['sequences']);
    $this->installEntitySchema('node');
    $this->installEntitySchema('reservation');
    $this->installSchema('reservation', ['reservation_entity_statistics']);
    $this->installConfig(['filter']);

    NodeType::create(['type' => 'page'])->save();

    FieldStorageConfig::create([
      'type' => 'text_long',
      'entity_type' => 'reservation',
      'field_name' => 'reservation_body',
    ])->save();
    $this->addDefaultReservationField('node', 'page', 'reservation');

    $account = $this->createUser();
    $other_account = $this->createUser();

    $node_authored_by_account = $this->createNode([
      'uid' => $account->id(),
      'title' => "authored by {$account->id()}",
    ]);
    $node_reservationed_by_account = $this->createNode([
      'title' => "reservationed by {$account->id()}",
    ]);
    $arbitrary_node = $this->createNode();

    // Reservation added by $account.
    Reservation::create([
      'uid' => $account->id(),
      'entity_id' => $node_reservationed_by_account->id(),
      'entity_type' => 'node',
      'field_name' => 'reservation',
    ])->save();
    // Reservation added by $other_account on $node_reservationed_by_account
    Reservation::create([
      'uid' => $other_account->id(),
      'entity_id' => $node_reservationed_by_account->id(),
      'entity_type' => 'node',
      'field_name' => 'reservation',
    ])->save();
    // Reservation added by $other_account on an arbitrary node.
    Reservation::create([
      'uid' => $other_account->id(),
      'entity_id' => $arbitrary_node->id(),
      'entity_type' => 'node',
      'field_name' => 'reservation',
    ])->save();

    ViewTestData::createTestViews(static::class, ['reservation_test_views']);

    $expected_result = [
      [
        'nid' => $node_authored_by_account->id(),
        'title' => "authored by {$account->id()}",
      ],
      [
        'nid' => $node_reservationed_by_account->id(),
        'title' => "reservationed by {$account->id()}",
      ],
    ];
    $column_map = ['nid' => 'nid', 'title' => 'title'];
    $view = Views::getView('test_reservation_user_uid');

    // Test the argument handler.
    $view->preview(NULL, [$account->id()]);
    $this->assertIdenticalResultset($view, $expected_result, $column_map);

    // Test the filter handler. Reuse the same view but replace the argument
    // handler with a filter handler.
    $view->removeHandler('default', 'argument', 'uid_touch');
    $options = [
      'id' => 'uid_touch',
      'table' => 'node_field_data',
      'field' => 'uid_touch',
      'value' => [$account->id()],
    ];
    $view->addHandler('default', 'filter', 'node_field_data', 'uid_touch', $options);

    $view->preview();
    $this->assertIdenticalResultset($view, $expected_result, $column_map);
  }

}
