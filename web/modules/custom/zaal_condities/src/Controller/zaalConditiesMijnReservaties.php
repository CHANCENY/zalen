<?php

namespace Drupal\zaal_condities\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * An example controller.
 */
class zaalConditiesMijnReservaties extends ControllerBase {

  /**
   * Returns a render-able array for a test page.
   */
  public function content() {
    $build = [
      '#markup' => $this->t('Mijn reservaties pagina'),
    ];
    return $build;
  }

}