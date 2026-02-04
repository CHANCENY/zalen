<?php

namespace Drupal\Tests\datetime_flatpickr\Kernel;

use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\KernelTests\KernelTestBase;
use Drupal\datetime_flatpickr\Plugin\Field\FieldWidget\DateTimeFlatPickrWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Tests the DateTimeFlatPickrWidget massageFormValues() method.
 *
 * @group datetime
 */
class DateTimeFlatPickrWidgetMassageFormValuesTest extends KernelTestBase {

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

    // Create an instance of the DateTimeFlatPickrWidget class.
    return DateTimeFlatPickrWidget::create(
      $this->container,
      [
        'field_definition' => $field_definition,
        'settings' => [],
        'third_party_settings' => [],
      ],
      'datetime_flatpickr',
      ['type' => 'datetime_flatpickr']
    );
  }

  /**
   * Tests the massageFormValues() method.
   */
  public function testMassageFormValues() {
    date_default_timezone_set('UTC');

    $widget = $this->createWidget(DateTimeItem::DATETIME_TYPE_DATETIME);

    $formState = $this->createMock(FormStateInterface::class);

    $values = [
      [
        'value' => '2023-05-19 10:50',
      ],
    ];

    $outputValues = $widget->massageFormValues($values, [], $formState);

    $expectedOutputValues = [
      [
        'value' => '2023-05-19T10:50:00',
      ],
    ];
    $this->assertEquals($expectedOutputValues, $outputValues);

    date_default_timezone_set('Asia/Kolkata');
    $expectedOutputValues = [
      [
        'value' => '2023-05-19T05:20:00',
      ],
    ];

    $outputValues = $widget->massageFormValues($values, [], $formState);
    $this->assertEquals($expectedOutputValues, $outputValues);

    date_default_timezone_set('UTC');
    $widget = $this->createWidget(DateTimeItem::DATETIME_TYPE_DATE);

    $values = [
      [
        'value' => '2023-05-19',
      ],
    ];

    $expectedOutputValues = [
      [
        'value' => '2023-05-19',
      ],
    ];

    $outputValues = $widget->massageFormValues($values, [], $formState);
    $this->assertEquals($expectedOutputValues, $outputValues);

    date_default_timezone_set('Asia/Kolkata');
    $outputValues = $widget->massageFormValues($values, [], $formState);
    $this->assertEquals($expectedOutputValues, $outputValues);

  }

}
