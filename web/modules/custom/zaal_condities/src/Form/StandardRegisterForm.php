<?php

namespace Drupal\zaal_condities\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\user\AccountForm;
use Drupal\Core\Url;

/**
 * Form handler for the user register forms.
 *
 * @internal
 */
class StandardRegisterForm extends AccountForm {

  /**
   * {@inheritdoc}.
   */
  public function getFormId() {
    return 'standard_register_form';
  }

  public function form(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\user\UserInterface $account */
    $account = $this->entity;

    $admin = $account->access('create');

    $form = parent::form($form, $form_state, $account);
    $form['#attached']['library'][] = 'zaal_condities/checkbox_radio';

    $form['administer_users'] = [
      '#type' => 'value',
      '#value' => $admin,
    ];

    $form['#attached']['library'][] = 'core/drupal.form';

    if (!$admin) {
      $form['#attributes']['data-user-info-from-browser'] = TRUE;
    }

    if ($admin) {
      $account->activate();
    }

    $form['account']['roles']['#default_value'] = ['zaal_eigenaar']; //['zaal_eigenaar" => "Standaard'];
    $form['account']['roles']['#access'] = true;
    $form['account']['roles']['#ajax'] = [
        'callback' => '::vipStandaardAjaxCallback',
        'event' => 'change',
        'wrapper' => 'change-vip-standaard',
        'disable-refocus' => FALSE,
        'progress' => [
          'type' => 'throbber',
          'message' => t('Wijzigen...'),
        ]
      ];
    unset($form['account']['roles']['#options']['authenticated']);
    unset($form['account']['roles']['#options']['administrator']);
    unset($form['account']['roles']['#options']['adverteerder']);
    unset($form['account']['roles']['#options']['magnus']);
    unset($form['account']['roles']['#options']['betalende_gebruiker']);
    unset($form['field_betaling_accepteren_via_mo']['widget']['#options']['_none']);
    $form['field_betaling_accepteren_via_mo']['widget']['#default_value'] = 'nee';

    // Dynamic fields wrapper.
    $form['dynamic_fields'] = array(
      '#type' => 'container',
      '#attributes' => array('id' => 'dynamic_fields_wrapper'),
    );

    $roles = $form_state->getValue('roles') ?? [];
    if($roles) {
      $this->enableDisable($form['dynamic_fields'], $form_state,$form);
    }else {
      unset($form['field_betaling_accepteren_via_mo']);
    }

    $url = Url::fromRoute('entity.node.canonical', ['node' => 10]);
    $form['account']['roles']['#description'] = $this->t('Uw bedrijf opname <a href=@link>Opties in detail</a>', ['@link' =>$url->toString()]);
    return $form;
}

/**
  * {@inheritdoc}
  */
  protected function actions(array $form, FormStateInterface $form_state) {
    $form = parent::actions($form, $form_state);
    $form['submit']['#prefix'] = '<div id="change-vip-standaard">';
    $form['submit']['#suffix'] = '</div>';
    $form['submit']['#value'] = 'Standaard account maken';

    return $form;
  }

  public function vipStandaardAjaxCallback(&$form, \Drupal\Core\Form\FormStateInterface $form_state, $form_id) {
    $roles = $form_state->getValue('roles');

    if(in_array('zaal_eigenaar', $roles)){
      $form['actions']['submit']['#value'] = 'Partner worden';
    }
      elseif(in_array('premium_zaal', $roles)){
        $form['actions']['submit']['#value'] = 'VIP worden';
    }
    return [
       $this->enableDisable($form['dynamic_fields'], $form_state, $form),
       $form['actions']['submit'],
    ];

  }

   /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
      $admin = $form_state->getValue('administer_users');

      if (!\Drupal::config('user.settings')->get('verify_mail') || $admin) {
        $pass = $form_state->getValue('pass');
      }
      else {
        $pass = \Drupal::service('password_generator')->generate();
      }

