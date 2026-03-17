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

      'Identifiers & Names' => [
        'id' => 'Place ID',
        'name' => 'Resource Name',
        'displayName' => 'Display Name',
        'moved_place' => 'Moved Place Resource Name',
        'moved_place_id' => 'Moved Place ID',
      ],

      'Basic & Classification' => [
        'types' => 'Types',
        'primaryType' => 'Primary Type',
        'primaryTypeDisplayName' => 'Primary Type Display Name',
      ],

      'Address Information' => [
        'formattedAddress' => 'Formatted Address',
        'shortFormattedAddress' => 'Short Formatted Address',
        'postalAddress' => 'Postal Address',
        'adrFormatAddress' => 'ADR Format Address',
        'addressComponents' => 'Address Components',
        'addressDescriptor' => 'Address Descriptor',
        'plusCode' => 'Plus Code',
      ],

      'Geometry & Location' => [
        'location' => 'Location (Lat/Lng)',
        'viewport' => 'Viewport',
      ],

      'Business Status & Details' => [
        'businessStatus' => 'Business Status',
        'pureServiceAreaBusiness' => 'Pure Service Area Business',
        'containingPlaces' => 'Containing Places',
        'subDestinations' => 'Sub Destinations',
      ],

      'Contact Information' => [
        'internationalPhoneNumber' => 'International Phone Number',
        'nationalPhoneNumber' => 'National Phone Number',
        'websiteUri' => 'Website URI',
      ],

      'Opening Hours' => [
        'regularOpeningHours' => 'Regular Opening Hours',
        'currentOpeningHours' => 'Current Opening Hours',
        'regularSecondaryOpeningHours' => 'Regular Secondary Opening Hours',
        'currentSecondaryOpeningHours' => 'Current Secondary Opening Hours',
      ],

      'Price & Ratings' => [
        'priceLevel' => 'Price Level',
        'priceRange' => 'Price Range',
        'rating' => 'Rating',
        'userRatingCount' => 'User Rating Count',
      ],

      'Maps & Links' => [
        'googleMapsUri' => 'Google Maps URI',
        'googleMapsLinks' => 'Google Maps Links',
      ],

      'Icons & Media' => [
        'photos' => 'Photos',
        'iconMaskBaseUri' => 'Icon Mask Base URI',
        'iconBackgroundColor' => 'Icon Background Color',
      ],

      'Timezone & Misc' => [
        'timeZone' => 'Timezone',
        'utcOffsetMinutes' => 'UTC Offset Minutes',
      ],

      'Atmosphere & Amenities' => [
        'allowsDogs' => 'Allows Dogs',
        'curbsidePickup' => 'Curbside Pickup',
        'delivery' => 'Delivery',
        'dineIn' => 'Dine In',
        'editorialSummary' => 'Editorial Summary',
        'evChargeAmenitySummary' => 'EV Charge Amenity Summary',
        'evChargeOptions' => 'EV Charge Options',
        'fuelOptions' => 'Fuel Options',
        'goodForChildren' => 'Good For Children',
        'goodForGroups' => 'Good For Groups',
        'goodForWatchingSports' => 'Good For Watching Sports',
        'liveMusic' => 'Live Music',
        'menuForChildren' => 'Menu For Children',
        'neighborhoodSummary' => 'Neighborhood Summary',
        'parkingOptions' => 'Parking Options',
        'paymentOptions' => 'Payment Options',
        'outdoorSeating' => 'Outdoor Seating',
        'reservable' => 'Reservable',
        'restroom' => 'Restroom',
      ],

      'Reviews & Summaries' => [
        'reviews' => 'Reviews',
        'reviewSummary' => 'Review Summary',
        'routingSummaries' => 'Routing Summaries',
      ],

    ];

  }

}
