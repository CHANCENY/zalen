<?php

namespace Drupal\zaal_condities\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormHelper;

/**
 * Form helper to remove the #type error from the $elements['#states']
 */
class FormCustomHelper extends FormHelper {
    
  /**
    * @param array $elements
   *   A render array element having a #states property as described above.
   *
   * @see \Drupal\form_test\Form\JavascriptStatesForm
   * @see \Drupal\FunctionalJavascriptTests\Core\Form\JavascriptStatesTest
   */
  public static function processStates(array &$elements) {
    $elements['#attached']['library'][] = 'core/drupal.states';
    //$key = ($elements['#type'] == 'item') ? '#wrapper_attributes' : '#attributes'; this line is original in core
    $key = isset($elements['#type']) && $elements['#type'] === 'item' ? '#wrapper_attributes' : '#attributes';
    $elements[$key]['data-drupal-states'] = Json::encode($elements['#states']);
  }

}
