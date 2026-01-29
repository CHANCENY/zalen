<?php

namespace Drupal\reservation\Plugin\migrate;

use Drupal\migrate_drupal\Plugin\migrate\FieldMigration;

/**
 * Migration plugin for Drupal 7 reservations with fields.
 */
class D7Reservation extends FieldMigration {

  /**
   * {@inheritdoc}
   */
  public function getProcess() {
    if (!$this->init) {
      $this->init = TRUE;
      $this->fieldDiscovery->addEntityFieldProcesses($this, 'reservation');
    }
    return parent::getProcess();
  }

}
