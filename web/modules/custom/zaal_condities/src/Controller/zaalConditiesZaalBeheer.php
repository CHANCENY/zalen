<?php

namespace Drupal\zaal_condities\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * An example controller.
 */
class zaalConditiesZaalBeheer extends ControllerBase {

  /**
   * Returns a render-able array for a test page.
   */
  public function content() {

    $user_id = \Drupal::currentUser()->id();
    $query = \Drupal::entityQuery('node')
      ->accessCheck(TRUE)
      //->condition('status', 1) //published or not
      ->condition('type', 'bedrijf') //content type
      ->condition('uid', $user_id);
    $nids = $query->execute();
    if (empty($nids)) {
      return [];
    }

    $build = [
      '#markup' => $this->t('<h2 class="content-user">Mijn zaal beheer pagina</h2>'),
    ];
    $build['#markup']  .= $this->t("<p class='content-user'>Please create a company first, then proceed to create a room.</p>");
    return $build;
  }

}

