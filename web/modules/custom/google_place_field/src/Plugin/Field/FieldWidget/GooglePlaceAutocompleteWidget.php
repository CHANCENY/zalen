<?php

namespace Drupal\google_place_field\Plugin\Field\FieldWidget;

use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * @FieldWidget(
 *   id = "google_place_autocomplete",
 *   label = @Translation("Google Places Autocomplete"),
 *   field_types = {"google_place_business"}
 * )
 */
class GooglePlaceAutocompleteWidget extends WidgetBase {

  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

    $value = $items[$delta]->name ?? '';
    $address = $items[$delta]->address ?? '';
    $map_url = $items[$delta]->map_url ?? '';

    $element['search'] = [
      '#type' => 'textfield',
      '#title' => $this->t($element['#title']),
      '#default_value' => $value,
      '#autocomplete_route_name' => 'google_place_field.autocomplete',
      '#attributes' => ['class' => ['google-place-search']],
    ];

    $element['place_id'] = ['#type' => 'hidden', '#default_value' => $items[$delta]->place_id ?? '', '#attributes' => ['class' => ['google-place-id']]];
    $element['address'] = ['#type' => 'hidden', '#default_value' => $address, '#attributes' => ['class' => ['google-place-address']]];
    $element['map_url'] = ['#type' => 'hidden', '#default_value' => $map_url, '#attributes' => ['class' => ['google-place-map-url']]];

    $element['preview'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['google-place-preview']],
      '#markup' => $this->buildPreviewMarkup($value, $address, $map_url),
    ];

    $element['#attached']['library'][] = 'google_place_field/google_place_widget';

    return $element;
  }

  protected function buildPreviewMarkup(string $name = '', string $address = '', string $map_url = ''): string {
    if (empty($name) || empty($map_url)) {
      return '';
    }

    return '<div class="google-place-preview-wrapper">
      <strong>' . $name . '</strong><br/>
      <div>' . $address . '</div>
      <iframe src="' . $map_url . '" width="100%" height="300" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
    </div>';
  }
}
