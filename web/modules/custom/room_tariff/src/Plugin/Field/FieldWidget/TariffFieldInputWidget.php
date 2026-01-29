<?php

/**
 * @file
 * Contains \Drupal\room_tariff\Plugin\Field\FieldWidget\TariffFieldInputWidget.
 */

namespace Drupal\room_tariff\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\file\Entity\File;
use Drupal\file\Element\ManagedFile;

/**
 * @FieldWidget(
 *   id = "tariff_field_input_widget",
 *   module = "room_tariff",
 *   label = @Translation("Price entry form"),
 *   field_types = {
 *     "room_tariff"
 *   }
 * )
 */
class TariffFieldInputWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'show_extra' => TRUE,
      'hide_select_currency' => FALSE,
    ] + parent::defaultSettings();
  }
  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);
    $cardinality = $this->fieldDefinition->getFieldStorageDefinition()->getCardinality();
    if ($cardinality != 1) {
      $element['show_extra'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Always show an extra, empty widget (Drupal default). Otherwise the user must explicitly add a new widget if needed.'),
        '#default_value' => $this->getSetting('show_extra'),
      ];
    }
    $element['hide_date'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide the select currency field unless it\'s forbidden to change the user.'),
      '#default_value' => $this->getSetting('hide_select_currency'),
    ];
    return $element;
  }
  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $settings = $this->getSettings();
    if ($this->fieldDefinition->getFieldStorageDefinition()->getCardinality() != 1) {
    $summary[] = $this->t('Always show extra field @extra:', array('@extra' => $settings['show_extra']?$this->t('Yes'):$this->t('No')));
    };
    $summary[] = $this->t('Hide the select currency: @currency.', array('@currency' => $settings['hide_select_currency']?$this->t('Yes'):$this->t('No')));
    return $summary;
  }


  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

    $field_name = $this->fieldDefinition->getName();
    $triggering_button = $form_state->getTriggeringElement();
    $filed_settings = $this->getFieldSettings();
    $parents = $form['#parents'];
    $field_state = static::getWidgetState($parents, $field_name, $form_state);

    // Available organization of currency quotes.
    /** @var \Drupal\room_tariff\Service\CurrencyQuotesList $fin_services */
    $fin_services = \Drupal::service('room_tariff.currency_quotes_list');
    $service = $fin_services->getObjService($filed_settings['default_fin_service']);
    //Additional settings for multiple field
    //$default_value_config = $this->fieldDefinition->getDefaultValueLiteral();
    //$cardinality = $this->fieldDefinition->getFieldStorageDefinition()->getCardinality();

    // Depending on where the initial data comes from. (form_state, AJAX of another field, load from bd, new from, new item in field)
    // We will process the form elements. For example, to exclude the choice "per hour"/"per day" twice.
    $tariff_values = [];

    if ($form_state->isProcessingInput()) {
      $tariff_values = $form_state->getValue(array_merge($element['#field_parents'], [$field_name,]));
      if (!empty($tariff_values)) {
        $tariff_values = $tariff_values + ['root' => 'form_state'];
        unset($tariff_values['add_more']);
        if (in_array('add_more', $triggering_button['#parents'])) {
          $tariff_values['btn'] = 'add_more';
        } else if (in_array('remove_item', $triggering_button['#parents'])) {
          $tariff_values['btn'] = 'remove_item';
        };
      } else if (!in_array($field_name, $form_state->getTriggeringElement()['#parents'])) {
        //Ajax from another field with '#limit_validation_errors'
        $tariff_values = $form_state->getCompleteForm();
        foreach (array_merge($form['#parents'], [$field_name]) as $key) {
          $tariff_values = $tariff_values[$key] ?? [];
        };
        $tariff_values = $tariff_values['widget'] ?? null;
        if (!empty($tariff_values)) {
          $tariff_values = array_intersect_key($tariff_values, range(0, $tariff_values['#max_delta'], 1));
          $tariff_values = array_map(function($v){
            //if ($v['#delta'] == $delta) {return array('pattern'=>$v['pattern']['#value'],'delta' => $v['#delta'],'_weight'=>$v['_weight']['#value'],);};
            return array(
              'pattern' => $v['pattern']['#value'],
              'enumerate' => [
                'price' => $v['enumerate']['price']['#value'],
                'currency' => $v['enumerate']['currency']['#value'], 'begin' => null, 'end' => null,
                'range_type' => $v['enumerate']['range_type']['#value'] ?? null, 'range_tim_start' => null, 'range_tim_end' => null,
                'range_day' => null, 'range_mon' => null, 'range_yea' => null, 'services' => null, 'require' => null,
              ],
              'delta' => $v['#delta'],
              '_weight' => $v['_weight']['#value'],
            );
          }, $tariff_values);//array_fill(0, count($tariff_values), $delta)
          $tariff_values = $tariff_values + ['root' => 'form_other'];
        } else {
          $tariff_values = ['root' => 'form_not_get'];
        };
      } else {
        $tariff_values = ['root' => 'form_unknown'];
      };
    } else if (!empty($items[0]->getValue())) {
      $tariff_values = $items->getValue() + ['root' => 'bd'];
    } else {
      $tariff_values = ['root' => 'empty'];
    };

    // prepare a list of allowed options.
    $setup_list_type = $filed_settings['tariff_type'];
    //1 added by Jan
    $translated_list_type = [];
      foreach ($setup_list_type as $key => $english_string) {
        $translated_list_type[$key] = (string) $this->t($english_string);
      }

    // Depending on the presence of elements in the field
    // We will only set the base field elements if none of them are set.
    // We will remove the basic field elements that have already been added.
    if (in_array($tariff_values['root'], ['form_state', 'form_other', 'bd'])) {
      $key = array_flip(array_column($tariff_values, 'pattern'));
      if (empty($key)) {
        $key = array_flip(['interval','rang_dat','services',]);
      } else if (!empty($tariff_values[$delta]['pattern'])) {
        if (
          //We will process only in cases of adding basic types of the tariff field.
          (array_intersect_key($key, array_flip(['per_hour','inan_day','i_person',])) !== []) &&
          // When open the page for editing, 1 new element will be added.
          (($tariff_values['root'] == 'bd' && $tariff_values[$delta] !== []) ||
          //The same. When a new element is added to form.
          //Since the base cannot be added twice, the next one will be added.
          (isset($tariff_values['btn']) && $tariff_values['btn'] == 'add_more'))
          ) {
          $key = $key + [array_key_first(array_diff_key($setup_list_type, $key)) => 'added_item_bd'];
        };
        $key = array_diff_key($key, [$tariff_values[$delta]['pattern'] => 'current_key']);
      };
      $key = array_diff_key($key, array_flip(['interval','rang_dat','services', 'i_person',]));
      $setup_list_type = array_diff_key($setup_list_type, $key);

    } else {
      $setup_list_type = array_diff_key($setup_list_type, array_flip(['interval','rang_dat','services',]));
    };
    //// ??? Pattern after the 3rd element exclude the base ones.
    //if ($delta > 2) {
    //  $setup_list_type = array_diff_key($setup_list_type, array_flip(['per_hour','inan_day','i_person',]));
    //};

    // prepare the default value of the form elements.
    $setup_default_value = [];
    $setup_default_value['pattern'] = !empty($tariff_values[$delta]['pattern']) ? $tariff_values[$delta]['pattern'] : array_key_first($setup_list_type);
    //If the "add_more" button is clicked. We will return the previous field type instead of the first in the list for convenience.
    if (empty($tariff_values[$delta]['pattern']) && isset($tariff_values['btn']) && $tariff_values['btn'] == 'add_more') {
      if (isset($tariff_values[$delta-1]['pattern']) && !in_array($tariff_values[$delta-1]['pattern'], ['per_hour','inan_day','i_person',])) {
        $setup_default_value['pattern'] = $tariff_values[$delta-1]['pattern'];
      };
    };
    //Add default currency
    if ($tariff_values['root'] == 'form_state') {
      if (empty($tariff_values[$delta]['enumerate']['currency']) && isset($tariff_values['btn']) && $tariff_values['btn'] == 'add_more') {
        $setup_default_value['currency'] = $tariff_values[$delta-1]['enumerate']['currency'] ?? $filed_settings['default_currency'];
      } else {
        $setup_default_value['currency'] = $tariff_values[$delta]['enumerate']['currency'];
      };
    } else if ($tariff_values['root'] == 'form_other') {
      $setup_default_value['currency'] = $tariff_values[$delta]['enumerate']['currency'];
    } else if ($tariff_values['root'] == 'bd') {
      $setup_default_value['currency'] = $tariff_values[$delta]['currency'] ?? $filed_settings['default_currency'];
    } else {
      $setup_default_value['currency'] = $filed_settings['default_currency'];
    };
    //$test = 0;

    /* if ($announced_list_type = $form_state->getValue(array_merge($element['#field_parents'], [$field_name,]))) {
      //'formstate';
      if (isset($announced_list_type[$delta])) {
        $setup_list_type = $announced_list_type[$delta];
        $setup_default_value['pattern'] = $setup_list_type['pattern'];
        if (isset($setup_list_type['enumerate'])) {
          if (!array_diff(array_slice($triggering_button['#parents'],-3,3), [$field_name,$delta,'pattern',])) {
            $setup_default_value['price'] = $setup_list_type['enumerate']['price'];
            $setup_default_value['currency'] = $setup_list_type['enumerate']['currency'];
          } else {
            $setup_default_value = array_merge($setup_default_value, $setup_list_type['enumerate']);
          }
        };
      } else if (!array_diff(array_slice($triggering_button['#parents'],-2,2), [$field_name,'add_more',]) &&
      isset($announced_list_type[$delta-1]) && in_array($announced_list_type[$delta-1]['pattern'], ['interval','rang_dat','services',])) {
        $setup_default_value['pattern'] = $announced_list_type[$delta-1]['pattern'];
        $setup_default_value['currency'] = $announced_list_type[$delta-1]['enumerate']['currency'];
      } else {
        $setup_default_value = '+1new';
      };
      $announced_list_type = array_filter($announced_list_type, function($el){return is_array($el);});
      $announced_list_type = array_column($announced_list_type, 'pattern');
    } else if (count(($announced_list_type = $items->getValue())[0])) {
      //'defolt';
      if (isset($items[$delta]->pattern)) {
        $setup_default_value = $items[$delta]->getValue();
        $setup_default_value['price'] = number_format((float)$setup_default_value['price']/100, 2, '.', '');
        $setup_default_value['begin'] = $setup_default_value['begin'] ? DrupalDateTime::createFromTimestamp($setup_default_value['begin']) : NULL;
        $setup_default_value['end'] = $setup_default_value['end'] ? DrupalDateTime::createFromTimestamp($setup_default_value['end']) : NULL;
      } else {
        $setup_default_value = '+1new';
      };
      $announced_list_type = array_column($announced_list_type, 'pattern');
    } else {
      //'else';
      $announced_list_type = array_slice($filed_settings['tariff_type'],0,$field_state['items_count']+1);
      $announced_list_type = array_keys($announced_list_type);
      $i = 0;
      foreach ($filed_settings['tariff_type'] as $k => $v) {
        if (!in_array($k, ['per_hour','inan_day','i_person',]) || $i >= $delta) {
          $setup_default_value['pattern'] = $k;
          break;
        };
        $i++;
      };
    };

    $setup_list_type = array_diff($announced_list_type, ['interval','rang_dat','services',]);
    if (isset($setup_default_value['pattern'])) {
      $setup_list_type = array_diff($setup_list_type, [$setup_default_value['pattern'],]);
    };
    $setup_list_type = array_diff_key($filed_settings['tariff_type'], array_flip($setup_list_type));

    if ($setup_default_value == '+1new') {
      $setup_default_value = [];
      $setup_default_value['pattern'] = array_key_first($setup_list_type);
    }; */

    // Build form

    $element['#attached']['library'][] = 'room_tariff/remove_field_prijs_option';
    $element['pattern'] = array(
      '#title' => $this->t('Set a price "No. @number"', ['@number' => $delta + 1,]),
      '#type' => 'select',
      '#options' => $translated_list_type,//changed from $setup_list_type
      '#default_value' => $setup_default_value['pattern'],
      '#wrapper_attributes' => [
        'class' => ['tariff-wrapper'],
      ],
      '#element_validate' => [[$this, 'checkingAvailabilityItemType']],
      '#limit_validation_errors' => [array_merge($form['#parents'], [$field_name,])],
      '#description' => $this->t(
        'Selecting the type of pricing formation. <br>You can only add once:: <br>-per @hour, <br>-per @day', [
          '@hour' => (string) $this->t($filed_settings['tariff_type']['per_hour']),
          '@day' => (string) $this->t($filed_settings['tariff_type']['inan_day']),
          '@person' => (string) $this->t($filed_settings['tariff_type']['i_person']),
          //commented out by Jan, cause the translations did not work in the website
          //'@hour' => $filed_settings['tariff_type']['per_hour'],
          //'@day' => $filed_settings['tariff_type']['inan_day'],
          //'@person' => $filed_settings['tariff_type']['i_person'],
        ]
      ),
    );
    $element['enumerate'] = array(
      '#type' => 'container',
      '#attributes' => ['id' => $field_name.'-'.$delta,],
    );
    $element['enumerate']['price'] = array(
      '#title' => $this->t('Set a price'),
      '#type' => 'textfield',
      '#size' => 10,
      //'#default_value' => isset($items[$delta]->price) ? number_format((float)$items[$delta]->price/100, 2, '.', '') : '0.00',
      '#default_value' => isset($items[$delta]->price) ? number_format((float)$items[$delta]->price/100, 2, '.', '') : null,
      '#placeholder' => '0.00',
      '#description' => $this->t('Format as "1000.00" @currency.', ['@currency' => $filed_settings['default_currency']]),
      //'#attributes' => array(' type' => ['number'], 'min' => '0.01', 'step' => '0.01', 'max' => '10000',),
    );

    $element['enumerate']['currency'] = array(
      '#title' => $this->t('Currency'),
      '#type' => 'select',
      '#options' => array_intersect_key($service->getDefaultListCurrency(), $service->getCurrencyRate() + $service->getBaseCurrency()) ?? $filed_settings['currency'],
      '#default_value' => isset($setup_default_value['currency']) ? $setup_default_value['currency'] : $filed_settings['default_currency'],
    );
    if ($filed_settings['use_converter'] && $filed_settings['currency_handle'] == 'convert') {
      $element['enumerate']['currency']['#attributes'] = ['class' => ['currency-refresh-rate',],];
      $element['enumerate']['price']['#attributes'] = ['class' => ['currency-refresh-price',],];
      $element['enumerate']['price']['#suffix'] = '<span class="currency-refresh-data"></span>';
    } else if ($filed_settings['use_converter'] && $filed_settings['currency_handle'] == 'disable') {
      $element['enumerate']['currency'] = array(
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $filed_settings['default_currency'],
      );
    };

    // if ($setup_default_value['pattern'] === 'interval') {
    $curent_time = new DrupalDateTime;
    $element['enumerate']['person_label'] = [
      '#title' => $this->t('Label'),
      '#type' => 'textfield',
      '#placeholder' => $this->t('Add a label'),
      '#attributes' => [
        'class' => ['form-text', 'special-offer'],
      ],
      '#default_value' => !empty($items[$delta]->person_label) ? $items[$delta]->person_label : NULL,
      '#description' => $this->t('Provide a short descriptive label for the per-person option (for example, "Spring menu", "Buffet", "Fish menu", or "Easter").'),
    ];

    $element['enumerate']['person_description'] = array(
      '#title' => $this->t('Description'),
      '#type' => 'textarea',
      '#placeholder' => 'Enter a detailed description',
      '#attributes' => [
        'class' => ['form-textarea special-offer-description'],
      ],
      '#default_value' => isset($items[$delta]->person_description) ? $items[$delta]->person_description : NULL,
      '#description' => $this->t('Provide a detailed description for the Services.'),
    );

    $element['enumerate']['person_images'] = array(
      '#title' => $this->t('Upload Image'),
      '#type' => 'managed_file',
      '#upload_location' => 'public://per_person_images/',
      '#default_value' => isset($items[$delta]->person_images) ? [$items[$delta]->person_images] : NULL,
      '#upload_validators' => [
        'file_validate_extensions' => ['png gif jpg jpeg'],
        'file_validate_size' => [25600000], // 25 MB limit
      ],
      '#theme' => 'image_widget',
      '#preview_image_style' => 'thumbnail',
      '#description' => $this->t('Upload an image. Allowed extensions: png gif jpg jpeg.'),
      // '#theme' => 'media_widget',
    );

    $element['enumerate']['special_offer'] = array(
      '#title' => $this->t('Label'),
      '#type' => 'textfield',
      '#placeholder' => 'Add a label',
      '#attributes' => [
        'class' => ['form-text special-offer'],
      ],
      '#default_value' => isset($items[$delta]->special_offer) ? $items[$delta]->special_offer : NULL,
      '#description' => $this->t('@description.', [
        '@description' => "Enter an optional label for special days (e.g., Christmas, Easter).'"
      ]),
    );

    $element['enumerate']['begin'] = array(
      '#title' => $this->t('Set the start of the period'),
      '#type' => 'datetime',
      '#date_year_range' => '2021:+3',
      '#default_value' => isset($items[$delta]->begin) ? DrupalDateTime::createFromTimestamp($items[$delta]->begin) : clone $curent_time->modify('next monday'),
      '#date_increment' => 900,
      '#prefix' => '<div class="interval-date-'.$delta.'-begin">',
      '#suffix' => '</div>',
    );

    $element['enumerate']['end'] = array(
      '#title' => $this->t('Set the end of the period'),
      '#type' => 'datetime',
      '#date_year_range' => '2021:+3',
      '#default_value' => isset($items[$delta]->end) ? DrupalDateTime::createFromTimestamp($items[$delta]->end) : clone $curent_time->modify('last day of this month'),
      '#date_increment' => 900,
      '#prefix' => '<div class="interval-date-'.$delta.'-end">',
    '#suffix' => '</div>',
    );

    $element['enumerate']['is_optional'] = [
      '#title' => $this->t('Please check this field to mark it as optional.'),
      '#type' => 'checkbox',
      '#wrapper_attributes' => [
        'class' => ['is-optional'],
      ],
      '#attributes' => [
        'class' => ['form-text' ,'is-optional'],
      ],
      '#default_value' => isset($items[$delta]->is_optional) ? $items[$delta]->is_optional : NULL,
    ];
    $element['enumerate']['child_friendly'] = [
      '#title' => $this->t('Child-Friendly.'),
      '#type' => 'checkbox',
      '#wrapper_attributes' => [
        'class' => ['child-friendly'],
      ],
      '#attributes' => [
        'class' => ['form-text' ,'child-friendly'],
      ],
      '#default_value' => isset($items[$delta]->child_friendly) ? $items[$delta]->child_friendly : NULL,
    ];

    // };

    // if ($setup_default_value['pattern'] === 'rang_dat') {
    // Added by Jan for translation purpose
      $raw_range_options = $filed_settings['range_type'];
      $translated_range_options = [];
      foreach ($raw_range_options as $machine_key => $english_string) {
        $translated_range_options[$machine_key] = (string) $this->t($english_string);
      }

      $name_range_type = array_merge($element['#field_parents'], [$field_name,$delta,'enumerate','range_type',]);
      $element['enumerate']['range_type'] = array(
        '#type' => 'radios',
        '#title' => $this->t('Select type range'),
        '#options' => $translated_range_options, //in stead of $filed_settings['range_type']
        '#attributes' => [
          'class' => ['date-range-'.$delta.'-type']
        ]
      );
      $element['enumerate']['range_type']['#default_value'] = isset($items[$delta]->range_type) ? $items[$delta]->range_type : array_key_first($element['enumerate']['range_type']['#options']);
      $element['enumerate']['range_tim_start'] = array(
        '#type' => 'datetime',
        '#title' => 'Start time',
        '#default_value' => isset($items[$delta]->begin) ? (new DrupalDateTime('1970-01-01 00:00:00'))->modify('+'.$items[$delta]->begin.'seconds') : new DrupalDateTime('1970-01-01 00:00:00'),
        '#date_date_element' => 'none',
        '#date_time_element' => 'time',
        '#date_time_format' => 'H:i',
        '#date_increment' => 900,
        '#prefix' => '<div class="date-range-'.$delta.'-start">',
        '#suffix' => '</div>',
      );
      $element['enumerate']['range_tim_end'] = array(
        '#type' => 'datetime',
        '#title' => 'End time',
        '#default_value' => isset($items[$delta]->end) ? (new DrupalDateTime('1970-01-01 00:00:00'))->modify('+'.$items[$delta]->end.'seconds') : new DrupalDateTime('1970-01-01 00:00:00'),
        '#date_date_element' => 'none',
        '#date_time_element' => 'time',
        '#date_time_format' => 'H:i',
        '#date_increment' => 900,
        '#prefix' => '<div class="date-range-'.$delta.'-end">',
        '#suffix' => '</div>',
      );
      $element['enumerate']['range_day'] = array(
        '#type' => 'checkboxes',
        '#title' => $this->t('Repeat every week in...'),
        '#options' => array_combine([7,1,2,3,4,5,6], \Drupal\Core\Datetime\DateHelper::weekDays(TRUE)),
        '#default_value' => [],
        '#multiple' => TRUE,
        '#attributes' => ['class' => ['container-inline']],
        '#states' => [
          'visible' => [
            ':input[name="'.$name_range_type[0].'['.implode('][',array_slice($name_range_type,1)).']"]' => ['value' => 'day'],
          ],
        ],
      );
      $element['enumerate']['range_mon'] = array(
        '#type' => 'checkboxes',
        '#title' => $this->t('Repeat every month in...'),
        '#options' => array_slice(range(0,31),1,31,true),
        '#default_value' => [],
        '#multiple' => TRUE,
        '#attributes' => ['class' => ['container-inline']],
        '#states' => [
          'visible' => [
            ':input[name="'.$name_range_type[0].'['.implode('][',array_slice($name_range_type,1)).']"]' => ['value' => 'mon'],
          ],
        ],
      );
      $element['enumerate']['range_yea'] = array(
        '#type' => 'checkboxes',
        '#title' => $this->t('Repeat once a year in...'),
        '#options' => \Drupal\Core\Datetime\DateHelper::monthNames(TRUE),
        '#default_value' => [],
        '#multiple' => TRUE,
        '#attributes' => ['class' => ['container-inline']],
        '#states' => [
          'visible' => [
            ':input[name="'.$name_range_type[0].'['.implode('][',array_slice($name_range_type,1)).']"]' => ['value' => 'yea'],
          ],
        ],
      );
      if(isset($items[$delta]->range_data)) {
        $setup_default_value['range_data'] = explode(';',$items[$delta]->range_data);
        if ($items[$delta]->range_type === 'day') {$element['enumerate']['range_day']['#default_value'] = $setup_default_value['range_data'];};
        if ($items[$delta]->range_type === 'mon') {$element['enumerate']['range_mon']['#default_value'] = $setup_default_value['range_data'];};
        if ($items[$delta]->range_type === 'yea') {$element['enumerate']['range_yea']['#default_value'] = $setup_default_value['range_data'];};
      };

    // };

    // Load the file entity if fid is available.
