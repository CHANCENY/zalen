<?php

/**
 * @file
 * Contains Drupal\room_tariff\Plugin\Field\FieldType\TariffFieldItem.
 */

namespace Drupal\room_tariff\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Field\FieldDefinitionInterface;


/**
 * @FieldType(
 *   id = "room_tariff",
 *   label = @Translation("Tariff field"),
 *   module = "room_tariff",
 *   description = @Translation("Custom price."),
 *   category = @Translation("Price"),
 *   default_widget = "tariff_field_input_widget",
 *   default_formatter = "tariff_field_default_formatter",
 *   list_class = "\Drupal\room_tariff\Plugin\Field\FieldType\TariffFieldItemList",
 * )
 */
class TariffFieldItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   *
   * We declare the fields for the table where the values of our field will be stored.
   * @see https://www.drupal.org/node/159605
   */
  public static function schema (FieldStorageDefinitionInterface $field_definition) {
    return array(
      'columns' => array(

        'pattern' => array(
          'type' => 'char',
          'length' => 8,
          'not null' => TRUE,
          'description' => 'Tariff type',
        ),

        'price' => array(
          'type' => 'int',
          'unsigned' => TRUE,
          'size' => 'normal',
          'not null' => TRUE,
          'default' => 0,
          'description' => 'Price in cents',
        ),

        'currency' => array(
          'type' => 'char',
          'length' => 3,
          'not null' => TRUE,
          'default' => 'EUR',
          'description' => 'Currency',
        ),

        'begin' => array(
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => FALSE,
          'size' => 'big',
          'default' => null,
          'description' => 'Timestamp beginning of period',
        ),

        'end' => array(
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => FALSE,
          'size' => 'big',
          'default' => null,
          'description' => 'Timestamp end of period',
        ),

        'services' => array(
          'type' => 'varchar',
          'length' => 64,
          'not null' => FALSE,
          'description' => 'Additional services',
        ),

        'require' => array(
          'type' => 'int',
          'size' => 'tiny',
          'unsigned' => TRUE,
          'not null' => FALSE,
          'default' => 0,
          'description' => 'Sets a service if is mandatory',
        ),

        'range_type' => array(
          'type' => 'char',
          'length' => 3,
          'not null' => FALSE,
          'default' => null,
          'description' => 'Range type',
        ),

        'range_data' => array(
          'type' => 'varchar',
          'length' => 255,
          'not null' => FALSE,
          'default' => null,
          'description' => 'Range data',
        ),

        'special_offer' => array(
          'type' => 'varchar',
          'length' => 255,
          'not null' => FALSE,
          'default' => null,
          'description' => 'Special Offer',
        ),
        'services_images' => array(
          'type' => 'text',
          'not null' => FALSE,
          'default' => null,
          'description' => 'Service image entity reference',
        ),
        'service_description' => array(
          'type' => 'text',
          'not null' => FALSE,
          'default' => null,
          'description' => 'Description',
        ),
        'services_minimum_order' => array(
          'type' => 'int',
          'not null' => FALSE,
          'default' => null,
          'description' => 'Services minimum order',
        ),
        'person_images' => array(
          'type' => 'text',
          'not null' => FALSE,
          'default' => null,
          'description' => 'person image entity reference',
        ),
        'person_label' => array(
          'type' => 'varchar',
          'length' => 255,
          'not null' => FALSE,
          'default' => null,
          'description' => 'Special Offer',
        ),
        'person_description' => array(
          'type' => 'text',
          'not null' => FALSE,
          'default' => null,
          'description' => 'Description',
        ),
        'is_optional' => array(
          'type' => 'int',
          'size' => 'tiny',
          'not null' => TRUE,
          'default' => 0,
          'description' => 'is_optional',
        ),
        'child_friendly' => array(
          'type' => 'int',
          'size' => 'tiny',
          'not null' => TRUE,
          'default' => 0,
          'description' => 'child_friendly',
        ),
      ),
    );
  }

  /**
   * {@inheritdoc}
   *
   * This tells Drupal if the field is missing. If NULL then the data will not be saved to the database.
   */
  public function isEmpty() {
    $value = $this->get('price')->getValue();
    return $value === NULL || $value === '';
  }

  /**
   * {@inheritdoc}
   *
   * This tells Drupal if the field is missing in TariffFieldInputWidget - massageFormValues.
   * If NULL then the data will be as missing.
   */
  public function isEmptyForm() {
    $value = $this->getValue()['enumerate']['price'] ?? null;
    if ($value !== '') {
      $value = is_numeric($value) ? $value : null;
    };
    return $value === NULL;
  }

  /**
   * {@inheritdoc}
   *
   * This tells Drupal how to store the values for this field. (For example integer, string, or any.)
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {

    $properties['pattern'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Type of tariff'))->setDescription(new TranslatableMarkup('The type of tarif'))->setRequired(TRUE);
    $properties['price'] = DataDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Price'));
    $properties['currency'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Currency'));
    $properties['begin'] = DataDefinition::create('timestamp')
      ->setLabel(new TranslatableMarkup('Beginning of period'));
    $properties['end'] = DataDefinition::create('timestamp')
      ->setLabel(new TranslatableMarkup('End of period'));
    $properties['services'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Additional services'))->setSetting('max_length', 64)->addConstraint('Length', ['max' => 64]);
    $properties['require'] = DataDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Service is mandatory'));
    $properties['range_type'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Range type'));
    $properties['range_data'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Range data'))->setSetting('max_length', 255)->addConstraint('Length', ['max' => 255]);


    $properties['special_offer'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Special Offer'))->setSetting('max_length', 255)->addConstraint('Length', ['max' => 255]);
    $properties['service_description'] = DataDefinition::create('string')
        ->setLabel(new TranslatableMarkup('Service Description'))->setDescription('Description of the service');
    $properties['services_images'] = DataDefinition::create('entity_reference')
      ->setLabel(t('Service image'))->setDescription(t('Reference to the service image file entity'))->setSetting('target_type', 'file');
    $properties['services_minimum_order'] = DataDefinition::create('integer')
    ->setLabel(new TranslatableMarkup('Service Minimum Order'))->setDescription('Minimum order amount for the service');
    $properties['person_label'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Label'))->setSetting('max_length', 255)->addConstraint('Length', ['max' => 255]);
    $properties['person_description'] = DataDefinition::create('string')
        ->setLabel(new TranslatableMarkup('Description'))->setDescription('Description of the Person');
    $properties['person_images'] = DataDefinition::create('entity_reference')
      ->setLabel(t('Image'))->setDescription(t('Reference to the Person image file entity'))->setSetting('target_type', 'file');

      // Add the new boolean properties
    $properties['is_optional'] = DataDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Is Optional'))
      ->setDescription(new TranslatableMarkup('Whether this price unit is optional.'))
      ->setRequired(FALSE);
    $properties['child_friendly'] = DataDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Child Friendly'))
      ->setDescription(new TranslatableMarkup('Whether this price unit is child-friendly.'))
      ->setRequired(FALSE);

      return $properties;
  }

  /**
   * {@inheritdoc}
   *
   */
  public static function defaultStorageSettings() {
    $defaultStorageSettings = [
      'tariff_type' => [
        'per_hour' => new TranslatableMarkup('Hourly rate'),
        'inan_day' => new TranslatableMarkup('Daily price'),
        'i_person' => new TranslatableMarkup('Per person'),
        'interval' => new TranslatableMarkup('Interval price'),
        'rang_dat' => new TranslatableMarkup('Date range'),
        'services' => new TranslatableMarkup('Additional services'),
      ],
      'currency' => [
        'EUR' => new TranslatableMarkup('Euro'),
        'USD' => new TranslatableMarkup('US dollar'),
      ],
      'default_currency' => 'EUR',
      'range_type' => [
        'tim' => new TranslatableMarkup('Repeat every day'),
        'day' => new TranslatableMarkup('Repeat every week'),
        'mon' => new TranslatableMarkup('Repeat every month'),
        'yea' => new TranslatableMarkup('Repeat every year'),
      ],
      'default_fin_service' => 'ECB',
    ] + parent::defaultStorageSettings();

    return $defaultStorageSettings;
  }
  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    $element = parent::storageSettingsForm($form, $form_state, $has_data);
    $settings = $this->getFieldDefinition()->getFieldStorageDefinition()->getSettings();

    // Prepare a list of available organization of currency quotes.
    /** @var \Drupal\room_tariff\Service\CurrencyQuotesList $fin_services */
    $fin_services = \Drupal::service('room_tariff.currency_quotes_list');
    $fin_services_options = [];
    foreach ($fin_services->services as $key) {
      $fin_services_options['default_bank'][$key['id']] = $key['label'];
    };
    // Prepare a list of available currency quotes for current organization. And some actions...
    // - If the financial institution uses a language other than English.
    // - Or changed the list of supported currencies.
    $current_bank_value = !empty($form_state->getTriggeringElement()) ? $form_state->getValue(['settings','default_bank']) : $settings['default_fin_service'];
    $fin_services_options['service'][$current_bank_value] = $fin_services->getObjService($current_bank_value);
    $fin_services_options['default_currency'][$current_bank_value] = $fin_services_options['service'][$current_bank_value]->getDefaultListCurrency();
    $fin_services_options['default_currency'][$current_bank_value] = array_intersect_key(
      $fin_services_options['default_currency'][$current_bank_value],
      $fin_services_options['service'][$current_bank_value]->getCurrencyRate() + [
        $fin_services_options['service'][$current_bank_value]::BASE_CURRENCY => $fin_services_options['service'][$current_bank_value]::BASE_CURRENCY,
      ]
    );

    // Bild form.

    $element['default_bank'] = [
      '#type' => 'select',
      '#title' => $this->t('Place currency quotes.'),
      '#options' => $fin_services_options['default_bank'],
      '#default_value' => $settings['default_fin_service'],
      '#required' => FALSE,
      '#description' => $this->t('Which bank to use to get currency quotes?'),
      '#ajax' => [
        'callback' => [$this, 'ajaxReferenceCurrencies'],
        'disable-refocus' => FALSE,
        'event' => 'change',
        'wrapper' => 'reference-currencies',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Refreshing reference currencies...'),
        ],
      ],
    ];
    $element['default_currency'] = [
      '#type' => 'select',
      '#title' => $this->t('Default currency (@ex).', ['@ex' => '$, €, £, ₴...']),
      '#options' => $fin_services_options['default_currency'][$current_bank_value],
      '#default_value' => !empty(array_intersect_key([$settings['default_currency'] => 0,], $fin_services_options['default_currency'][$current_bank_value])) ? $settings['default_currency'] : '',
      '#required' => FALSE,
      '#description' => $this->t('The default currency in the widget for the field.'),
      '#prefix' => '<div id="reference-currencies">',
      '#suffix' => '</div>',
    ];
    return $element;
  }
  /**
   * {@inheritdoc}
   */
  public static function storageSettingsToConfigData(array $settings) {

    // After saving the field settings, add a marking to the exchange rate update settings.
    // To be able to update only used exchange rates via cron.
    // Also, if do not save the settings, the default configs are saved when creating the field.

    /** @var \Drupal\Core\Config\Config $config_fin_services */
    $config_fin_services = \Drupal::service('config.factory')->getEditable('room_tariff.currency');
    $config_fin_services->set('currency_data.check_changed_in', 'storageSettingsToConfigData');
    $config_fin_services->save();

    return parent::storageSettingsToConfigData($settings);
  }
  /**
   * {@inheritdoc}
   *
   */
  public static function onDependencyRemoval(FieldDefinitionInterface $field_definition, array $dependencies) {

    // After remove, add a marking to the exchange rate update settings.
    // We just mark for cron refresh about it. Because the function is called
    // - to open the form "delete field"
    // - and to click the "delete" button.

    /** @var \Drupal\Core\Config\Config $config_fin_services */
    $config_fin_services = \Drupal::service('config.factory')->getEditable('room_tariff.currency');
    $config_fin_services->set('currency_data.check_changed_in', 'onDependencyRemoval');
    $config_fin_services->save();

    return parent::onDependencyRemoval($field_definition, $dependencies);
  }
  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   * @return array Return the part of the form that has changed.
   */
  public function ajaxReferenceCurrencies(array &$form, FormStateInterface $form_state) {
    // Return the part of the form that has changed.
    return $form['settings']['default_currency'];
  }

  /**
   * {@inheritdoc}
   *
   */
  public static function defaultFieldSettings() {
    $defaultFieldSettings = [
      'on_label' => 'Explanation of the tariff price. Write here.',
      'currency_handle' => 'convert',
      'use_converter' => false,
    ] + parent::defaultFieldSettings();
    return $defaultFieldSettings;
  }
  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $settings = $this->getSettings();
    $element = [];
    $element['on_label'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Explanation of the tariff price'),
      '#rows' => 3,
      '#default_value' => $settings['on_label'] ? $settings['on_label'] : $this->t('on_label'),
    ];
    $element['use_converter'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use a currency auto converter?'),
      '#default_value' => $settings['use_converter'] ? $settings['use_converter'] : '',
      '#required' => FALSE,
      '#description' => $this->t('The field will be available to convert currency.'),
    ];
    $element['currency_handle'] = [
      '#type' => 'select',
      '#title' => $this->t('Handling different currencies'),
      '#options' => [
        'convert' => $this->t('Auto convert to @currency when creating material.', ['@currency'=>$settings['default_currency'],]),
        'disable' => $this->t('Disable entering currency in a different @currency.', ['@currency'=>$settings['default_currency'],]),
        'save' => $this->t('Save in the currency in which the user entered.'),
      ],
      '#default_value' => $settings['currency_handle'] ?? 'convert',
      '#required' => TRUE,
      '#description' => $this->t('How to handle currency?'),
      '#states' => [
        'visible' => [
          ':input[name="settings[use_converter]"]' => ['checked' => TRUE,],
        ],
      ],
    ];
    return $element;
  }

  ///**
  // * {@inheritdoc}
  // */
  /* public function getConstraints() {
    $constraint_manager = \Drupal::typedDataManager()->getValidationConstraintManager();
    $constraints = parent::getConstraints();
    $max_length = 256;
    $constraints[] = $constraint_manager->create('ComplexData', [
      'value' => [
        'Length' => [
          'max' => $max_length,
          'maxMessage' => $this->t('%name: the telephone number may not be longer than @max characters.', [
            '%name' => $this->getFieldDefinition()->getLabel(), '@max' => $max_length,
          ]),
        ],
      ],
    ]);
    return $constraints;
  } */
  // */
  // */

}
