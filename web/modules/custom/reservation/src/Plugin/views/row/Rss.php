<?php

namespace Drupal\reservation\Plugin\views\row;

use Drupal\views\Plugin\views\row\RssPluginBase;

/**
 * Plugin which formats the reservations as RSS items.
 *
 * @ViewsRow(
 *   id = "reservation_rss",
 *   title = @Translation("Reservation"),
 *   help = @Translation("Display the reservation as RSS."),
 *   theme = "views_view_row_rss",
 *   register_theme = FALSE,
 *   base = {"reservation_field_data"},
 *   display_types = {"feed"}
 * )
 */
class Rss extends RssPluginBase {

  /**
   * {@inheritdoc}
   */
  protected $base_table = 'reservation_field_data';

  /**
   * {@inheritdoc}
   */
  protected $base_field = 'cid';

  /**
   * @var \Drupal\reservation\ReservationInterface[]
   */
  protected $reservations;

  /**
   * {@inheritdoc}
   */
  protected $entityTypeId = 'reservation';

  public function preRender($result) {
    $cids = [];

    foreach ($result as $row) {
      $cids[] = $row->cid;
    }

    $this->reservations = $this->entityTypeManager->getStorage('reservation')->loadMultiple($cids);
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm_summary_options() {
    $options = parent::buildOptionsForm_summary_options();
    $options['title'] = $this->t('Title only');
    $options['default'] = $this->t('Use site default RSS settings');
    return $options;
  }

  public function render($row) {
    global $base_url;

    $cid = $row->{$this->field_alias};
    if (!is_numeric($cid)) {
      return;
    }

    $view_mode = $this->options['view_mode'];
    if ($view_mode == 'default') {
      $view_mode = \Drupal::config('system.rss')->get('items.view_mode');
    }

    // Load the specified reservation and its associated node:
    /** @var $reservation \Drupal\reservation\ReservationInterface */
    $reservation = $this->reservations[$cid];
    if (empty($reservation)) {
      return;
    }

    $reservation->link = $reservation->toUrl('canonical', ['absolute' => TRUE])->toString();
    $reservation->rss_namespaces = [];
    $reservation->rss_elements = [
      [
        'key' => 'pubDate',
        'value' => gmdate('r', $reservation->getCreatedTime()),
      ],
      [
        'key' => 'dc:creator',
        'value' => $reservation->getAuthorName(),
      ],
      [
        'key' => 'guid',
        'value' => 'reservation ' . $reservation->id() . ' at ' . $base_url,
        'attributes' => ['isPermaLink' => 'false'],
      ],
    ];

    // The reservation gets built and modules add to or modify
    // $reservation->rss_elements and $reservation->rss_namespaces.
    $build = $this->entityTypeManager->getViewBuilder('reservation')->view($reservation, 'rss');
    unset($build['#theme']);

    if (!empty($reservation->rss_namespaces)) {
      $this->view->style_plugin->namespaces = array_merge($this->view->style_plugin->namespaces, $reservation->rss_namespaces);
    }

    $item = new \stdClass();
    if ($view_mode != 'title') {
      // We render reservation contents.
      $item->description = $build;
    }
    $item->title = $reservation->label();
    $item->link = $reservation->link;
    // Provide a reference so that the render call in
    // template_preprocess_views_view_row_rss() can still access it.
    $item->elements = &$reservation->rss_elements;
    $item->cid = $reservation->id();

    $build = [
      '#theme' => $this->themeFunctions(),
      '#view' => $this->view,
      '#options' => $this->options,
      '#row' => $item,
    ];
    return $build;
  }

}
