<?php

namespace Drupal\zaal_condities\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\user\AccountForm;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Form handler for the user register forms.
 *
 * @internal
 */
class MagnusRegisterForm extends AccountForm {

  /**
   * {@inheritdoc}.
   */
  public function getFormId() {
    return 'magnus_register_form';
  }

  public function form(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\user\UserInterface $account */
    $account = $this->entity;
  
    $admin = $account->access('create');

    $form = parent::form($form, $form_state, $account);
  
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
  
    $form['account']['roles']['#default_value'] = ['magnus'];
        
    return $form;
   }
  
   /**
     * {@inheritdoc}
     */
    protected function actions(array $form, FormStateInterface $form_state) {
      $element = parent::actions($form, $form_state);
      $element['submit']['#value'] = $this->t('Account aanmaken');
      $element['magnus_info'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this
          ->t('Een Magnus account is volledig <b>gratis</b>.</br> U hebt toegang tot alle inhoud op de website, kan zalen contacteren en boeken.'),
      ];
      return $element;
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
  
      $form_state->setValue('pass', $pass);
      $form_state->setValue('init', $form_state->getValue('mail'));
  
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
  
      $destination = [];
      $query = $this->getRequest()->query;
      if ($query->has('destination')) {
        $destination = ['destination' => $query->get('destination')];
        $query->remove('destination');
      }
      $form_state->setRedirect(
        'zaal_condities.user.edit_profiel',
        ['user' => $this->entity->id()],
        ['query' => $destination]
      );
    }
  
  }