<?php

namespace Drupal\zaal_condities\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Url;
use Drupal\taxonomy\Entity\Term;

/**
 * @FieldFormatter(
 *   id = "room_layout_capacity_formatter",
 *   label = @Translation("Room layouts with icon and capacity"),
 *   field_types = {"room_layout_capacity"}
 * )
 */
class RoomLayoutCapacityFormatter extends FormatterBase {

  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $item) {

      if (empty($item->value)) {
        continue;
      }

      $data = json_decode($item->value, TRUE);
      if (!is_array($data)) {
        continue;
      }

      foreach ($data as $row) {

        // -------------------------------
        // DEFENSIEVE CONTROLES
        // -------------------------------
        if (
          empty($row['layout']) ||
          !is_numeric($row['layout']) ||
          empty($row['capacity'])
        ) {
          continue;
        }

        $term = Term::load((int) $row['layout']);
        if (!$term) {
          continue;
        }

        // -------------------------------
        // ICON
        // -------------------------------
        $icon = NULL;
        if (!$term->get('field_room_layout_icon')->isEmpty()) {
          $icon = $term->get('field_room_layout_icon')->view([
            'type' => 'image',
            'label' => 'hidden',
            'settings' => [
              'image_style' => 'thumbnail',
            ],
          ]);
        }

        // -------------------------------
        // GEBRUIK (HOVER)
        // -------------------------------
        $gebruik = '';
        if (!$term->get('field_gebruik')->isEmpty()) {
          $gebruik = $term->get('field_gebruik')->value;
        }

        // -------------------------------
        // LINK NAAR TERM
        // -------------------------------
        $url = Url::fromRoute('entity.taxonomy_term.canonical', [
          'taxonomy_term' => $term->id(),
        ]);

        // -------------------------------
        // RENDER OUTPUT
        // -------------------------------
        $elements[] = [
          '#type' => 'link',
          '#title' => [
            '#markup' =>
              '<div class="room-layout-item" title="' . htmlspecialchars($gebruik) . '">' .
                '<span class="room-layout-icon">' .
                  ($icon ? \Drupal::service('renderer')->renderPlain($icon) : '') .
                '</span>' .
                '<span class="room-layout-label">' .
                  $term->label() .
                '</span>' .
                '<span class="room-layout-capacity">' .
                  (int) $row['capacity'] .
                '</span>' .
              '</div>',
          ],
          '#url' => $url,
          '#options' => [
            'html' => TRUE,
          ],
        ];
      }
    }

    return $elements;
  }

}

