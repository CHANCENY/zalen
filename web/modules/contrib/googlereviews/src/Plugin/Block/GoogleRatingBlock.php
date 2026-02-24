<?php

namespace Drupal\googlereviews\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\googlereviews\GetGoogleDataInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block with Google reviews rating.
 *
 * @Block(
 *   id = "googlereviews_rating",
 *   admin_label = @Translation("Google Reviews Rating"),
 *   category = @Translation("Google Reviews")
 * )
 */
class GoogleRatingBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The Get Google Data service.
   *
   * @var \Drupal\googlereviews\GetGoogleDataInterface
   */
  protected $getGoogleData;

  /**
   * Constructs a new GoogleRatingBlock object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\googlereviews\GetGoogleDataInterface $getGoogleData
   *   The Google Data service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, GetGoogleDataInterface $getGoogleData) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->getGoogleData = $getGoogleData;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('googlereviews.get_google_data')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'google_place_id' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['google_place_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Google Place ID'),
      '#default_value' => $this->configuration['google_place_id'] ?? '',
      '#description' => $this->t('The Google Maps Place ID from the location you want to see reviews for. Find the place id of you location at <a href=":link">Google Place ID Finder</a>.', [':link' => 'https://developers.google.com/maps/documentation/javascript/examples/places-placeid-finder']),
      '#required' => FALSE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['google_place_id'] = $form_state->getValue('google_place_id');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $rating = $this->getGoogleData->getGoogleReviews(
      ['rating', 'user_ratings_total', 'url'],
      5,
      'newest',
      '',
      $this->configuration['google_place_id'] ?? ''
    );
    $renderable = [];
    if (!empty($rating)) {
      $rating_percentage = ($rating['rating'] / 5) * 100;

      $renderable = [
        '#attached' => ['library' => ['googlereviews/googlereviews.rating']],
        '#theme' => 'googlereviews_rating_block',
        '#user_ratings_total' => $rating['user_ratings_total'],
        '#rating' => $rating['rating'],
        '#rating_percentage' => $rating_percentage,
        '#place_id' => $rating['place_id'],
        '#place_url' => $rating['url'],
      ];
    }
    return $renderable;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return $this->getGoogleData->getCacheMaxAge();
  }

}
