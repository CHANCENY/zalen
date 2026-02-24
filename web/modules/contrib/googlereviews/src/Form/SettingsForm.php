<?php

namespace Drupal\googlereviews\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Google Reviews settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'googlereviews_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['googlereviews.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['google_api_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Google Maps Places API URL'),
      '#default_value' => $this->config('googlereviews.settings')->get('google_api_url'),
      '#description' => $this->t('The Google Maps Places API URL.'),
      '#required' => TRUE,
    ];
    $form['google_auth_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Google Auth key'),
      '#default_value' => $this->config('googlereviews.settings')->get('google_auth_key'),
      '#description' => $this->t('Your Google API key from Google Maps API. To obtain a key you need to create a project in the Google Cloud Console, <a href=":link">see documentation</a>.', [':link' => 'https://developers.google.com/maps/documentation/embed/get-api-key']),
      '#required' => TRUE,
    ];
    $form['google_place_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Google Place ID'),
      '#default_value' => $this->config('googlereviews.settings')->get('google_place_id'),
      '#description' => $this->t('The Google Maps Place ID from the location you want to see reviews for. Find the place id of you location at <a href=":link">Google Place ID Finder</a>.', [':link' => 'https://developers.google.com/maps/documentation/javascript/examples/places-placeid-finder']),
      '#required' => TRUE,
    ];
    $form['cache_max_age'] = [
      '#type' => 'number',
      '#title' => $this->t('Cache max age'),
      '#default_value' => $this->config('googlereviews.settings')->get('cache_max_age'),
      '#min' => 0,
      '#max' => 31622400,
      '#step' => 1,
      '#description' => $this->t('in seconds'),
      '#required' => TRUE,
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('googlereviews.settings')
      ->set('google_api_url', $form_state->getValue('google_api_url'))
      ->set('google_auth_key', $form_state->getValue('google_auth_key'))
      ->set('google_place_id', $form_state->getValue('google_place_id'))
      ->set('cache_max_age', $form_state->getValue('cache_max_age'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
