<?php

/**
 * @file
 * Contains room_tariff.module.
 */

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\views\ViewExecutable;
use Drupal\views\Plugin\views\query\Sql;

/**
 * Implements hook_help().
 */
function room_tariff_help($route_name, RouteMatchInterface $route_match) {
  switch ($route_name) {
    // Main module help for the room_tariff module.
    case 'help.page.room_tariff':
      $output = '';
      $output .= '<h3>' . t('About') . '</h3>';
      $output .= '<p>' . t('Tariff for zalen node for guest-houses and other accommodation with rooms.') . '</p>';
      return $output;

    default:
  }
}

/**
 * Implements hook_cron().
 *
 * Queues rules for refresh_currency.
 */
function room_tariff_cron() {

  // Let's load the service with the list of available organizations with currency exchange rate.
  /** @var \Drupal\room_tariff\Service\CurrencyQuotesList $fin_services Currency rate service */
  $fin_services = \Drupal::service('room_tariff.currency_quotes_list');
  /** @var Drupal\Core\Queue\QueueFactory $queue_factory */
  $queue_factory = \Drupal::service('queue');
  /** @var Drupal\Core\Queue\DatabaseQueue $queue (QueueInterface) Queue for adding tasks for updating data. */
  $queue = $queue_factory->get('refresh_currency');

  // Let's check if it is necessary to update the list of updating exchange rates

  if ($fin_services->checkCronRefreshConfig()) {
    $list_refresh_ids = [];
    /** @var \Drupal\field\Entity\FieldStorageConfig[] $all_storage_configs */
    $all_storage_configs = $fin_services->getToAllFieldsStorageConfig();

    // if empty - the field is not used anywhere.
    if (empty($all_storage_configs)) {
      // We mark that it is not need to update the currency exchange rate.
      if (!empty($list_refresh_all = $fin_services->getRefreshList())) {
        foreach ($list_refresh_all as $item) {
          $item['status'] = false;
        };
        $fin_services->setRefreshList($list_refresh_all)->save();
      };
      return;
    };

    foreach ($all_storage_configs as $item) {
      $list_refresh_ids[] = $item->getSetting('default_fin_service');
    };
    // Add statuses to config
    if (!empty($list_refresh_ids)) {
      $list_refresh_current = $fin_services->getRefreshList() ?? [];
      foreach (array_keys($list_refresh_current) as $item) {
        $list_refresh_current[$item]['status'] = in_array($item, $list_refresh_ids);
      };
      // If we have new financial organizations that not added in the config when installing the module.
      if ($new_fin_org = array_diff_key($fin_services->services, array_flip($list_refresh_ids))) {
        foreach (array_keys($new_fin_org) as $item) {
          if (in_array($item, $list_refresh_ids)) {// add only refreshed
            $list_refresh_current[$item]['status'] = true;
          };
        };
      };
      // If we have an organization that is not in the module service, we will remove it from the config.
      $list_refresh_current = array_intersect_key($list_refresh_current, $fin_services->services);
      $fin_services->setRefreshList($list_refresh_current)->save();
    };
    // If we have an unprocessed queue, delete it for the new configuration.
    if ($queue->numberOfItems()) {
      $queue->deleteQueue();
    };
    $fin_services->markCronRefreshConfig('');
  };

  // Since elements are added to the queue without uniqueness check.
  // We will wait until the old queue is processed to avoid duplicates.

  if ($queue->numberOfItems()) {
    \Drupal::logger('room_tariff')->warning('The queue for updating exchange rates was not built because the old queue contains '.$queue->numberOfItems().' elements.');
    return;
  };

  // Let's add currency quote services to the update queue.

  $list_refresh_all = $fin_services->getRefreshList();
  $list_refresh_current = [];
  // If refresh necessary. And the exchange rate data is irrelevant.
  foreach ($list_refresh_all as $key => $item) {
    if ($item['status']) {
      $service = $fin_services->getObjService($key);
      if (!$service->isActual($service->getCurrencyRate())) {
        $list_refresh_current[] = $key;
      };
    };
  };
  // Adding items to the queue
  if (!empty($list_refresh_current)) {
    $queue->createQueue();
    foreach ($list_refresh_current as $item) {
      $queue->createItem($item);
    };
  };
}

