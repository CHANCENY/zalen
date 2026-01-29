<?php

namespace Drupal\zalen_migrate\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\user\Entity\User;

/**
 * Skip user if existing destination user does NOT have role 'pendente'.
 *
 * @MigrateProcessPlugin(
 *   id = "skip_if_not_pendente"
 * )
 */
class SkipIfNotPendente extends ProcessPluginBase {

  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {

    // Lookup destination uid by email.
    $mail = $row->getSourceProperty('mail');
    if (!$mail) {
      return $value;
    }

    $users = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->loadByProperties(['mail' => $mail]);

    if (!$users) {
      // New user → allow creation.
      return $value;
    }

    /** @var \Drupal\user\Entity\User $account */
    $account = reset($users);

    if (!$account->hasRole('pendente')) {
      throw new MigrateSkipRowException("User {$mail} skipped (role is not pendente)");
    }

    return $value;
  }
}

