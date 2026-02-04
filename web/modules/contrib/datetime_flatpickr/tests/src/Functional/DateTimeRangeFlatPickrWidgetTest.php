<?php

namespace Drupal\Tests\datetime_flatpickr\Functional;

/**
 * Datetime flatpickr range form test.
 *
 * @group datetime_flatpickr
 */
class DateTimeRangeFlatPickrWidgetTest extends DateTimeFlatPickrWidgetTest {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The default display settings to use for the formatters.
   *
   * @var array
   */
  protected $defaultSettings = ['timezone_override' => ''];

  /**
   * The field type associated with the widget.
   *
   * @var string
   */
  protected $fieldType = 'daterange';

  /**
   * The widget type used for rendering the field form element.
   *
   * @var string
   */
  protected $widgetType = 'datetime_range_flatpickr';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'node',
    'entity_test',
    'datetime_range',
    'field_ui',
    'datetime_flatpickr',
  ];

}
