<?php

namespace Drupal\google_place_field\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\google_place_field\GoogleApiCaller;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Provides a Google Place Map Block.
 *
 * @Block(
 *   id = "google_place_map_block",
 *   admin_label = @Translation("Google Place Map"),
 * )
 */
class GooglePlaceMapBlock extends BlockBase implements ContainerFactoryPluginInterface {

  protected $configFactory;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $placeId = $this->determineEntityGooglePlaceId();
    $googleApiCaller = new GoogleApiCaller(\Drupal::config('google_place_field.settings'));
    $embed_url = $googleApiCaller->getGoogleMapEmbedUrl($placeId);

    return [
      '#theme' => 'google_place_map',
      '#map_url' => $embed_url,
      '#attached' => [
        'library' => [
          'google_place_field/google_place_map',
        ],
      ],
    ];
  }

  private function determineEntityGooglePlaceId() {
    $node = \Drupal::routeMatch()->getParameter('node');
    $googlePlaceId = "";
    if ($node instanceof Node) {

      if ($node->bundle() === 'zaal') {
        $location = $node->get('field_bedrijf_zaal')->entity;
        if ($location instanceof Node && $location->hasField('field_google_place_id')) {
          $googlePlaceId = $location->get('field_google_place_id')->place_id;
        }
      }
      elseif ($node->bundle() === 'bedrijf' && $node->hasField('field_google_place_id')) {
        $googlePlaceId = $node->get('field_google_place_id')->place_id;
      }

    }
    return $googlePlaceId;
  }


}
