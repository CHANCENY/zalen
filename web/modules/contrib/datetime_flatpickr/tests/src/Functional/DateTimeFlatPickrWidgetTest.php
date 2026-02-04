<?php

namespace Drupal\Tests\datetime_flatpickr\Functional;

use Drupal\Tests\datetime\Functional\DateTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Core\Entity\Entity\EntityFormDisplay;

/**
 * Datetime flatpickr form test.
 *
 * @group datetime_flatpickr
 */
class DateTimeFlatPickrWidgetTest extends DateTestBase {

  /**
   * Language object for testing purposes.
   *
   * @var \Drupal\language\Entity\ConfigurableLanguage
   */
  protected $testLanguage;

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
  protected $fieldType = 'datetime';

  /**
   * The widget type used for rendering the field form element.
   *
   * @var string
   */
  protected $widgetType = 'datetime_flatpickr';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'language',
    'node',
    'entity_test',
    'datetime',
    'field_ui',
    'datetime_flatpickr',
  ];

  /**
   * {@inheritdoc}
   */
  protected function getTestFieldType() {
    return $this->fieldType;
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->testLanguage = ConfigurableLanguage::createFromLangcode('fr');
    $this->testLanguage->save();
  }

  /**
   * Tests Date Time Flat Pickr functionality.
   */
  public function testDateTimeFlatPickrWidget() {
    $field_name = $this->fieldStorage->getName();
    $field_label = $this->field->label();

    // Ensure field is set to a date only field.
    $this->fieldStorage->setSetting('datetime_type', 'date');
    $this->fieldStorage->save();

    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
    $display_repository = \Drupal::service('entity_display.repository');

    // Define the widget settings.
    $widget_settings = [
      'dateFormat' => 'Y-m-d H:i',
      'altInput' => TRUE,
      'altFormat' => 'F j, Y',
      'allowInput' => TRUE,
      'enableTime' => TRUE,
      'enableSeconds' => TRUE,
      'minDate' => '2000',
      'maxDate' => '2040',
      'minTime' => ['hour' => '10', 'min' => '20'],
      'maxTime' => ['hour' => '18', 'min' => '20'],
      'time_24hr' => TRUE,
    ];

    // Change the widget to a datelist widget.
    $display_repository->getFormDisplay($this->field->getTargetEntityTypeId(), $this->field->getTargetBundle())
      ->setComponent($field_name, [
        'type' => $this->widgetType,
        'settings' => $widget_settings,
      ])
      ->save();
    \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();

    // Display creation form.
    $this->drupalGet('entity_test/add');
    $this->assertSession()->elementTextContains('xpath', '//div[@id="edit-' . $field_name . '-wrapper"]/*/label', $field_label);
    $this->assertSession()->elementExists('xpath', '//input[@flatpickr-name="entity_test-' . $field_name . '-0"]');
    $this->assertSession()->elementExists('xpath', '//input[@id="edit-' . $field_name . '-0-value"]');

    $drupalSettings = $this->getDrupalSettings();

    $this->assertArrayHasKey('datetimeFlatPickr', $drupalSettings);

    $settings = $drupalSettings['datetimeFlatPickr'];

    $this->assertArrayHasKey('entity_test-' . $field_name . '-0', $settings);

    // Get the widget settings for the field.
    $flatPickrSettings = $settings['entity_test-' . $field_name . '-0']['settings'];

    // The minTime and maxTime are altered at output.
    $widget_settings['minTime'] = $widget_settings['minTime']['hour'] . ':' . $widget_settings['minTime']['min'];
    $widget_settings['maxTime'] = $widget_settings['maxTime']['hour'] . ':' . $widget_settings['maxTime']['min'];

    // Assert that the widget settings match the expected values.
    $this->assertEquals($widget_settings, array_intersect_key($flatPickrSettings, $widget_settings));

    // At default language the "locale" is not part of the settings.
    $this->assertArrayNotHasKey('locale', $flatPickrSettings);

    // Test another language, if the locale setting is populated.
    $this->drupalGet('fr/entity_test/add');

    $drupalSettings = $this->getDrupalSettings();

    $flatPickrSettings = $drupalSettings['datetimeFlatPickr']['entity_test-' . $field_name . '-0']['settings'];

    $this->assertArrayHasKey('locale', $flatPickrSettings);

    $this->assertEquals('fr', $flatPickrSettings['locale']);

    // Go to the form display page.
    $this->drupalGet('entity_test/structure/entity_test/form-display');

    $this->getSession()->getPage()->pressButton($field_name . '_settings_edit');
    $this->assertSession()->checkboxChecked('fields[' . $field_name . '][settings_edit_form][settings][altInput]');
    $this->assertSession()->checkboxChecked('fields[' . $field_name . '][settings_edit_form][settings][allowInput]');

    // Define the new settings.
    $new_settings = [
      'dateFormat' => 'Y-m-d',
      'altInput' => FALSE,
      'altFormat' => 'F j',
      'allowInput' => FALSE,
      'enableTime' => FALSE,
      'enableSeconds' => FALSE,
      'minDate' => '1990',
      'maxDate' => '2050',
      'minTime' => ['hour' => '4', 'min' => '40'],
      'maxTime' => ['hour' => '21', 'min' => '10'],
      'time_24hr' => FALSE,
    ];

    foreach ($new_settings as $setting => $value) {
      $setting_name = 'fields[' . $field_name . '][settings_edit_form][settings][' . $setting . ']';
      if (is_bool($value)) {
        if ($value) {
          $this->getSession()->getPage()->checkField($setting_name);
        }
        else {
          $this->getSession()->getPage()->uncheckField($setting_name);
        }
      }
      elseif ($setting === 'minTime' || $setting === 'maxTime') {
        $this->getSession()->getPage()->fillField($setting_name . '[hour]', $value['hour']);
        $this->getSession()->getPage()->fillField($setting_name . '[min]', $value['min']);
      }
      else {
        $this->getSession()->getPage()->fillField($setting_name, $value);
      }
    }

    $this->getSession()->getPage()->pressButton('Update');
    $this->getSession()->getPage()->pressButton('Save');

    // Load the form display configuration.
    $form_display = EntityFormDisplay::load('entity_test.entity_test.default');
    $component = $form_display->getComponent($field_name);

    // Check that the settings have been saved correctly.
    foreach ($new_settings as $setting => $value) {
      if ($setting === 'minTime' || $setting === 'maxTime') {
        $this->assertEquals($value['hour'], $component['settings'][$setting]['hour']);
        $this->assertEquals($value['min'], $component['settings'][$setting]['min']);
      }
      else {
        $this->assertEquals($value, $component['settings'][$setting]);
      }
    }

  }

}