/**
* Implements hook_preprocess_HOOK().
*
* According Drupal's best practices this file should be placed the theme file.
* To avoid creating a custom admin theme, we placed it in the module file.
*
*/
function room_tariff_preprocess_image_widget(&$variables) {
  $element = $variables['element'];

  $variables['attributes'] = array('class' => array('image-widget', 'js-form-managed-file', 'form-managed-file', 'clearfix'));

  if (!empty($element['fids']['#value'])) {
    $file = reset($element['#files']);
    $element['file_' . $file->id()]['filename']['#suffix'] = ' <span class="file-size">(' . format_size($file->getSize()) . ')</span> ';
    $file_variables = array(
      'style_name' => $element['#preview_image_style'],
      'uri' => $file->getFileUri(),
    );

    // Determine image dimensions.
    if (isset($element['#value']['width']) && isset($element['#value']['height'])) {
      $file_variables['width'] = $element['#value']['width'];
      $file_variables['height'] = $element['#value']['height'];
    } else {
      $image = \Drupal::service('image.factory')->get($file->getFileUri());
      if ($image->isValid()) {
        $file_variables['width'] = $image->getWidth();
        $file_variables['height'] = $image->getHeight();
      }
      else {
        $file_variables['width'] = $file_variables['height'] = NULL;
      }
    }

    $element['preview'] = array(
      '#weight' => -10,
      '#theme' => 'image_style',
      '#width' => $file_variables['width'],
      '#height' => $file_variables['height'],
      '#style_name' => $file_variables['style_name'],
      '#uri' => $file_variables['uri'],
    );

    // Store the dimensions in the form so the file doesn't have to be
    // accessed again. This is important for remote files.
    $element['width'] = array(
      '#type' => 'hidden',
      '#value' => $file_variables['width'],
    );
    $element['height'] = array(
      '#type' => 'hidden',
      '#value' => $file_variables['height'],
    );
  }

  $variables['data'] = array();
  foreach (\Drupal\Core\Render\Element::children($element) as $child) {
    $variables['data'][$child] = $element[$child];
  }
}

/**
 * Implements hook_entity_presave().
 */
//Added by Jan to use price fields in views to be able to filter on the cheapest first.
//And also use the values to print in the node, prices start from!
function room_tariff_entity_presave(\Drupal\Core\Entity\EntityInterface $entity) {
  if ($entity->getEntityTypeId() === 'node' && $entity->bundle() === 'zaal') {
    $tariffs = $entity->get('field_prijs_eenheid')->getValue();
    if (!empty($tariffs)) {
      $lowest_person_price = null;
      $hour_price = null;
      $day_price = null;

      foreach ($tariffs as $tariff) {
        switch ($tariff['pattern']) {
          case 'per_hour':
            $hour_price = $tariff['price'] / 100;
            break;

          case 'inan_day':
            $day_price = $tariff['price'] / 100;
            break;

          case 'i_person':
            $person_price = $tariff['price'] / 100;
            if ($lowest_person_price === null || $person_price < $lowest_person_price) {
              $lowest_person_price = $person_price;
            }
            break;
        }
      }

      if ($hour_price !== null) {
        $entity->set('field_uurprijs', $hour_price);
      }
      if ($day_price !== null) {
        $entity->set('field_dagprijs', $day_price);
      }
      if ($lowest_person_price !== null) {
        $entity->set('field_prijs_per_persoon', $lowest_person_price);
      }
    }
  }
}

//The code below was added by Jan to optimize the sorting results.
/**
 * Implements hook_views_query_alter().
 */
function room_tariff_views_query_alter(ViewExecutable $view, $query) {
  if ($view->id() === 'alle_zalen' && $view->current_display === 'page_alle_zalen') {
    // Check that the query object is of the correct type.
    if ($query instanceof \Drupal\views\Plugin\views\query\Sql) {

      // Add additional field tables, but do not filter yet.
      $dagprijs_alias = $query->ensureTable('node__field_dagprijs', 'node_field_data');
      $uurprijs_alias = $query->ensureTable('node__field_uurprijs', 'node_field_data');
      $persoonprijs_alias = $query->ensureTable('node__field_prijs_per_persoon', 'node_field_data');

      // Determine which field the user is sorting on via the exposed form.
      $exposed = $view->getExposedInput();
      $sort_by = $exposed['sort_by'] ?? '';

      // Filter out nodes with no value in the chosen field.
      switch ($sort_by) {
        case 'field_dagprijs_value':
          // Show only nodes with daily price.
          $dagprijs_alias = $query->ensureTable('node__field_dagprijs', 'node_field_data');
          $query->addWhere(0, "$dagprijs_alias.field_dagprijs_value", NULL, 'IS NOT NULL');
          break;

        case 'field_uurprijs_value':
          // Show only nodes with hourly rate.
          $uurprijs_alias = $query->ensureTable('node__field_uurprijs', 'node_field_data');
          $query->addWhere(0, "$uurprijs_alias.field_uurprijs_value", NULL, 'IS NOT NULL');
          break;

        case 'field_prijs_per_persoon_value':
          // Show only nodes with price per person.
          $pp_alias = $query->ensureTable('node__field_prijs_per_persoon', 'node_field_data');
          $query->addWhere(0, "$pp_alias.field_prijs_per_persoon_value", NULL, 'IS NOT NULL');
          break;
      }
    }
  }
}
/**
 * Implements hook_init() of hook_help() or something else.
 */
function room_tariff_init() {
  // Force-include these strings so the translator sees them.
  t('Hourly rate');
  t('Daily price');
  t('Per person');
  t('Interval price');
  t('Date range');
  t('Additional services');
  t('Repeat every day');
  t('Repeat every week');
  t('Repeat every month');
  t('Repeat every year');
}

