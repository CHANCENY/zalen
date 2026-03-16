<?php

namespace Drupal\google_place_field\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Google Place Field settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * Config name.
   */
  const SETTINGS = 'google_place_field.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'google_place_field_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      self::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config(self::SETTINGS);

    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Google API Key'),
      '#default_value' => $config->get('api_key'),
      '#required' => TRUE,
      '#description' => $this->t('Enter your Google Places API key.'),
    ];

    $form['field_masks'] = [
      '#type' => 'select',
      '#title' => $this->t('Google Places Field Masks'),
      '#description' => $this->t('Select the fields to request from the Google Places API.'),
      '#options' => $this->getFieldMaskOptions(),
      '#multiple' => TRUE,
      '#size' => 10,
      '#default_value' => $config->get('field_masks') ?? [],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Submit handler.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $this->config(self::SETTINGS)
      ->set('api_key', $form_state->getValue('api_key'))
      ->set('field_masks', array_values(array_filter($form_state->getValue('field_masks'))))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Field mask options for Google Places API.
   */
  protected function getFieldMaskOptions() {

    return [

      'Basic Information' => [
        'places.id' => 'Place ID',
        'places.displayName' => 'Display Name',
        'places.types' => 'Types',
        'places.primaryType' => 'Primary Type',
        'places.primaryTypeDisplayName' => 'Primary Type Display Name',
      ],

      'Address Information' => [
        'places.formattedAddress' => 'Formatted Address',
        'places.shortFormattedAddress' => 'Short Formatted Address',
        'places.postalAddress' => 'Postal Address',
        'places.addressComponents' => 'Address Components',
        'places.adrFormatAddress' => 'ADR Format Address',
      ],

      'Location Information' => [
        'places.location' => 'Location (Lat/Lng)',
        'places.viewport' => 'Viewport',
        'places.plusCode' => 'Plus Code',
      ],

      'Business Information' => [
        'places.businessStatus' => 'Business Status',
        'places.priceLevel' => 'Price Level',
        'places.priceRange' => 'Price Range',
      ],

      'Ratings' => [
        'places.rating' => 'Rating',
        'places.userRatingCount' => 'User Rating Count',
      ],

      'Contact Information' => [
        'places.websiteUri' => 'Website',
        'places.nationalPhoneNumber' => 'National Phone Number',
        'places.internationalPhoneNumber' => 'International Phone Number',
      ],

      'Opening Hours' => [
        'places.regularOpeningHours' => 'Regular Opening Hours',
        'places.currentOpeningHours' => 'Current Opening Hours',
      ],

      'Media' => [
        'places.photos' => 'Photos',
        'places.iconMaskBaseUri' => 'Icon Mask Base URI',
        'places.iconBackgroundColor' => 'Icon Background Color',
      ],

      'Google Maps' => [
        'places.googleMapsUri' => 'Google Maps URI',
        'places.googleMapsLinks' => 'Google Maps Links',
      ],

    ];

  }

}
