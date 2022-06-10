<?php

/**
 * @file
 * Contains \Drupal\dcu_member\Form\Signup\StepOneForm.
 */

namespace Drupal\dcu_member\Form\Signup;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\dcu_navision\Client\NavisionRestClient;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class StepOneForm extends SignupFormBase {

  /**
   * {@inheritdoc}.
   */
  public function getFormId() {
    return 'signup_step_one';
  }

  /**
   * {@inheritdoc}.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    if ($redirect = dcu_admin_check_navision_redirect ()) {
      return $redirect;
    }
    if (!\Drupal::currentUser()->isAnonymous()) {
      \Drupal::messenger()->addMessage($this->t('You are logged in, so you have been redirected to profile page. To create new DCU membership you must log out first.'), 'status');
      return new RedirectResponse(Url::fromRoute('dcu_member.user_profile', ['userId' => \Drupal::currentUser()->id()])->toString());;
    }
    $form = parent::buildForm($form, $form_state);
    $member_type = $this->store->get('member_type');
    $form['header'] = [
      '#markup' => $this->getFormHeader(),
      '#weight' => -10
    ];
    $form['email'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Email'),
      '#attributes' => ['placeholder' => $this->t('Email')],
      '#default_value' => $this->store->get('email') ? $this->store->get('email') : '',
      '#required' => TRUE,
      '#suffix' => '<div class="form-row"><div class="col-md-12"><div id="verifyemail"></div></div></div>',
      '#ajax'     => [
        'callback' => '::emailValidationAjaxCallback',
        'event'    => 'focusout',
        'disable-refocus' => TRUE,
      ],
    ];
    $form['password'] = [
      '#title' =>  $this->t('Desired password'),
      '#type' => 'password',
      '#size' => 25,
      '#required' => TRUE,
      '#attributes' => ['placeholder' => $this->t('Desired password')]
    ];
    // Only show all countries for DEAL and unset dk.
    if ($member_type == 'deal') {
      $country_info = '';
      $countries = \Drupal::service('country_manager')->getList();
      unset($countries['DK']);
    }
    else {
      $country_info = $this->t('Danes living abroad, Greenland or the Faroe Islands can sign up by e-mail to bogholderi@dcu.dk');
      $countries['DK'] = 'Danmark';
    }
    $form['country'] = [
      '#title' => $this->t('Country'),
      '#type'          => 'select',
      '#required'      => TRUE,
      '#default_value' => 'DK',
      '#options'       => $countries,
      '#description'   => $country_info,
    ];
    $form['campaigncode'] = [
      '#title' => $this->t('Discount code'),
      '#type'  => 'textfield',
      '#attributes' => ['placeholder' => $this->t('Discount code')],
      '#default_value' => $this->store->get('campaigncode') ? $this->store->get('campaigncode') : '',
    ];
    $form['consent'] = [
      '#title' => $this->t('I have read and accepted the conditions for DCU Deal.'),
      '#type'  => 'checkbox',
      '#default_value' => $this->store->get('consent') ? $this->store->get('consent') : '',
      '#required' => TRUE,
      '#description' => '<a target="_blank" href="/handelsbetingelser">' . $this->t('Read terms and conditions') . '</a>'
    ];
    $form['actions']['submit']['#value'] = $this->t('Next');

    $form['actions'] = [
      '#type' => 'submit',
      '#value' => $this->t('Next'),
      '#weight' => 1000,
      '#attributes' => [
        'class' => ['disable-on-click'],
      ],
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (empty($form_state->getValue('email'))) {
      $form_state->setErrorByName('email', $this->t('You must enter an E-mail address.', ['@val' => $form_state->getValue('email')]));
    }
    else {
      //Further email check.
      $email = strtolower(str_replace(' ', '', $form_state->getValue('email')));
      $form_state->setValue('email', $email);

      if (!\Drupal::service('email.validator')->isValid($email)) {
        $form_state->setErrorByName('email', $this->t('@val is not a valid E-mail address.', ['@val' => $email]));
      }
      else {
        if ($this->navisionClient->emailExist($email)) {
          $form_state->setErrorByName('email', $this->t('The E-mail address @val already exists at dcu. <a href="/user">Login with E-mail and password here</a>.', ['@val' => $email]));
        }
        else {
          if (user_load_by_mail($email)) {
            $form_state->setErrorByName('email', $this->t('The E-mail address @val already exists at dcu. <a href="/user">Login with E-mail and password here</a>.', ['@val' => $email]));
          }
        }
      }
    }
    if (!empty($code = $form_state->getValue('campaigncode'))) {
      if(!$this->navisionClient->validateCampaignCode($code)) {
        $form_state->setErrorByName('campaigncode', $this->t('Cuponcode is invalid : @code', ['@code' => $code]));
      }
    }
  }

  /**
   * Ajax callback - validate email and check if email exists in Navision.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   */
  public function emailValidationAjaxCallback(array &$form, FormStateInterface $form_state) {
    $triggerd_element = $form_state->getTriggeringElement();
    $response = new AjaxResponse();

    //Further email check.
    $email = strtolower(str_replace(' ', '', $form_state->getValue('email')));
    $form_state->setValue('email', $email);

    if (empty($email)) {
      $response->addCommand(new HtmlCommand('#verifyemail', ''));
      return $response;
    }
    if (!\Drupal::service('email.validator')->isValid($email)) {
      $response->addCommand(new HtmlCommand('#verifyemail', '<div class="alert alert-danger" role="alert">' . t('@val is not a valid E-mail address.', ['@val' => $email]) . '</div>'));
      $response->addCommand(new InvokeCommand('#edit-email', 'removeClass', ['verified']));
      return $response;
    }
    if ($this->navisionClient->emailExist($email)) {
      $response->addCommand(new HtmlCommand('#verifyemail', '<div class="alert alert-danger" role="alert">' . t('The E-mail address already exists at dcu. <a href="/user">Login with E-mail and password here</a>') . '</div>'));
      $response->addCommand(new InvokeCommand('#edit-email', 'removeClass', ['verified']));
    }
    else if (user_load_by_mail($email)) {
      $response->addCommand(new HtmlCommand('#verifyemail', '<div class="alert alert-danger" role="alert">' . t('The E-mail address already exists at dcu. <a href="/user">Login with E-mail and password here</a>') . '</div>'));
      $response->addCommand(new InvokeCommand('#edit-email', 'removeClass', ['verified']));
    }
    else {
      $response->addCommand(new HtmlCommand('#verifyemail', ''));
      $response->addCommand(new InvokeCommand('#edit-email', 'addClass', ['verified']));
    }
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->store->set('email', $form_state->getValue('email'));
    // TODO: tbx encrypt password
    $this->store->set('password', $form_state->getValue('password'));
    $this->store->set('consent', $form_state->getValue('consent'));
    $this->store->set('country', $form_state->getValue('country'));
    $this->store->set('campaigncode', $form_state->getValue('campaigncode'));
    $form_state->setRedirect('dcu_member.info');
  }
}
