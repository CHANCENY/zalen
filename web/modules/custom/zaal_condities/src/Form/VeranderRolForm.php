<?php

namespace Drupal\zaal_condities\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\user\AccountForm;
use Drupal\Core\Url;

/**
 * Form handler for the user register forms.
 */
class VeranderRolForm extends AccountForm {

  /**
   * {@inheritdoc}.
   */
  public function getFormId() {
    return 'verander_rol_form';
  }


  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $form['#attached']['library'][] = 'zaal_condities/checkbox_radio';

    $form['account']['roles']['#default_value'] = ['premium_zaal']; //['premium_zaal' => 'VIP'];

    $form['account']['roles']['#ajax'] = [
      'callback' => '::standaardVipAjaxCallback',
      'event' => 'change',
      'wrapper' => 'verander-vip-standaard',
      'disable-refocus' => FALSE,
      'progress' => [
        'type' => 'throbber',
        'message' => t('Wijzigen...'),
      ]
    ];

    $form['account']['roles']['#access'] = true;
    $form['account']['mail']['#access'] = false;
    $form['account']['name']['#access'] = false;
    $form['account']['pass']['#access'] = false;
    $form['account']['current_pass']['#access'] = false;

    unset($form['account']['roles']['#options']['administrator']);
    unset($form['account']['roles']['#options']['adverteerder']);
    unset($form['account']['roles']['#options']['betalende_gebruiker']);
    unset($form['account']['roles']['#options']['authenticated']);
    unset($form['account']['roles']['#options']['magnus']);

    $current_user = \Drupal::currentUser();
    $user_roles = $current_user->getRoles();
    if(in_array('zaal_eigenaar', $user_roles)){
      unset($form['account']['roles']['#options']['zaal_eigenaar']); //['zaal_eigenaar' => 'Partner'];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $element = parent::actions($form, $form_state);
    $element['submit']['#submit'][] = '::standaardVipAjaxCallback';
    $element['submit']['#prefix'] = '<div id="verander-vip-standaard">';
    $element['submit']['#suffix'] = '</div>';
    $element['submit']['#value'] = $this->t('VIP worden');
    $url = Url::fromRoute('entity.node.canonical', ['node' => 2192]);
      $element['vip_info'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this
        ->t('Voor de ambitieuze ondernemer: Geniet van totale autonomie en ongelimiteerde boekingen <b>zonder</b> commissiebeperkingen.
        </br>Bereik <b>succes en groei</b> met een <b>VIP</b>-aansluiting. <a href=@link>Vip aansluiting in detail</a>', ['@link' =>$url->toString()]),
    ];
    $current_user = \Drupal::currentUser();
    $user_roles = $current_user->getRoles();
    if(!in_array('zaal_eigenaar', $user_roles)){
      $url = Url::fromRoute('entity.node.canonical', ['node' => 2193]);
      $element['standaard_info'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this
        ->t('Geef uw zaal een vliegende start met een Standaard-account: Accepteer onbeperkt boekingen <b>zonder</b> abonnementskosten.
        </br>Betaal enkel een commissie per succesvolle boeking en laat uw <b>evenementenlocatie floreren.</b>
         <a href=@link>Standaard aansluiting in detail</a>', ['@link' =>$url->toString()]),
    ];
    }

      return $element;
  }

  public function StandaardVipAjaxCallback(&$form, \Drupal\Core\Form\FormStateInterface $form_state, $form_id) {
    $roles = $form_state->getValue('roles');
    if(in_array('zaal_eigenaar', $roles)){
      $form['actions']['submit']['#value'] = 'Partner worden';
    }
      elseif(in_array('premium_zaal', $roles)){
        $form['actions']['submit']['#value'] = 'VIP worden';
    }
    return [
      $this->enableDisable($form['dynamic_fields'], $form_state, $form),
      $form['actions']['submit']
      ];
  }

}