//    $file_entity = NULL;
//    if ($file_fid) {
//      $file_entity = File::load($file_fid);
//    }

    // if ($setup_default_value['pattern'] === 'services') {
      $element['enumerate']['services_images'] = array(
        '#title' => $this->t('Upload Image'),
        '#type' => 'managed_file',
        '#upload_location' => 'public://services_images/',
        '#default_value' => isset($items[$delta]->services_images) ? [$items[$delta]->services_images] : NULL,
        '#upload_validators' => [
          'file_validate_extensions' => ['png gif jpg jpeg'],
          'file_validate_size' => [25600000], // 25 MB limit
        ],
        '#theme' => 'image_widget',
        '#preview_image_style' => 'thumbnail',
        '#description' => $this->t('Upload an image for the Services. Allowed extensions: png gif jpg jpeg.'),
      );


      $element['enumerate']['service_description'] = array(
        '#title' => $this->t('Description'),
        '#type' => 'textarea',
        '#placeholder' => 'Enter a detailed description of the Services',
        '#attributes' => [
          'class' => ['form-textarea special-offer-description'],
        ],
        '#default_value' => isset($items[$delta]->service_description) ? $items[$delta]->service_description : NULL,
        '#description' => $this->t('Provide a detailed description for the Services.'),
      );

      $element['enumerate']['services'] = array(
        '#title' => $this->t('Set additional services'),
        '#type' => 'textfield',
        '#size' => 60,
        '#placeholder' => $this->t('Enter favourite'),
        '#default_value' => isset($items[$delta]->services) ? $items[$delta]->services : NULL,
      );

      $element['enumerate']['require'] = array(
        '#title' => $this->t('Set whether a service is mandatory or not'),
        '#type' => 'checkbox',
        '#default_value' => isset($items[$delta]->require) ? $items[$delta]->require : NULL,
      );

      $element['enumerate']['services_minimum_order'] = array(
        '#title' => $this->t('Minimum Order'),
        '#type' => 'number',
        '#size' => 60,
        '#placeholder' => $this->t('Min Order'),
        '#default_value' => isset($items[$delta]->services_minimum_order) ? $items[$delta]->services_minimum_order : NULL,
      );

    // };

    // if field is required.
    if ($delta == 0 && $this->fieldDefinition->isRequired()) {
      $element['enumerate']['price']['#required'] = false;//default true, but give an ajax error
    };

    // if default field-dagprijs field-uurprijs field-prijs-per-persoon.
    // below uncommented by Jan, I think we do not need this annymore, see module file
   /* if ($v = $form_state->getUserInput()) {
      switch ($element['pattern']['#default_value']) {
        case 'per_hour':
          if (is_numeric($v['field_uurprijs'][0]['value'])) {
            $element['enumerate']['price']['#default_value'] = $v['field_uurprijs'][0]['value'] === '0' ?
            '0.00' : $v['field_uurprijs'][0]['value'];
          };
        break;
        case 'inan_day':
          if (is_numeric($v['field_dagprijs'][0]['value'])) {
            $element['enumerate']['price']['#default_value'] = $v['field_dagprijs'][0]['value'] === '0' ?
            '0.00' : $v['field_dagprijs'][0]['value'];
          };
        break;
        case 'i_person':
          if (is_numeric($v['field_prijs_per_persoon'][0]['value'])) {
            $element['enumerate']['price']['#default_value'] = $v['field_prijs_per_persoon'][0]['value'] === '0' ?
            '0.00' : $v['field_prijs_per_persoon'][0]['value'];
          };
        break;
        default:
      };
    };*/

    // add ajax.
    $element['pattern']['#ajax'] = [
      'callback' => [$this, 'changeAjaxPickTypePrice'],
      'wrapper' => $field_name.'-'.$delta,
      'event' => 'change',
      'progress' => [
        'type' => 'throbber',
        'message' => $this->t('Opening.'),
      ],
    ];


    return $element;
  }

  /**
   * Special handling to create form elements for multiple values.
   *
   * Removed the added generic features for multiple fields:
   * - Number of widgets;
   * - AHAH 'add more' button;
   * - Table display and drag-n-drop value reordering.
   * N.B. This is never called with Annotation: multiple_values = "FALSE".
   *
   * {@inheritdoc}
   */
  protected function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $form_state) {

    $elements = parent::formMultipleElements($items, $form, $form_state);
    $field_cardinality = $this->fieldDefinition->getFieldStorageDefinition()->getCardinality();
    if ($field_cardinality == FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED) {

      $delta = $elements['#max_delta'];
      $wrapper_id = $elements['add_more']['#ajax']['wrapper'];

      if ($delta >= 1) {

        $field_name = $this->fieldDefinition->getName();
        $parents = $form['#parents'];
        $language = $items->getLangcode() ? $items->getLangcode() : \Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED;

        $element['remove_item'] = [
          '#type' => 'submit',
          '#value' => $this->t('Remove'),
          '#submit' => array([$this, 'removeCallback']),
          '#limit_validation_errors' => [array_merge($parents, [$field_name])],
          '#attributes' => [
            'class' => [
              'multiple-fields-remove-button',
            ],
          ],
          '#ajax' => [
            'callback' => array($this, 'removeAjaxCallback'),
            'wrapper' => $wrapper_id,
            'effect' => 'fade',
            'method' => 'replace',
          ],
        ];

        for ($i=1; $i<=$delta; $i++) {
          $elements[$i] += $element;
          $elements[$i]['remove_item']['#delta'] = $i;
          $field_parents = isset($elements[$i]['#field_parents']) ? $elements[$i]['#field_parents'] : [];
          $all_parents = array_merge($field_parents, [$field_name, $language, $i,]);
          $elements[$i]['remove_item']['#name'] = implode('_', $all_parents) . '_remove_button';
        };

      };

    }

    // Let's add auto update of exchange rates in the user's browser.
    $filed_settings = $this->getFieldSettings();
    if ($filed_settings['use_converter'] && $filed_settings['currency_handle'] == 'convert') {
      /** @var \Drupal\room_tariff\Service\CurrencyQuotesList $fin_services */
      $fin_services = \Drupal::service('room_tariff.currency_quotes_list');
      $service = $fin_services->getObjService($filed_settings['default_fin_service']);
      $rate_description = $this->t(
        'The currency will be automatically converted to @currency after saving the price. At the rate of the @bank on @date.',
        ['@currency' => $filed_settings['default_currency'], '@bank' => $service->getLabel(), '@date' => $service->getCurrencyRate()['date'],]
      );
      for ($i=0; $i<=$delta; $i++) {
        $elements[$i]['enumerate']['currency']['#description'] = $rate_description;
      };
      $form['#attached']['library'][] = 'room_tariff/room_tariff_lib';
      $form['#attached']['drupalSettings']['room_tariff']['currency_rate'] = $service->getCurrencyState() + ['base' => key($service->getBaseCurrency())];
      $form['#attached']['drupalSettings']['room_tariff']['field_config'] = ['provider' => $service->getLabel(),'currency' => $filed_settings['default_currency'],];
    };

    // Let's add a global update of the field when changing the type "pattern" of tariff in ajax if the field is multiple,
    // this will update the list selection pattern for all field elements.
    if ($this->fieldDefinition->getFieldStorageDefinition()->isMultiple()) {
      $delta = $elements['#max_delta'];
      if ($delta >= 1) {
        $wrapper_id = $elements['add_more']['#ajax']['wrapper'];
        for ($i=0; $i<=$delta; $i++) {
          $elements[$i]['pattern']['#ajax']['wrapper'] = $wrapper_id;
        };
      };
    };

    // Add a paragraph explaining how to fill in the tariff field.
    if ($on_label = $this->getFieldSettings()['on_label']) {
      $elements["#prefix"] = $elements["#prefix"].'<p>'.$this->t($on_label).'</p>';
    };

    // Add automatic updating of basic field elements from user input.
    $form['#attached']['library'][] = 'room_tariff/auto_upd_base_field_el';
    $form['#attached']['drupalSettings']['room_tariff']['name_definition'] = $this->fieldDefinition->getName();

    return $elements;
  }


  /**
   * We change the form element when the type is selected in the checkbox.
   * @return array Returning the modified element.
   */
  public function changeAjaxPickTypePrice (array $form, FormStateInterface $form_state) {

    // When need a global update of the field.
    // (when changing the "pattern" type in ajax, if the field is multiple,
    // this will update the "pattern" selection for all field elements.)
    if ($this->fieldDefinition->getFieldStorageDefinition()->isMultiple()) {
      $delta = $form[$this->fieldDefinition->getName()]['widget']['#max_delta'];
      if ($delta >= 1) {
        return $form[$this->fieldDefinition->getName()]['widget'];
      };
    };

    $selected_item = $form_state->getTriggeringElement();

    $path_to_element = $selected_item['#array_parents'];

    $path_to_element = array_slice($path_to_element, 0, -1);
    $new_value =& $form;
    foreach ($path_to_element as $k) {
      if (is_array($new_value) && array_key_exists($k, $new_value)) {
        $new_value =& $new_value[$k];
      };
    };

    return $new_value['enumerate'];
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    // dump($values);
    // Make sure we only process once, after validation.
   if ($form_state->isValidationComplete()) {

    $filed_settings = $this->getFieldSettings();
    $service = \Drupal::service('room_tariff.currency_quotes_list')->getObjService($filed_settings['default_fin_service']);

    //The widget form element type has transformed the value to a DrupalDateTime object at this point.
    //We need to convert it back to the storage timestamp.
    foreach ($values as &$item) {

      if (!empty($item['pattern']) && array_key_exists($item['pattern'], $filed_settings['tariff_type'])) {
        $item['pattern'] = (string)$item['pattern'];
      } else {$item['pattern'] = $item['enumerate']['price'] = $item['price'] = NULL;};

      if ((!empty($item['enumerate']['price']) || $item['enumerate']['price'] === '0') && is_numeric($item['enumerate']['price'])) {
        $item['price'] = intval(abs(floor($item['enumerate']['price'] * 100)));
      } else if (!empty($item['enumerate']['price'])) {
        $item['enumerate']['price'] = preg_replace('/,/','.',$item['enumerate']['price']);
        $item['price'] = preg_replace('/[^\d.]/','',$item['enumerate']['price']);
        $item['price'] = is_numeric($item['price']) ? intval($item['price']*100) : intval(preg_replace('/\D/','',$item['price']));
      } else { $item['price'] = NULL;};

      if ($filed_settings['use_converter'] && $filed_settings['currency_handle'] == 'disable') {
        // This is because in the form in this case there is no currency selection element, it is displayed as a <p>.
        $item['enumerate']['currency'] = $filed_settings['default_currency'];
      };
      if (!empty($item['enumerate']['currency']) && isset($item['price']) && array_key_exists($item['enumerate']['currency'], $service->getCurrencyRate() + $service->getBaseCurrency())) {
        $item['currency'] = (string)$item['enumerate']['currency'];
        if ($filed_settings['use_converter'] && $filed_settings['currency_handle'] == 'convert' && $item['currency'] !== $filed_settings['default_currency']) {
          $item['price'] = intval($service->getMoneyExchange($item['price'], $item['currency'], $filed_settings['default_currency']));
          $item['currency'] = $filed_settings['default_currency'];
        };
      } else {$item['currency'] = $item['price'] = NULL;};

      if ($item['pattern'] === 'interval' && $item['price']) {
        if (!empty($item['enumerate']['begin']) && $item['enumerate']['begin'] instanceof DrupalDateTime) {
          $item['begin'] = intval($item['enumerate']['begin']->getTimestamp());
        } else {$item['begin'] = '';};
        if (!empty($item['enumerate']['end']) && $item['enumerate']['end'] instanceof DrupalDateTime) {
          $item['end'] = intval($item['enumerate']['end']->getTimestamp());
        } else {$item['end'] = '';};
        if (!empty($item['enumerate']['special_offer'])) {
          $item['special_offer'] = $item['enumerate']['special_offer'];
        } else {$item['speical_offer'] = NULL;};

        if (!$item['begin'] || !$item['end']) {$item['begin'] = $item['end'] = NULL;};
      } else {
        $item['begin'] = $item['end'] = null;
      };
      if ($item['pattern'] === 'i_person' && $item['price']) {
        if (!empty($item['enumerate']['person_label'])) {
          $item['person_label'] = $item['enumerate']['person_label'];
        } else {$item['person_label'] = NULL;};


        if (!empty($item['enumerate']['is_optional'])) {
          $item['is_optional'] = $item['enumerate']['is_optional'];
        } else {$item['is_optional'] = NULL;};
        if (!empty($item['enumerate']['child_friendly'])) {
          $item['child_friendly'] = $item['enumerate']['child_friendly'];
        } else {$item['child_friendly'] = NULL;};


        if (!empty($item['enumerate']['person_description'])) {
          $item['person_description'] = $item['enumerate']['person_description'];
        } else {$item['person_description'] = NULL;};
        if (!empty($item['enumerate']['person_images'])) {
          $item['person_images'] = $item['enumerate']['person_images'][0];
        } else {$item['person_images'] = NULL;};
      }
      if ($item['pattern'] === 'services' && $item['price']) {
        if (!empty($item['enumerate']['services'])) {
          $item['services'] = strval(preg_replace('/[^\w !?:;,.\-_@]/','',$item['enumerate']['services']));
          if (!empty($item['enumerate']['require'])) {
            $item['require'] = (int)$item['enumerate']['require'];
          } else {$item['require'] = 0;};
        } else {$item['services'] = $item['require'] = $item['price'] = NULL;};

        if (!empty($item['enumerate']['service_description'])) {
          $item['service_description'] = $item['enumerate']['service_description'];
        } else {$item['service_description'] = NULL;};
        // dump($item['enumerate']);

        if (!empty($item['enumerate']['services_images'])) {
          $item['services_images'] = $item['enumerate']['services_images'][0];
        } else {$item['services_images'] = NULL;};

        if (!empty($item['enumerate']['services_minimum_order'])) {
          $item['services_minimum_order'] = $item['enumerate']['services_minimum_order'];
        } else {$item['services_minimum_order'] = NULL;};

      } else {$item['services'] = $item['require'] = NULL;};

      if ($item['pattern'] === 'rang_dat' && $item['price']) {
        if (!empty($item['enumerate']['range_type']) && array_key_exists($item['enumerate']['range_type'], $filed_settings['range_type'])) {
          $item['range_type'] = (string)$item['enumerate']['range_type'];
          if (!empty($item['enumerate']['range_tim_start']) && $item['enumerate']['range_tim_start'] instanceof DrupalDateTime &&
            !empty($item['enumerate']['range_tim_end']) && $item['enumerate']['range_tim_end'] instanceof DrupalDateTime) {
            $item['begin'] = $item['enumerate']['range_tim_start']->format('H')*3600 + $item['enumerate']['range_tim_start']->format('i')*60;
            $item['end'] = $item['enumerate']['range_tim_end']->format('H')*3600 + $item['enumerate']['range_tim_end']->format('i')*60;
            if ($item['range_type'] === 'day' && max($item['enumerate']['range_day']) > 0) {
              $item['enumerate']['range_day'] = array_diff($item['enumerate']['range_day'], array(0));
              $item['range_data'] = implode(';',$item['enumerate']['range_day']);
            } else if ($item['range_type'] === 'mon' && max($item['enumerate']['range_mon']) > 0) {
              $item['enumerate']['range_mon'] = array_diff($item['enumerate']['range_mon'], array(0));
              $item['range_data'] = implode(';',$item['enumerate']['range_mon']);
            } else if ($item['range_type'] === 'yea' && max($item['enumerate']['range_yea']) > 0) {
              $item['enumerate']['range_yea'] = array_diff($item['enumerate']['range_yea'], array(0));
              $item['range_data'] = implode(';',$item['enumerate']['range_yea']);
            } else {$item['range_data'] = '';};
          } else {$item['range_type'] = $item['range_data'] = $item['price'] = NULL;};
        } else {$item['range_type'] = $item['range_data'] = $item['price'] = NULL;};
      } else {$item['range_type'] = $item['range_data'] = NULL;};
      unset($item['enumerate']);

    };
    // dump($values);
    // die();
    return $values;

   } else {
    // If the field is required we will change the empty string to null on save.
    // This will give us the opportunity to work flexibly with filling in the field without errors
    // and will not allow us to save the field empty.
    if ($this->fieldDefinition->isRequired() && ($form_state->getTriggeringElement()['#id'] ?? '') == 'edit-submit') {
      foreach ($values as &$item) {
        if ($item['enumerate']['price'] === '') {
          $item['enumerate']['price'] = null;
        } else if (strpos($item['enumerate']['price'],',') !== false) {
          // duplicate for: if required field, it is needed to constraint validate on empty field.
          $item['enumerate']['price'] = preg_replace('/,/','.',$item['enumerate']['price']);
        };
      };
    };
    return parent::massageFormValues($values, $form, $form_state);
  };

  }

  /**
   * Validation of the element that is carried out when changing the type.
   * @param array $element
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function checkingAvailabilityItemType ($element, FormStateInterface $form_state) {

    if ($this->fieldDefinition->getFieldStorageDefinition()->getCardinality() != 1) {
    $check_select = $form_state->getTriggeringElement();

    if (!array_diff(array_slice($check_select['#parents'],-3,2), array_slice($element['#parents'],-3,2))) {

      $values = $form_state->getValue(array_slice($element['#parents'], 0, -2));
      $defined_tariff_types = [];
      foreach ($values as $k => $v) {
        if (array_slice($element["#parents"],-2,1)[0] == $k) {
          // ignore the current element
          continue;
        };
        if (is_array($v) && array_key_exists('pattern', $v)) {
          $defined_tariff_types[] = $v['pattern'];
        };
      };

      if ($element['#value'] == 'per_hour' && in_array('per_hour', $defined_tariff_types)) {
        $form_state->setError($element, $this->t('The price per hour can be specified only once.'));
      } else if ($element['#value'] == 'inan_day' && in_array('inan_day', $defined_tariff_types)) {
        $form_state->setErrorByName(implode('][', $element['#parents']), $this->t('The price per day can only be specified once.'));
       } ;//else if ($element['#value'] == 'i_person' && in_array('i_person', $defined_tariff_types)) {
      //   $form_state->setErrorByName(implode('][', array_slice($element['#parents'],-3,3)), $this->t('The price per person can only be specified once.'));
      // };
    };

    };
  }

  /**
   * Submit handler for the "remove one" button.
   * Decrements the max counter and causes a form rebuild.
   */
  public function removeCallback(array &$form, FormStateInterface $form_state) {

    $triggeringing_button = $form_state->getTriggeringElement();
    $field_name = $this->fieldDefinition->getName();

    $form_fields_address = array_slice($triggeringing_button['#parents'], 0, -2);
    $formValues = $form_state->getValue($form_fields_address);;

    $originalFormInputs = $form_state->getUserInput();
    $formInputs =& $originalFormInputs;
    foreach ($form_fields_address as $v) {
      if (array_key_exists($v, $formInputs)) {
        $formInputs =& $formInputs[$v];
      };
    };
    $delta = array_slice($triggeringing_button["#parents"],-2,1)[0];

    $address_widget_on_element = array_slice($triggeringing_button['#array_parents'], 0, -2);
    // Go one level up in the form, to the widgets container.
    $widget_parent = $form;
    foreach ($address_widget_on_element as $v) {
      $widget_parent = $widget_parent[$v];
    };
    $parents_on_widget = $widget_parent['#field_parents'];
    $field_state = WidgetBase::getWidgetState($parents_on_widget, $field_name, $form_state);
    // Go ahead and renumber everything from our delta to the last item down one. This will overwrite the item being removed.
    for ($i = $delta; $i < $field_state['items_count']; $i++) {

      $formValues[$i] = $formValues[$i+1];
      if (array_key_exists('_weight', $formValues[$i])) {$formValues[$i]['_weight'] = strval($formValues[$i]['_weight']-1);};
      $formInputs[$i] = $formInputs[$i+1];
      if (array_key_exists('_weight', $formInputs[$i])) {$formInputs[$i]['_weight'] = strval($formInputs[$i]['_weight']-1);};

      // Move the entity in our saved state.
      if (isset($field_state['original_deltas'][$i + 1])) {
        $field_state['original_deltas'][$i] = $field_state['original_deltas'][$i + 1];
      } else {
        unset($field_state['original_deltas'][$i]);
      };
    };

    if (isset($field_state['wrapper_id'])) {
      $element_id = isset($form[$field_name]['#id']) ? $form[$field_name]['#id'] : '';
      if (!$element_id) {$element_id = $widget_parent['#id'];};
      $field_state['wrapper_id'] = $element_id;
    }

    // Delete default value for the last deleted element.
    if ($field_state['items_count'] == $i) {
      unset($formValues[$i], $formInputs[$i]);
    };
    // Save new element values.
    $form_state->setValue($field_name, $formValues);
    $form_state->setUserInput($originalFormInputs);

    // Replace the deleted entity with an empty one. This helps to ensure that trying to add a new entity
    // won't resurrect a deleted entity from the trash bin. $count = count($field_state['entity']);
    // Then remove the last item. But we must not go negative.
    if ($field_state['items_count'] > 0) {
      $field_state['items_count']--;
    };

    WidgetBase::setWidgetState($parents_on_widget, $field_name, $form_state, $field_state);

    $form_state->setRebuild();
  }

  /**
   * Callback for both ajax-enabled buttons.
   * Selects and returns the fieldset with the names in it.
   */
  public function removeAjaxCallback(array &$form, FormStateInterface $form_state) {

    $button = $form_state->getTriggeringElement();
    $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -3));
    $element = $element['widget'];

    return $element;
  }

}
