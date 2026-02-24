<?php

namespace Drupal\googlereviews\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\googlereviews\GetGoogleDataInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block with Google reviews list.
 *
 * @Block(
 *   id = "googlereviews_reviews",
 *   admin_label = @Translation("Google Reviews List"),
 *   category = @Translation("Google Reviews")
 * )
 */
class GoogleReviewsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The Get Google Data service.
   *
   * @var \Drupal\googlereviews\GetGoogleDataInterface
   */
  protected $getGoogleData;

  /**
   * Constructs a new GoogleReviewsBlock object.
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
      'max_google_reviews' => 5,
      'google_reviews_sorting' => 'newest',
      'google_place_id' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {

    $max_rows_options = [
      '1' => '1',
      '2' => '2',
      '3' => '3',
      '4' => '4',
      '5' => '5',
    ];

    $form['max_google_reviews'] = [
      '#type' => 'select',
      '#title' => $this->t('Amount of reviews'),
      '#options' => $max_rows_options,
      '#description' => $this->t('The amount of reviews that need to be shown in this block.'),
      '#default_value' => $this->configuration['max_google_reviews'] ?? 5,
    ];

    $form['google_reviews_sorting'] = [
      '#type' => 'select',
      '#title' => $this->t('Reviews sorting'),
      '#options' => [
        'newest' => $this->t('Newest'),
        'most_relevant' => $this->t('Most relevant (according to Google)'),
      ],
      '#default_value' => $this->configuration['google_reviews_sorting'] ?? 'newest',
    ];

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
    $this->configuration['max_google_reviews'] = $form_state->getValue('max_google_reviews');
    $this->configuration['google_reviews_sorting'] = $form_state->getValue('google_reviews_sorting');
    $this->configuration['google_place_id'] = $form_state->getValue('google_place_id');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $reviews = $this->getGoogleData->getGoogleReviews(
      ['rating', 'reviews'],
      $this->configuration['max_google_reviews'],
      $this->configuration['google_reviews_sorting'],
      '',
      $this->configuration['google_place_id'] ?? ''
    );

    $renderable = [];
    if (!empty($reviews)) {
      $renderable = [
        '#attached' => ['library' => ['googlereviews/googlereviews.reviews']],
        '#theme' => 'googlereviews_reviews_block',
        '#reviews' => $reviews['reviews'],
        '#place_id' => $reviews['place_id'],
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
