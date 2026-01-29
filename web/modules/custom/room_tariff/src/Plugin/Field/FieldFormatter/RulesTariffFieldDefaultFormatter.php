<?php

/**
 * @file
 * Contains \Drupal\room_tariff\Plugin\Field\FieldFormatter\RulesTariffFieldDefaultFormatter.
 */

namespace Drupal\room_tariff\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;

/** *
 * @FieldFormatter(
 *   id = "tariff_rules_default_formatter",
 *   label = @Translation("Rules tariff field default"),
 *   field_types = {
 *     "tariff_rules"
 *   }
 * )
 */
class RulesTariffFieldDefaultFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {

    $settings = $this->getFieldSettings();
    $settings['pattern_formatter'] = array (
      'per_hour' => $this->t('per hour'),
      'inan_day' => $this->t('per day'),
      'i_person' => $this->t('per person'),
    );
    $element = [];
    $discounts = [];
    $restrictions = [];


    foreach ($items as $delta => $item) {

      $value = $item->getValue();
      if ($value['rule_type'] == 'minprice') {
        // The minimum order amount @pattern is <span>@time</span> @time_format.
        $restrictions[] = $this->t('The minimum order amount is <span>@time</span> @time_format.', [
          '@pattern' => $settings['pattern_formatter'][$value['pattern_tariff']],
          '@price' => number_format($value['price'] / 100, 2, '.', ''),
          '@time' => $value['pattern_tariff'] == 'inan_day' ? $value['span_time']/86400 : $value['span_time']/3600,
          '@time_format' => $value['pattern_tariff'] == 'inan_day' ? $this->t('days') : $this->t('hours'),
        ]);
      } else if ($value['rule_type'] == 'if_large') {
        $discounts[] = $this->t('If your order is more than @time @time_format, the price becomes <span>@price</span>.', [
          '@time' => $value['pattern_tariff'] == 'days' ? $value['span_time'] / 86400 : ($value['pattern_tariff'] == 'i_person' ? $value['span_time'] . ' person' : $value['span_time'] / 3600),
          '@time_format' => $this->t(strtolower($settings['subtype_rule_if_more'][$value['pattern_tariff']])),
          '@price' => number_format($value['price'] / 100, 2, '.', ''),
        ]);
      };

    };

    if (!empty($discounts)) {
      $content = [
        '#theme' => 'item_list',
        '#list_type' => 'ul',
        '#title' => $this->t('Discount rules'),
        '#items' => $discounts,
        '#attributes' => ['class' => 'item-discount'],
        '#wrapper_attributes' => ['class' => 'wrap-discount'],
      ];
      $element[] = $content;
    };

    if (!empty($restrictions)) {
      $content = [
        '#theme' => 'item_list',
        '#list_type' => 'ul',
        '#title' => $this->t('Restrictions rules'),
        '#items' => $restrictions,
        '#attributes' => ['class' => 'item-restriction'],
        '#wrapper_attributes' => ['class' => 'wrap-restriction'],
      ];
      $element[] = $content;
    };

    return $element;
  }

}
