<?php

/**
 * @file
 * flickity.theme.inc
 */

use Drupal\Core\Template\Attribute;
use Drupal\flickity\FlickityBuilder;

/**
 * Template preprocess Flickity variables.
 */
function template_preprocess_flickity(&$variables) {
  // Load Flickity settings.
  $group = flickity_settings($variables['settings']);

  $variables['content'] = [
    '#output' => [
      'settings' => $variables['settings'],
      'items' => $variables['items']
    ],
    '#pre_render' => [[FlickityBuilder::class, 'preRender']],
  ];

  // Attach Flickity javascript settings.
  $variables['#attached'] = [
    'drupalSettings' => [
      'flickity' => [
        $group['id'] => ['settings' => $group['settings']],
      ]
    ],
  ];
}

/**
 * Template preprocess Flickity item variables.
 */
function template_preprocess_flickity_item(&$variables) {
  $variables['attributes'] = new Attribute($variables['attributes']);
}

/**
 * Template preprocess Flickity wrapper variables.
 */
function template_preprocess_flickity_wrapper(&$variables) {
  $variables['attributes'] = new Attribute($variables['attributes']);
}
