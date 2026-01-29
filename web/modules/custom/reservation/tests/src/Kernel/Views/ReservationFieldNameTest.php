<?php

namespace Drupal\Tests\reservation\Kernel\Views;

use Drupal\reservation\Entity\Reservation;
use Drupal\reservation\Tests\ReservationTestTrait;
use Drupal\Core\Render\RenderContext;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\views\Tests\ViewResultAssertionTrait;
use Drupal\views\Tests\ViewTestData;
use Drupal\views\Views;

/**
 * Tests the reservation field name field.
 *
 * @group reservation
 */
class ReservationFieldNameTest extends KernelTestBase {

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
  public static $testViews = ['test_reservation_field_name'];

  /**
   * Test reservation field name.
   */
  public function testReservationFieldName() {
    $renderer = $this->container->get('renderer');

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('reservation');
    $this->installSchema('system', ['sequences']);
    $this->installSchema('reservation', ['reservation_entity_statistics']);
    $this->installConfig(['filter']);

    NodeType::create(['type' => 'page'])->save();
    FieldStorageConfig::create([
      'type' => 'text_long',
      'entity_type' => 'reservation',
      'field_name' => 'reservation_body',
    ])->save();
    $this->addDefaultReservationField('node', 'page', 'reservation');
    $this->addDefaultReservationField('node', 'page', 'reservation_custom');

    ViewTestData::createTestViews(static::class, ['reservation_test_views']);

    $node = $this->createNode();
    $reservation = Reservation::create([
      'entity_id' => $node->id(),
      'entity_type' => 'node',
      'field_name' => 'reservation',
    ]);
    $reservation->save();
    $reservation2 = Reservation::create([
      'entity_id' => $node->id(),
      'entity_type' => 'node',
      'field_name' => 'reservation_custom',
    ]);
    $reservation2->save();

    $view = Views::getView('test_reservation_field_name');
    $view->preview();

    $expected_result = [
      [
        'cid' => $reservation->id(),
        'field_name' => $reservation->getFieldName(),
      ],
      [
        'cid' => $reservation2->id(),
        'field_name' => $reservation2->getFieldName(),
      ],
    ];
    $column_map = [
      'cid' => 'cid',
      'reservation_field_data_field_name' => 'field_name',
    ];
    $this->assertIdenticalResultset($view, $expected_result, $column_map);

    // Test that data rendered correctly.
    $expected_output = $renderer->executeInRenderContext(new RenderContext(), function () use ($view) {
      return $view->field['field_name']->advancedRender($view->result[0]);
    });
    $this->assertEquals($expected_output, $reservation->getFieldName());
    $expected_output = $renderer->executeInRenderContext(new RenderContext(), function () use ($view) {
      return $view->field['field_name']->advancedRender($view->result[1]);
    });
    $this->assertEquals($expected_output, $reservation2->getFieldName());
  }

}
