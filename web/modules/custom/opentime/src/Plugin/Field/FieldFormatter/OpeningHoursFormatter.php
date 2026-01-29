<?php

namespace Drupal\opentime\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Language\LanguageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * @FieldFormatter(
 *   id = "opening_hours_formatter",
 *   label = @Translation("Opening Hours Formatter"),
 *   field_types = { "opening_hours" }
 * )
 */
class OpeningHoursFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  protected $dateFormatter;

  public function __construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings, DateFormatterInterface $date_formatter) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->dateFormatter = $date_formatter;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('date.formatter')
    );
  }

  public function viewElements(FieldItemListInterface $items, $langcode) {
    // 1. Define day‐labels per language.
    $short_days = [
      'nl' => ['monday'=>'Ma','tuesday'=>'Di','wednesday'=>'Wo','thursday'=>'Do','friday'=>'Vr','saturday'=>'Za','sunday'=>'Zo'],
      'en' => ['monday'=>'Mon','tuesday'=>'Tue','wednesday'=>'Wed','thursday'=>'Thu','friday'=>'Fri','saturday'=>'Sat','sunday'=>'Sun'],
      'fr' => ['monday'=>'Lu','tuesday'=>'Ma','wednesday'=>'Me','thursday'=>'Je','friday'=>'Ve','saturday'=>'Sa','sunday'=>'Di'],
      'de' => ['monday'=>'Mo','tuesday'=>'Di','wednesday'=>'Mi','thursday'=>'Do','friday'=>'Vr','saturday'=>'Sa','sunday'=>'So'],
      'uk' => ['monday'=>'Пн','tuesday'=>'Вт','wednesday'=>'Ср','thursday'=>'Чт','friday'=>'Пт','saturday'=>'Сб','sunday'=>'Нд'],
    ];
  $lang = \Drupal::languageManager()
    ->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)
    ->getId();
  if (!isset($short_days[$lang])) $lang = 'nl';
  $labels  = $short_days[$lang];
  $all_days = array_keys($labels);

  // 2) Initiate schedule as empty list per day
  $schedule = [];
  foreach ($all_days as $day) {
    $schedule[$day] = [];
  }

  // 3) Enter all time intervals per field item
  foreach ($items as $item) {
    $days = json_decode($item->days, TRUE) ?: [];
    foreach ($days as $d) {
      if (isset($schedule[$d])) {
        $schedule[$d][] = [
          'start' => $item->start_time,
          'end'   => $item->end_time,
        ];
      }
    }
  }

  // 4) Return one render array with the full schedule
  return [
    0 => [
      '#theme'    => 'opening_hours',
      '#schedule' => $schedule,
      '#labels'   => $labels,
      '#cache'    => [
        'contexts'=>['url','user.permissions'],
        'tags'    => ['node:' . $items->getEntity()->id()],
      ],
    ],
  ];
}

}
