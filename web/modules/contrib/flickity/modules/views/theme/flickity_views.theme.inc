<?php

/**
 * @file
 * flickity_views.theme.inc
 */

/**
 * Template preprocess Flickity Views variables.
 */
function template_preprocess_flickity_views(&$variables) {
  $items = [];
  foreach ($variables['rows'] as $row) {
    $items[] = [
      'row' => \Drupal::service('renderer')->render($row),
    ];
  }

  // Row items & instance settings.
  $variables['settings'] = $variables['view']->style_plugin->options;
  $variables['items'] = $items;

  $variables['content'] = [
    '#theme' => 'flickity',
    '#items' => $variables['items'],
    '#settings' => $variables['settings']['settings'],
  ];
}
