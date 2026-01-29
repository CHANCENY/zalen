<?php

namespace Drupal\flickity;

use Drupal\Core\Render\Element\RenderCallbackInterface;

/**
 * Provides a trusted callback to alter the flickity output.
 *
 * @see theme_flickity()
 */
class FlickityBuilder implements RenderCallbackInterface {

  /**
   * Sets the flickity - #pre_render callback.
   */
  public static function preRender(array $element) {
    $items = [];
    foreach ($element['#output']['items'] as $key => $item) {
      $items[] = [
        '#theme' => 'flickity_item',
        '#item' => $item['row'],
        '#attributes' => [
          'class' => ['gallery-cell', 'item-' . $key],
        ],
      ];
    }

    $group = $element['#output']['settings'];

    // Build wrapper with Flickity items.
    $build = [
      '#output' => $items,
      '#attributes' => ['class' => [$group, 'flickity']],
      '#theme_wrappers' => ['flickity_wrapper'],
    ];

    // Provide pre render alter.
    \Drupal::moduleHandler()->alter('pre_render', $build);

    // Render the complete Flickity element.
    $element['#children'] = \Drupal::service('renderer')->render($build);

    return $element;
  }

}
