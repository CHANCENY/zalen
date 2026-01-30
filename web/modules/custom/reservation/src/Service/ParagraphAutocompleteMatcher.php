<?php

namespace Drupal\reservation\Service;

use Drupal;
use Drupal\Core\Entity\EntityAutocompleteMatcher;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\user\Entity\User;

class ParagraphAutocompleteMatcher extends EntityAutocompleteMatcher {

  public function getMatches($target_type, $selection_handler, $selection_settings, $string = ''): array {
    $flag = false;
    if ($target_type === "paragraph" && $selection_handler === "views") {

      if ($selection_settings['view']['view_name'] === "extra_menus_and_services" &&
        $selection_settings['view']['display_name'] === "entity_reference_1") {
        $flag = true;
      }

    }
    $matches = parent::getMatches($target_type, $selection_handler, $selection_settings, $string);

    if ($flag) {
      $list = [];

      foreach ($matches as $k=>$match) {
        $paragraph_id = $this->extractId($match['value']);
        if (!empty($paragraph_id)) {
          $paragraph = Paragraph::load($paragraph_id);
          $symbol = '';
          if (!empty($paragraph->get('field_service_currency')->value)) {
            $symbol = Drupal::service('reservation.currencies')->getSymbol($paragraph->get('field_service_currency')->value);
            $title = ucfirst($paragraph->get('field_is_service_or_menu')->value);
            $label = "{$title}: {$paragraph->get('field_service_short_description')->value} {$symbol}{$paragraph->get('field_service_amount')->value} ({$paragraph_id})";
            $matches[$k]['label'] = $label;
            $matches[$k]['value'] = $label;
            $list[] = [
              'value' => $label,
              'label' => $label,
            ];
          }
        }
      }

      return !empty($list) ? $list : $matches;
    }

    return $matches;
  }

  function extractId(string $value): int {
    $pos = strrpos($value, '(');
    return (int) substr($value, $pos + 1, -1);
  }

}
