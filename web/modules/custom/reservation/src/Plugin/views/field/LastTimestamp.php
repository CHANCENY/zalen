<?php

namespace Drupal\reservation\Plugin\views\field;

use Drupal\views\Plugin\views\field\Date;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;

/**
 * Field handler to display the timestamp of a reservation with the count of reservations.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("reservation_last_timestamp")
 */
class LastTimestamp extends Date {

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);

    $this->additional_fields['reservation_count'] = 'reservation_count';
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $reservation_count = $this->getValue($values, 'reservation_count');
    if (empty($this->options['empty_zero']) || $reservation_count) {
      return parent::render($values);
    }
    else {
      return NULL;
    }
  }

}
