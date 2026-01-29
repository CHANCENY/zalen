<?php

/**
 * @file
 * Contains \Drupal\room_tariff\Plugin\Field\FieldFormatter\TariffFieldPartsFormatter.
 */

namespace Drupal\room_tariff\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Datetime\DrupalDateTime;

/** *
 * @FieldFormatter(
 *   id = "tariff_field_parts_formatter",
 *   label = @Translation("Parts element tariff field default"),
 *   field_types = {
 *     "room_tariff"
 *   }
 * )
 */
class TariffFieldPartsFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   *
   */
  public static function defaultSettings() {
    return [
      'default_format_date' => 'Y-m-d H:i:s',
      'output_fields' => ['per_hour',],
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   *
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);

    $field_settings = $this->getFieldSetting('tariff_type');

    $elements['output_fields'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Output the fields'),
      '#options' => [],
      '#default_value' => (array) $this->getSettings()['output_fields'],
      '#description' => $this->t('Select the fields to be displayed.'),

    ];
    foreach ($field_settings as $k => $v) {
      $elements['output_fields']['#options'][$k] = $this->t($v);
    };

    if (array_key_exists('interval', $elements['output_fields']['#options'])) {
    $elements['default_format_date'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Default format date'),
      '#field_suffix' => 'input format date',
      '#size' => 30,
      '#default_value' => $this->getSetting('default_format_date'),
      '#maxlength' => 32,

    );
    };

    return $elements;
  }

  /**
   * {@inheritdoc}
   *
   */
  public function settingsSummary() {
    $summary = [];
    $settings = $this->getSettings();
    $field_settings = $this->getFieldSetting('tariff_type');

    $summary[] = $this->t('Output fields: @output_fields.', array('@output_fields' => implode(', ', array_intersect_key($field_settings, array_flip($settings['output_fields']) ) ) ) );
    $summary[] = $this->t('Default format date: @default_format_date.', array('@default_format_date' => $settings['default_format_date']));

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = array();
    $settings = $this->getSettings();

    $element = [
      '#type' => 'container',
      '#title' => $this->t('Cost billing'),
      '#attributes' => array (
        'class' => 'tariff-cost',
        //'style' => 'background-color: beige',
      ),
    ];
    $event_value = [];
    $value_interval = [];
    $value_range = [];
    $value_services = [];
    foreach ($items as $item) {

      if (in_array($item->getValue()['pattern'], $settings['output_fields'])) {

        switch ($item->getValue()['pattern']) {
          case 'per_hour':
            $event_value['per_hour'] = [
              '#type' => 'html_tag',
              '#tag' => 'p',
              '#value' => $this->t(
                'Cost per hour <span>@amount</span> <span class="tariff-item-currency">@currency</span>.', [
                  '@amount' => number_format($item->getValue()['price']/100, 2, '.', ''),
                  '@currency' => $item->getValue()['currency'],
                ]
              ),
            ];
            break;
          case 'inan_day':
            $event_value['inan_day'] = [
              '#type' => 'html_tag',
              '#tag' => 'p',
              '#value' => $this->t(
                'Cost per day <span>@amount</span> <span class="tariff-item-currency">@currency</span>.', [
                  '@amount' => number_format($item->getValue()['price']/100, 2, '.', ''),
                  '@currency' => $item->getValue()['currency'],
                ]
              ),
            ];
            break;
          case 'i_person':
            $event_value['i_person'] = [
              '#type' => 'html_tag',
              '#tag' => 'p',
              '#value' => $this->t(
                'Cost per person <span>@amount</span> <span class="tariff-item-currency">@currency</span> for 1 person.', [
                  '@amount' => number_format($item->getValue()['price']/100, 2, '.', ''),
                  '@currency' => $item->getValue()['currency'],
                ]
              ),
            ];
            break;
          case 'interval':
            $item_interval = $this->t(
              'For the period from <span>@interval_begin</span> to <span>@interval_end</span> will cost <span>@amount</span> <span class="tariff-item-currency">@currency</span>.', [
                '@interval_begin' => DrupalDateTime::createFromTimestamp($item->getValue()['begin'])->format($settings['default_format_date']),
                '@interval_end' => DrupalDateTime::createFromTimestamp($item->getValue()['end'])->format($settings['default_format_date']),
                '@amount' => number_format($item->getValue()['price']/100, 2, '.', ''),
                '@currency' => $item->getValue()['currency'],
              ]
            );
            $value_interval['#items'][] =  $item_interval;
            break;
          case 'rang_dat':

            $range_type = $item->getValue()['range_type'];
            switch ($range_type) {
              case "tim":
                $item_rang = $this->t(
                  'Every day from <span>@range_begin</span> to <span>@range_end</span> is <span>@amount</span> <span class="tariff-item-currency">@currency</span>.', [
                    '@range_begin' => (new \DateTime('today'))->modify('+ '.$item->getValue()['begin'].' seconds')->format('H:i'),
                    '@range_end' => (new \DateTime('today'))->modify('+ '.$item->getValue()['end'].' seconds')->format('H:i'),
                    '@amount' => number_format($item->getValue()['price']/100, 2, '.', ''),
                    '@currency' => $item->getValue()['currency'],
                  ]
                );
              break;
              case "day":
                $range_data = explode(';',$item->getValue()['range_data']);
                $week = array_combine([7,1,2,3,4,5,6], \Drupal\Core\Datetime\DateHelper::weekDays(TRUE));
                $item_rang = $this->t(
                  'Every week on <span>@week</span> from <span>@range_begin</span> to <span>@range_end</span> is <span>@amount</span> <span class="tariff-item-currency">@currency</span>.', [
                    '@range_begin' => (new \DateTime('today'))->modify('+ '.$item->getValue()['begin'].' seconds')->format('H:i'),
                    '@range_end' => (new \DateTime('today'))->modify('+ '.$item->getValue()['end'].' seconds')->format('H:i'),
                    '@week' => implode(', ', array_intersect_key($week, array_flip($range_data))),
                    '@amount' => number_format($item->getValue()['price']/100, 2, '.', ''),
                    '@currency' => $item->getValue()['currency'],
                  ]
                );
              break;
              case "mon":
                $range_data = explode(';',$item->getValue()['range_data']);
                $item_rang = $this->t(
                  'Every month on the <span>@month</span> from <span>@range_begin</span> to <span>@range_end</span> is <span>@amount</span> <span class="tariff-item-currency">@currency</span>.', [
                    '@range_begin' => (new \DateTime('today'))->modify('+ '.$item->getValue()['begin'].' seconds')->format('H:i'),
                    '@range_end' => (new \DateTime('today'))->modify('+ '.$item->getValue()['end'].' seconds')->format('H:i'),
                    '@month' => implode(', ', $range_data),
                    '@amount' => number_format($item->getValue()['price']/100, 2, '.', ''),
                    '@currency' => $item->getValue()['currency'],
                  ]
                );
              break;
              case "yea":
                $range_data = explode(';',$item->getValue()['range_data']);
                $month = \Drupal\Core\Datetime\DateHelper::monthNames(TRUE);
                $item_rang = $this->t(
                  'Every year in <span>@yea</span> from <span>@range_begin</span> to <span>@range_end</span> is <span>@amount</span> <span class="tariff-item-currency">@currency</span>.', [
                    '@range_begin' => (new \DateTime('today'))->modify('+ '.$item->getValue()['begin'].' seconds')->format('H:i'),
                    '@range_end' => (new \DateTime('today'))->modify('+ '.$item->getValue()['end'].' seconds')->format('H:i'),
                    '@yea' => implode(', ', array_intersect_key($month, array_flip($range_data))),
                    '@amount' => number_format($item->getValue()['price']/100, 2, '.', ''),
                    '@currency' => $item->getValue()['currency'],
                  ]
                );
              break;
            };

            $value_range['#items'][] =  $item_rang;
            break;
          case 'services':
            $item_services = $this->t(
              'The cost for the service: "@services_str" is <span>@amount</span> <span class="tariff-item-currency">@currency</span>.'.($item->getValue()['require'] ? ' @require!' : ''), [
                '@services_str' => $item->getValue()['services'],
                '@amount' => number_format($item->getValue()['price']/100, 2, '.', ''),
                '@currency' => $item->getValue()['currency'],
                '@require' => $item->getValue()['require'] ? $this->t('Require') : '',
              ]
            );
            $value_services['#items'][] =  $item_services;
            break;
          //default:
        };
      };

    };

    if (!empty($event_value['per_hour']) || !empty($event_value['inan_day']) || !empty($event_value['i_person'])) {
      $event_value = $event_value + [
        '#type' => 'container',
        '#title' => $this->t('Base cost'),
      ];
      $element[] = $event_value;
    };

    if ($value_interval) {
      $value_interval['#theme'] = 'item_list';
      $value_interval['#list_type'] = 'ul';
      $value_interval['#title'] = $this->t('List of date ranges');
      $element[] = $value_interval;
    };

    if ($value_range) {
      $value_range['#theme'] = 'item_list';
      $value_range['#list_type'] = 'ul';
      $value_range['#title'] = $this->t('List of recur dates');
      $element[] = $value_range;
    };

    if ($value_services) {
      $value_services['#theme'] = 'item_list';
      $value_services['#list_type'] = 'ul';
      $value_services['#title'] = $this->t('List of services');
      $element[] = $value_services;
    };

    if (count($element) < 3) {
      $element = [
        '#type' => 'markup',
        '#markup' => new FormattableMarkup('No data in @field for output', ['@field' => $this->fieldDefinition->getName(),]),
      ];
    };

    return $element;
  }

}