<?php

namespace Drupal\reservation\Plugin\views\sort;

use Drupal\views\Plugin\views\sort\SortPluginBase;

/**
 * Sort handler to sort by last reservation name which might be in 2 different
 * fields.
 *
 * @ingroup views_sort_handlers
 *
 * @ViewsSort("reservation_ces_last_reservation_name")
 */
class StatisticsLastReservationName extends SortPluginBase {

  public function query() {
    $this->ensureMyTable();
    $definition = [
      'table' => 'users_field_data',
      'field' => 'uid',
      'left_table' => 'reservation_entity_statistics',
      'left_field' => 'last_reservation_uid',
    ];
    $join = \Drupal::service('plugin.manager.views.join')->createInstance('standard', $definition);

    // @todo this might be safer if we had an ensure_relationship rather than guessing
    // the table alias. Though if we did that we'd be guessing the relationship name
    // so that doesn't matter that much.
    $this->user_table = $this->query->ensureTable('ces_users', $this->relationship, $join);
    $this->user_field = $this->query->addField($this->user_table, 'name');

    // Add the field.
    $this->query->addOrderBy(NULL, "LOWER(COALESCE($this->user_table.name, $this->tableAlias.$this->field))", $this->options['order'], $this->tableAlias . '_' . $this->field);
  }

}
