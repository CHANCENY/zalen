<?php

namespace Drupal\zaal_condities\Plugin\Menu\LocalTask;

use Drupal\Core\Menu\LocalTaskDefault;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\HttpFoundation\Request;
use Drupal\user\Entity\User;
use Drupal\Core\Entity\EntityInterface;

class TabAccountWijzigen extends LocalTaskDefault {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getTitle(Request $request = NULL) {
    $roles = \Drupal::currentUser()->getRoles();
    if(in_array('zaal_eigenaar', $roles)) {
      return 'VIP worden'; 
    }
    return (string) $this->pluginDefinition['title'];
  }
}