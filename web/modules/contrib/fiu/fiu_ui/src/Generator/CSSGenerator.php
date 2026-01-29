<?php

namespace Drupal\fiu_ui\Generator;

use Drupal\Core\File\FileSystemInterface;

class CSSGenerator {

  public static function generate() {
    $css_content = '';
    $name = 'general';
    $configs = \Drupal::config('fiu_ui.settings')->get();
    $path = \Drupal::service('extension.path.resolver')->getPath('module', 'fiu_ui');
    $css_file = $path . '/css/templates/' . $name . '.ccss';
    $css_content .= file_get_contents($css_file);
    foreach ($configs as $key => $variable) {
      $css_content = str_replace('%' . $key . '%', $variable, $css_content);
    }

    $dir = 'public://tmp/fiu';
    if (!\Drupal::service('file_system')->prepareDirectory($dir)) {
      \Drupal::service('file_system')->mkdir($dir, NULL, TRUE);
    }
    $destination = $dir . '/' . $name . '.css';
    // Save css data.
    if (file_exists($destination)) {
      $param = FileSystemInterface::EXISTS_REPLACE;
    }
    else {
      $param = FileSystemInterface::EXISTS_RENAME;
    }
    \Drupal::service('file_system')->saveData($css_content, $destination, $param);
  }

}
