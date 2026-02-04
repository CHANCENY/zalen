<?php

namespace Drupal\Tests\datetime_flatpickr\Kernel;

use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\KernelTests\KernelTestBase;
use Drupal\datetime_flatpickr\Plugin\Field\FieldWidget\DateTimeRangeSeparateInputsFlatPickrWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Tests the DateTimeRangeSeparateInputsFlatPickrWidget massageFormValues() method.
 *
 * @group datetime_range
 */
class DateTimeRangeSeparateInputsMassageFormValuesTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'field',
    'datetime_flatpickr',
  ];

  protected function createWidget($type) {
    $field_definition = $this->createMock(FieldDefinitionInterface::class);

    $field_definition->expects($this->any())
      ->method('getSetting')
      ->with('datetime_type')
      ->willReturn($type);

    return DateTimeRangeSeparateInputsFlatPickrWidget::create(
      $this->container,
      [
        'field_definition' => $field_definition,
        'settings' => [],
        'third_party_settings' => [],
      ],
      'datetime_range_separate_inputs_flatpickr',
      ['type' => 'datetime_range_separate_inputs_flatpickr']
    );
  }

  /**
   * Tests the massageFormValues() method.
   */
  public function testMassageFormValues() {
    date_default_timezone_set('UTC');

    $widget = $this->createWidget(DateTimeItem::DATETIME_TYPE_DATETIME);

    // Create a mock FormStateInterface.
    $formState = $this->createMock(FormStateInterface::class);

    // Set the input values for start and end date.
    $values = [
      [
        'value' => '2023-05-11T00:00:00',
        'end_value' => '2023-05-27T00:00:00',
      ],
    ];

    $outputValues = $widget->massageFormValues($values, [], $formState);

    $expectedOutputValues = [
      [
        'value' => '2023-05-11T00:00:00',
        'end_value' => '2023-05-27T00:00:00',
      ],
    ];
    $this->assertEquals($expectedOutputValues, $outputValues);

    $values = [
      [
        'value' => '2023-05-11 00:00',
      ],
    ];

    $outputValues = $widget->massageFormValues($values, [], $formState);

    $expectedOutputValues = [
      [
        'value' => '2023-05-11T00:00:00',
      ],
    ];
    $this->assertEquals($expectedOutputValues, $outputValues);

    date_default_timezone_set('Asia/Kolkata');
    $values = [
      [
        'value' => '2023-05-11T10:50:00',
        'end_value' => '2023-05-27T10:50:00',
      ],
    ];
    $expectedOutputValues = [
      [
        'value' => '2023-05-11T05:20:00',
        'end_value' => '2023-05-27T05:20:00',
      ],
    ];
    $outputValues = $widget->massageFormValues($values, [], $formState);
    $this->assertEquals($expectedOutputValues, $outputValues);

    $widget = $this->createWidget(DateTimeItem::DATETIME_TYPE_DATE);

    $values = [
      [
        'value' => '2023-05-11T00:00:00',
        'end_value' => '2023-05-27T00:00:00',
      ],
    ];

    $expectedOutputValues = [
      [
        'value' => '2023-05-11',
        'end_value' => '2023-05-27',
      ],
    ];

    $outputValues = $widget->massageFormValues($values, [], $formState);
    $this->assertEquals($expectedOutputValues, $outputValues);

  }

}
