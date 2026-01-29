<?php

namespace Drupal\zalen_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateSkipRowException;

/**
 * Skips file rows if the file is already used as a user picture.
 *
 * @MigrateProcessPlugin(
 *   id = "skip_if_file_is_user_picture"
 * )
 */
class SkipIfFileIsUserPicture extends ProcessPluginBase {

  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $fid = (int) $value;

    $exists = \Drupal::database()
      ->select('user__user_picture', 'u')
      ->fields('u', ['entity_id'])
      ->condition('u.user_picture_target_id', $fid)
      ->range(0, 1)
      ->execute()
      ->fetchField();

    if ($exists) {
      throw new MigrateSkipRowException();
    }

    return $value;
  }
}
