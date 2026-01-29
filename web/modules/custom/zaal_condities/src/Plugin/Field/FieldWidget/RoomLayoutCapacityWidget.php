<?php

namespace Drupal\zaal_condities\Plugin\Field\FieldWidget;

use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\Entity\Term;

/**
 * @FieldWidget(
 *   id = "room_layout_capacity_widget",
 *   label = @Translation("Room layout selector with capacity"),
 *   field_types = {"room_layout_capacity"}
 * )
 */
class RoomLayoutCapacityWidget extends WidgetBase {

  public function formElement($items, $delta, array $element, array &$form, FormStateInterface $form_state) {

    /* ------------------------------------------------------------
     * STORED VALUE (JSON)
     * ------------------------------------------------------------ */

    $stored = [];
    if (!empty($items[$delta]->value)) {
      $stored = json_decode($items[$delta]->value, TRUE) ?: [];
    }

    /* ------------------------------------------------------------
     * LOAD TAXONOMY
     * ------------------------------------------------------------ */

    $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $tree = $storage->loadTree('zaalopstellingen');

    $groups = [];
    $layouts = [];

    foreach ($tree as $term) {
      if (empty($term->parents[0])) {
        // Hoofdgroep
        $groups[$term->tid] = $term->name;
      }
      else {
        // Effectieve layout
        $layouts[] = $term;
      }
    }

    /* ------------------------------------------------------------
     * ROOT ELEMENT
     * ------------------------------------------------------------ */

    $element['#type'] = 'container';
    $element['#tree'] = TRUE;
    $element['#attributes']['class'][] = 'room-layout-picker';
    $element['#attached']['library'][] = 'zaal_condities/widget';

    /* ------------------------------------------------------------
     * ENIGE FIELD INPUT (JSON)
     * ------------------------------------------------------------ */

    $element['value'] = [
      '#type' => 'hidden',
      '#default_value' => json_encode($stored),
    ];

    /* ------------------------------------------------------------
     * SUMMARY (UI)
     * ------------------------------------------------------------ */

    $element['summary'] = [
      '#markup' => '<div class="room-layout-summary"></div>',
    ];

    /* ------------------------------------------------------------
     * TOGGLE
     * ------------------------------------------------------------ */

    $element['toggle'] = [
      '#type' => 'html_tag',
      '#tag' => 'button',
      '#value' => $this->t('+ Add layout'),
      '#attributes' => [
        'type' => 'button',
        'class' => ['room-layout-toggle'],
      ],
    ];

    /* ------------------------------------------------------------
     * CHIPS
     * ------------------------------------------------------------ */

    $element['chips'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['room-layout-chips']],
    ];

    $element['chips']['all'] = [
      '#markup' => '<span class="chip active" data-filter="all">' . $this->t('All') . '</span>',
    ];

    foreach ($groups as $gid => $name) {
      $element['chips'][$gid] = [
        '#markup' => '<span class="chip" data-filter="' . $gid . '">' . $this->t($name) . '</span>',
      ];
    }

    /* ------------------------------------------------------------
     * LAYOUT ROWS (UI ONLY)
     * ------------------------------------------------------------ */

    foreach ($layouts as $layout) {
      $tid = (int) $layout->tid;
      $term = Term::load($tid);

      // Icon
      $icon = '';
      if ($term && !$term->get('field_room_layout_icon')->isEmpty()) {
        $rendered = \Drupal::service('renderer')->renderPlain(
          $term->get('field_room_layout_icon')->view([
            'type' => 'image',
            'label' => 'hidden',
          ])
        );
        if (preg_match('/<img[^>]+>/i', $rendered, $m)) {
          $icon = $m[0];
        }
      }

      $parents = $layout->parents;
      $parent_tid = !empty($parents) ? (int) $parents[0] : 0;

      $element['layout_' . $tid] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['room-layout-row'],
          'data-tid' => $tid,
          'data-label' => $term->label(),
          'data-group' => $parent_tid,
          'data-icon' => $icon,
        ],
        'preview' => [
          '#markup' =>
            '<span class="room-layout-icon">' . $icon . '</span>' .
            '<span class="room-layout-label">' . $term->label() . '</span>',
            //'<span class="room-layout-select-indicator" aria-hidden="true">＋</span>',
        ],
      ];
    }

    return $element;
  }

  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    // JSON-value mag ongewijzigd worden opgeslagen
    return $values;
  }

}
