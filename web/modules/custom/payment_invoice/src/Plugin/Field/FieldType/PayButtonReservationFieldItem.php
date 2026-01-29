<?php

/**
 * @file
 * Contains Drupal\payment_invoice\Plugin\Field\FieldType\PayButtonReservationFieldItem.
 */

namespace Drupal\payment_invoice\Plugin\Field\FieldType;

use Drupal\payment_invoice\Plugin\Field\FieldType\PayButtonBaseFieldItem;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;

/**
 * @FieldType(
 *   id = "payment_button_reservation",
 *   label = @Translation("Payment button in reservation"),
 *   module = "payment_invoice",
 *   description = @Translation("Custom payment button in reservation."),
 *   category = @Translation("Price"),
 *   default_widget = "payment_button_field_default_input_widget",
 *   default_formatter = "payment_button_field_default_formatter",
 *   cardinality = 1,
 * )
 */
class PayButtonReservationFieldItem extends PayButtonBaseFieldItem {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['value'] = DataDefinition::create('timestamp')
      ->setLabel(new TranslatableMarkup('Timestamp time click order'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    $defaultFieldSettings = [
      'parent_field_availability' => '',
      'payment_provider' => '',
      'payment_method' => '',
      'payment_parameter' => [],
    ] + parent::defaultFieldSettings();
    return $defaultFieldSettings;
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {

    $element = parent::fieldSettingsForm($form, $form_state);
    $settings = $this->getSettings();

    $element['parent_field_availability'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Set ID parent field (interval for button availability).'),
      '#placeholder' => 'field_example_name',
      '#default_value' => $settings['parent_field_availability'],
      '#required' => TRUE,
      '#description' => $this->t('This ID (machine name) for field availability payment button that determined in room of the owner.'),
    ];

    // Load the list of available payment provider plugins.
    if ($manager_providers = \Drupal::service('plugin.manager.payment_provider')) {
      $list_provider = $manager_providers->getDefinitions();
      $options = [];
      foreach ($list_provider as $k => $v) {
        $options[$k] = $v['id'];
      };
      $element['payment_provider'] = [
        '#type' => 'radios',
        '#title' => $this->t('Select a payment service provider.'),
        '#description' => $this->t('You must have the payment provider module enabled.'),
        '#multiple' => FALSE,
        '#options' => $options + ['test' => 'Test (use for test dev)',],
        '#limit_validation_errors' => [],
        '#ajax' => [
          'callback' => [$this, 'ajax_show_payment_method'],
          'event' => 'change',
          'wrapper' => 'provider-payment-method',
          'method' => 'replaceWith',
          'effect' => 'fade',
          'progress' => ['type' => 'throbber', 'message' => $this->t('Loading...'),],
        ],
        '#default_value' => empty($settings['payment_provider']) ? '' : $settings['payment_provider'],
      ];

      // Wrapper for loading available payment methods for fields using the selected payment provider.
      $element['payment_method'] = [
        '#prefix' => '<div id="provider-payment-method">',
        '#suffix' => '</div>',
      ];

      // Let's check if and which a specific payment provider has been selected.
      $current_provider = $form_state->getValue(['settings', 'payment_provider']);
      if (empty($current_provider) && !empty($settings['payment_provider'])) {
        $current_provider = $settings['payment_provider'];
      };

      // Let's check if the plugin of the selected current payment provider is available.
      // Let's check if the payment provider has customizable payment methods and download them for customization.
      if ($current_provider && $method_provider = $manager_providers->hasDefinition($current_provider)) {
        /** @var \Drupal\payment_provider\Plugin\PaymentProvider\PaymentProviderPluginInterface $provider The plagin instance. */
        $provider = $manager_providers->createInstance($current_provider);
        
        $method_provider = $provider->getPaymentMethods();
        if (!empty($method_provider)) {
          $element['payment_method'] += [
            '#type' => 'radios',
            '#title' => $this->t('Select a payment method for this provider.'),
            '#description' => $this->t('All Mollie payment methods available in plugin'),
            '#multiple' => FALSE,
            '#options' => $method_provider,
            '#default_value' =>  (!empty($settings['payment_method']) && array_key_exists($settings['payment_method'], $method_provider)) ? $settings['payment_method'] : '',
            '#ajax' => [
              'callback' => [$this, 'ajax_show_payment_parameter'],
              'event' => 'change',
              'wrapper' => 'provider-payment-parameter',
              'method' => 'replaceWith',
              'effect' => 'fade',
              'progress' => ['type' => 'throbber', 'message' => $this->t('Loading...'),],
            ],
          ];
        } else {
          $element['payment_method'] += [
            '#markup' => $this->t('There are no list available payment methods for this provider.'),
          ];
        };
      } else if ($current_provider && !$method_provider) {
        $element['payment_method'] += [
          '#markup' => $this->t('Payment service provider not available.'),
        ];
      } else {
        $element['payment_method'] += [
          '#markup' => $this->t('Select a payment service provider.'),
        ];
      };

      // Wrapper for loading parameters of a specific payment method.
      $element['payment_parameter'] = [
        '#prefix' => '<div id="provider-payment-parameter">',
        '#suffix' => '</div>',
      ];

      // Find out the selected payment method.
      if (!$selected_method_provider = $form_state->getValue(['settings', 'payment_method'])) {
        $selected_method_provider = $settings['payment_method'] ?? '';
      };

      if (isset($method_provider) && $selected_method_provider && $provider) {
        $help_form = $provider->getHelperFormSettingsField(); //Call to a member function getHelperFormSettingsField() on null
        $help_form = $help_form->getFieldConfigForm($form, $form_state, $selected_method_provider, $settings['payment_parameter']);
        $element['payment_parameter'] += $help_form;
      } else {
        $element['payment_parameter'] += [
          '#markup' => $this->t('Payment method settings are not available. There is no payment method for the payment provider, or no has been selected payment method in provider.'),
        ];
      };
            
      };

       //$pr = \Drupal\payment_provider\Plugin\PaymentProvider\PaymentProviderMollie::intPrintTest($settings, __FILE__, __FUNCTION__, __LINE__);;
    return $element;
  }

  /**
   * Ajah callback for the settings form of the button pay.
   *
   * Called from \Drupal\payment_invoice\Plugin\Field\FieldType\PayButtonReservationFieldItem,
   * setting field-level parameters. To add payment method settings for a payment provider for a button.
   *
   * @param array $form
   *   The form where the settings form is being included in.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the (entire) configuration form.
   *
   * @return array
   *   The part of the form definition for the field settings.
   */
  public function ajax_show_payment_method(array $form, FormStateInterface $form_state) {
    if (empty($form['settings']['payment_method']['#type']) ||
    ($form['settings']['payment_method']['#type'] == 'radios' && !empty($form['settings']['payment_method']['#default_value']))) {
      $response = new AjaxResponse();
      $response->addCommand(new ReplaceCommand('#provider-payment-method', $form['settings']['payment_method']));
      $response->addCommand(new ReplaceCommand('#provider-payment-parameter', $form['settings']['payment_parameter']));
      return $response;
    };
    return $form['settings']['payment_method'];
  }

  /**
   * Ajah callback for the settings form of the button pay.
   *
   * Called from \Drupal\payment_invoice\Plugin\Field\FieldType\PayButtonReservationFieldItem,
   * setting field-level parameters.
   * To add payment configuration to the payment method settings for a payment provider for a button.
   *
   * @param array $form
   *   The form where the settings form is being included in.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the (entire) configuration form.
   *
   * @return array
   *   The part of the form definition for the field settings.
   */
  public function ajax_show_payment_parameter(array $form, FormStateInterface $form_state) {
    return $form['settings']['payment_parameter'];
  }


}