      // Remove unneeded values.
      $form_state->cleanValues();
      $roles = $form_state->getValue('roles');
      $form_state->setValue('pass', $pass);
      $form_state->setValue('init', $form_state->getValue('mail'));
      if (in_array('zaal_eigenaar', $roles)) {
        $form_state->setValue('field_betaling_accepteren_via_mo', ['_none']);
      }
      $form_state->setValue('roles', ['magnus']);
      parent::submitForm($form, $form_state);
    }

      /**
     * {@inheritdoc}
     */
    public function save(array $form, FormStateInterface $form_state) {

      $account = $this->entity;
      $pass = $account->getPassword();
      $admin = $form_state->getValue('administer_users');
      $notify = !$form_state->isValueEmpty('notify');
      $account->save();

      $form_state->set('user', $account);
      $form_state->setValue('uid', $account->id());

      $this->logger('user')->notice('New user: %name %email.', ['%name' => $form_state->getValue('name'), '%email' => '<' . $form_state->getValue('mail') . '>', 'type' => $account->toLink($this->t('Edit'), 'edit-form')->toString()]);

      $account->password = $pass;

      if ($admin && !$notify) {
        $this->messenger()->addStatus($this->t('Created a new user account for <a href=":url">%name</a>. No email has been sent.', [':url' => $account->toUrl()->toString(), '%name' => $account->getAccountName()]));
      }
      elseif (!$admin && !\Drupal::config('user.settings')->get('verify_mail') && $account->isActive()) {
        _user_mail_notify('register_no_approval_required', $account);
        user_login_finalize($account);
        $this->messenger()->addStatus($this->t('Registration successful. You are now logged in.'));
        $form_state->setRedirect('<front>');
      }
      elseif ($account->isActive() || $notify) {
        if (!$account->getEmail() && $notify) {
          $this->messenger()->addStatus($this->t('The new user <a href=":url">%name</a> was created without an email address, so no welcome message was sent.', [':url' => $account->toUrl()->toString(), '%name' => $account->getAccountName()]));
        }
        else {
          $op = $notify ? 'register_admin_created' : 'register_no_approval_required';
          if (_user_mail_notify($op, $account)) {
            if ($notify) {
              $this->messenger()->addStatus($this->t('A welcome message with further instructions has been emailed to the new user <a href=":url">%name</a>.', [':url' => $account->toUrl()->toString(), '%name' => $account->getAccountName()]));
            }
            else {
              $this->messenger()->addStatus($this->t('A welcome message with further instructions has been sent to your email address.'));
              $form_state->setRedirect('<front>');
            }
          }
        }
      }
      else {
        _user_mail_notify('register_pending_approval', $account);
        $this->messenger()->addStatus($this->t('Thank you for applying for an account. Your account is currently pending approval by the site administrator.<br />In the meantime, a welcome message with further instructions has been sent to your email address.'));
        $form_state->setRedirect('<front>');
      }

//    $destination = [];
//    $query = $this->getRequest()->query;
//    if ($query->has('destination')) {
//      $destination = ['destination' => $query->get('destination')];
//      $query->remove('destination');
//    }
//    $form_state->setRedirect(
//      'zaal_condities.user.edit_profiel',
//      ['user' => $this->entity->id()],
//      ['query' => $destination]
//    );


      $redirect = null;
      $optionSelected = $form_state->getValue('field_betaling_accepteren_via_mo')[0]['value'] ?? NULL;
      if($optionSelected === 'ja') {
        $redirect = Url::fromRoute('payment_provider.oauth2_authorize_mollie');
      }else if ($optionSelected === 'nee'){
        $redirect = Url::fromRoute('zaal_condities.capacity_selection');
      } else{
        $redirect = Url::fromRoute('payment_provider.oauth2_authorize_mollie');
      }
      $form_state->setRedirectUrl($redirect);
    }

  private function enableDisable(array &$form, FormStateInterface $form_state, $all)
  {
    $roles = $form_state->getValue('roles');
    if(in_array('premium_zaal', $roles)) {
      $all['field_betaling_accepteren_via_mo']['#access'] = TRUE;
      $all['field_betaling_accepteren_via_mo']['widget']['#required'] = TRUE;
      $form['field_betaling_accepteren_via_mo'] = $all['field_betaling_accepteren_via_mo'];
    }
    return $form;
  }

}
