<?php

/**
 * @file
 * Contains \Drupal\room_tariff\Plugin\Field\FieldFormatter\TariffFieldDefaultFormatter.
 */

namespace Drupal\room_tariff\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\file\Entity\File;

/** *
 * @FieldFormatter(
 *   id = "tariff_field_default_formatter",
 *   label = @Translation("Tariff field default"),
 *   field_types = {
 *     "room_tariff"
 *   }
 * )
 */
class TariffFieldDefaultFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];
    $options = $this->getFieldSetting('tariff_type');

    $service_markup = null;
    foreach ($items as $delta => $item) {
      $file_entity = File::load((int)$item->getValue()['services_images']);
      $file_uri = $file_entity?->uri?->value;
      if($file_uri){
        $file_url_generator = \Drupal::service('file_url_generator');
        $image_url = $file_url_generator->generateAbsoluteString($file_uri);
        $image = $image_url ? '<img class="additional-services-image" src="'.$image_url.'" width=400 height=400>' : NULL;
      }else{
        $image = NULL;
      }
      $element[$delta] = [
        '#type' => 'markup',
        '#markup' => $options[$item->getValue()['pattern']] . ': ' . $item->getValue()['price']/100 . ' ' . $item->getValue()['currency'],
        'sortable' => $item->getValue()['pattern'],
      ];
      if ($item->getValue()['pattern'] == 'interval') {
        $date[] = DrupalDateTime::createFromTimestamp($item->getValue()['begin'])->format('Y-m-d H:i:s');
        $date[] = DrupalDateTime::createFromTimestamp($item->getValue()['end'])->format('Y-m-d H:i:s');
        $label_offer = isset($item->getValue()['special_offer']) ? $item->getValue()['special_offer'] : NULL;
        $element[$delta]['#markup'] .= ' from '.$date[0].' to '.$date[1];
        $element[$delta]['#markup'] .= ', <strong>Label</strong> : '.$label_offer;
      };

      if ($item->getValue()['pattern'] == 'i_person' || $item->getValue()['pattern'] == 'per_hour' || $item->getValue()['pattern'] == 'per_day') {
        $file_entity_person = File::load((int)$item->getValue()['person_images']);
        $file_uri_person = $file_entity_person?->uri?->value;
        if($file_uri_person){
          $file_url_generator = \Drupal::service('file_url_generator');
          $image_url_person = $file_url_generator->generateAbsoluteString($file_uri_person);
          $image_person = $image_url_person ? '<img src="'.$image_url_person.'" width=400 height=400>' : NULL;
        }else{
          $image_person = NULL;
        }
        $element[$delta]['#markup'] .= isset($item->getValue()['person_label']) ? '&nbsp; <strong>Label</strong>: ' . $item->getValue()['person_label'] : NULL;
        $element[$delta]['#markup'] .= isset($item->getValue()['person_description']) ? '&nbsp;<strong>Description</strong>: '.$item->getValue()['person_description'] : NULL;
        $element[$delta]['#markup'] .= isset($image_person) ? '<br>'.$image_person : NULL;
      };

      if ($item->getValue()['pattern'] == 'services') {
        $services = $item->getValue()['services'];
        $element[$delta]['#markup'] .= ' for service "'.($services ? $services : '?').'"';
        $element[$delta]['#markup'] .= $item->getValue()['require'] ? ' require' : '';
        $element[$delta]['#markup'] .= isset($item->getValue()['services']) ? ', '.trim($item->getValue()['services'],'"') : NULL;
        $element[$delta]['#markup'] .= isset($item->getValue()['services_minimum_order']) ? ', <strong>Minimum</strong>: ' . $item->getValue()['services_minimum_order'] : NULL;
        $element[$delta]['#markup'] .= isset($image) ? ', <br>'.$image : NULL;
        $element[$delta]['raw'] = [
          'name' => $item->getValue()['services'] ?? null,
          'mini' => $item->getValue()['services_minimum_order'],
          'price' => ($item->getValue()['price'] / 100) ?? 0,
          'currency' => $item->getValue()['currency'],
          'description' => $item->getValue()['service_description'],
          'image' => $image,
        ];
      };
      if ($item->getValue()['pattern'] == 'rang_dat') {
        $range_type = $item->getValue()['range_type'];
        switch ($range_type) {
          case "tim":
            $time_from = (new \DateTime('today'))->modify('+ '.$item->getValue()['begin'].' seconds')->format('H:i');
            $time_to = (new \DateTime('today'))->modify('+ '.$item->getValue()['end'].' seconds')->format('H:i');
            $element[$delta]['#markup'] .= ' Every day from '.$time_from.' to '.$time_to.'.';
          break;
          case "day":
            $range_data = explode(';',$item->getValue()['range_data']);
            $week = array_combine([7,1,2,3,4,5,6], \Drupal\Core\Datetime\DateHelper::weekDays(TRUE));
            $time_from = (new \DateTime('today'))->modify('+ '.$item->getValue()['begin'].' seconds')->format('H:i');
            $time_to = (new \DateTime('today'))->modify('+ '.$item->getValue()['end'].' seconds')->format('H:i');
            $element[$delta]['#markup'] .= ' Every week on '.implode(', ', array_intersect_key($week, array_flip($range_data))).' from '.$time_from.' to '.$time_to.'.';
          break;
          case "mon":
            $range_data = explode(';',$item->getValue()['range_data']);
            $time_from = (new \DateTime('today'))->modify('+ '.$item->getValue()['begin'].' seconds')->format('H:i');
            $time_to = (new \DateTime('today'))->modify('+ '.$item->getValue()['end'].' seconds')->format('H:i');
            $element[$delta]['#markup'] .= ' Every month on the '.implode(', ', $range_data).' from '.$time_from.' to '.$time_to.'.';
          break;
          case "yea":
            $range_data = explode(';',$item->getValue()['range_data']);
            $month = \Drupal\Core\Datetime\DateHelper::monthNames(TRUE);
            $time_from = (new \DateTime('today'))->modify('+ '.$item->getValue()['begin'].' seconds')->format('H:i');
            $time_to = (new \DateTime('today'))->modify('+ '.$item->getValue()['end'].' seconds')->format('H:i');
            $element[$delta]['#markup'] .= ' Every year in '.implode(', ', array_intersect_key($month, array_flip($range_data))).' from '.$time_from.' to '.$time_to.'.';
          break;
        };
        //$dat = (new \DateInterval('PT'.$item->getValue()['begin'].'S'))->format('%H:%I');
      };
    }

    $services_found = [];
    $services_indexes = [];
    $service_markup = [
      '#type' => 'markup',
      '#markup' => '<br><strong>Additional services</strong></br>',
    ];
    foreach ($element as $delta => $service) {
      if($service['sortable'] === 'services') {
        $services_found[] = $service['raw'];
        $services_indexes[] = $delta;
      }
      unset($element[$delta]['sortable']);
    }
    if($services_found) {
      $service_markup['#markup'] .= "<ul class='list-unstyled'>";
      foreach ($services_found as $service) {
        $img = $service['image'] ? $service['image'] : NULL;
        $service_markup['#markup'] .= "<br><li>
                                       <strong>Name</strong> {$service['name']} &nbsp;
                                       <strong>Minimum order:</strong> {$service['mini']} &nbsp;
                                       <strong>Price:</strong> {$service['price']} {$service['currency']}<br>
                                       <strong>Description:</strong> {$service['description']}<br>
                                       $img
                                       </li>";
      }
      $service_markup['#markup'] .= "</ul><br>";
      foreach ($services_indexes as $index => $service) {
        unset($element[$service]);
      }
      $element[] = $service_markup;
    }

    return $element;
  }

}


