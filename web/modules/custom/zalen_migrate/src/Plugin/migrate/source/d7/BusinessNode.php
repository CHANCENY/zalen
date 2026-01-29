<?php

namespace Drupal\zalen_migrate\Plugin\migrate\source\d7;

use Drupal\node\Plugin\migrate\source\d7\NodeComplete;
use Drupal\migrate\Row;

/**
 * Source plugin for D7 business nodes including image fields.
 *
 * @MigrateSource(
 *   id = "d7_business_node_complete"
 * )
 */
class BusinessNode extends NodeComplete {

  public function prepareRow(Row $row) {
  parent::prepareRow($row);

  $nid = $row->getSourceProperty('nid');

  \Drupal::logger('zalen_migrate')->notice('prepareRow for nid @nid', ['@nid' => $nid]);

  $this->addField($row, 'field_image', 'field_data_field_image', $nid);
  $this->addField($row, 'field_photo_banner', 'field_data_field_photo_banner', $nid);
  $this->addField($row, 'field_photos', 'field_data_field_photos', $nid);

  return TRUE;
}

protected function addField(Row $row, $field_name, $table, $nid) {
  $query = $this->select($table, 'f')
    ->fields('f')
    ->condition('entity_id', $nid)
    ->condition('deleted', 0)
    ->orderBy('delta');

  $results = $query->execute()->fetchAllAssoc('delta');

  \Drupal::logger('zalen_migrate')->notice(
    'Field @field for nid @nid: @count rows from @table',
    [
      '@field' => $field_name,
      '@nid' => $nid,
      '@count' => count($results),
      '@table' => $table,
    ]
  );

  $values = [];
  foreach ($results as $delta => $record) {
    $values[] = (array) $record;
  }

  if ($values) {
    $row->setSourceProperty($field_name, $values);
  }
}
}