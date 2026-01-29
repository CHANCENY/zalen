<?php

namespace Drupal\zalen_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\migrate\ProcessPluginBase;

/**
 * Converts a D7 fid to a D10 image field value.
 *
 * @MigrateProcessPlugin(
 *   id = "d7_file_to_image",
 *   handle_multiples = false
 * )
 */
class D7FileToImage extends ProcessPluginBase {

  public function transform($value, MigrateExecutableInterface $executable, Row $row, $destination_property) {
    if (empty($value['fid'])) {
      return NULL;
    }

    $fid = $value['fid'];

    $db = \Drupal::database();
    $record = $db->query("SELECT uri FROM migrate.file_managed WHERE fid = :fid", [':fid' => $fid])->fetchAssoc();

    if (!$record) {
      return NULL;
    }

    // Convert D7 uri to D10 legacy uri.
    $uri = $record['uri'];

    // D7 usually: public://field/image/foo.jpg → legacy/field/image/foo.jpg
    $relative = preg_replace('#^public://#', '', $uri);
    $new_uri = 'public://legacy/' . $relative;

    // Create or reuse file entity.
    $file = \Drupal::entityTypeManager()
      ->getStorage('file')
      ->loadByProperties(['uri' => $new_uri]);

    if ($file) {
      $file = reset($file);
    }
    else {
      $file = \Drupal\file\Entity\File::create([
        'uri' => $new_uri,
        'status' => 1,
      ]);
      $file->save();
    }

    return [
      'target_id' => $file->id(),
      'alt' => $value['alt'] ?? '',
      'title' => $value['title'] ?? '',
    ];
  }
}
