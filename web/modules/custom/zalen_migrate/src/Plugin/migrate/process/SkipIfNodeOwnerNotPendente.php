<?php

namespace Drupal\zalen_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\migrate\ProcessPluginBase;
use Drupal\user\Entity\User;

/**
 * Skip node if owner is not pendente.
 *
 * @MigrateProcessPlugin(id = "skip_if_node_owner_not_pendente")
 */
class SkipIfNodeOwnerNotPendente extends ProcessPluginBase {

  public function transform($value, MigrateExecutableInterface $executable, Row $row, $destination_property) {
    $uid = $row->getSourceProperty('node_uid');

    if (!$uid) {
      return $value;
    }

    $user = User::load($uid);

    if (!$user) {
      return $value;
    }

    if (!$user->hasRole('pendente')) {
      throw new \Drupal\migrate\MigrateSkipRowException('Owner not pendente, skipping node.');
    }

    return $value;
  }
}
