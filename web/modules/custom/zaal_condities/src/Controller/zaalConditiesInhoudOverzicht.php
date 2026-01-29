<?php

namespace Drupal\zaal_condities\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * An example controller.
 */
class zaalConditiesInhoudOverzicht extends ControllerBase {

  /**
   * Returns a render-able array for a test page.
   */
  public function content() {
    $build = [
      '#markup' => $this->t('Algemeen overzicht van mijn inhoud, bedrijf, zalen en reservaties'),
    ];
    return $build;
  }

}