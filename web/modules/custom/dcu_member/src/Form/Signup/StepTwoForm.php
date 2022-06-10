<?php

/**
 * @file
 * Contains \Drupal\dcu_member\Form\Signup\StepTwoForm.
 */

namespace Drupal\dcu_member\Form\Signup;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

class StepTwoForm extends SignupFormBase {

  /**
   * {@inheritdoc}.
   */
  public function getFormId() {
    return 'signup_step_two';
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
    if (empty($this->store->get('email'))) {
      return new RedirectResponse(Url::fromRoute('dcu_member.member')->toString());
    }

    $form['firstname'] = [
      '#title' => $this->t('Firstname'),
      '#type'  => 'textfield',
      '#attributes' => ['placeholder' => $this->t('Firstname')],
      '#default_value' => $this->store->get('firstname') ? $this->store->get('firstname') : '',
      '#required' => TRUE,
    ];
    $form['lastname'] = [
      '#title' => $this->t('Lastname'),
      '#type'  => 'textfield',
      '#attributes' => ['placeholder' => $this->t('Lastname')],
      '#default_value' => $this->store->get('lastname') ? $this->store->get('lastname') : '',
      '#required' => TRUE,
    ];
    $form['mobile'] = [
      '#title' => $this->t('Phonenumber'),
      '#type'  => 'tel',
      '#attributes' => ['placeholder' => $this->t('Phonenumber')],
      '#default_value' => $this->store->get('mobile') ? $this->store->get('mobile') : '',
      '#required' => TRUE,
    ];
    //Check
    $form['birthdate'] = [
      '#title' => $this->t('Birthdate'),
      '#type'  => 'date',
      //'#attributes' => ['placeholder' => $this->t('Birthdate')],
      '#format' => 'd/m/Y',
      '#date_date_format' => 'd/m/Y',
      '#default_value' => $this->store->get('birthdate') ? $this->store->get('birthdate') : '',
      '#required' => TRUE,
    ];

    $form['street'] = [
      '#title' => $this->t('Address'),
      '#type'  => 'textfield',
      '#attributes' => ['placeholder' => $this->t('Address')],
      '#default_value' => $this->store->get('street') ? $this->store->get('street') : '',
      '#required' => TRUE,
    ];
    $form['zipcode'] = [
      '#title' => $this->t('Zipcode'),
      '#type'  => 'textfield',
      '#attributes' => ['placeholder' => $this->t('Zipcode')],
      '#default_value' => $this->store->get('zipcode') ? $this->store->get('zipcode') : '',
      '#required' => TRUE,
    ];
    $form['city'] = [
      '#title' => $this->t('City'),
      '#type'  => 'textfield',
      '#attributes' => ['placeholder' => $this->t('City'), 'autocomplete' => 'off'],
      '#default_value' => $this->store->get('city') ? $this->store->get('city') : '',
      '#required' => TRUE,
    ];
    if ($this->store->get('member_type') != 'deal') {
      $form['zipcode']['#ajax']  = [
        'callback' => '::zipToCityAjaxCallback',
        'event'    => 'focusout',
        'wrapper' => 'update-city-value',
        'disable-refocus' => TRUE,
      ];
      $form['city']['#prefix']  = '<div id="update-city-value">';
      $form['city']['#suffix']  = '</div>';
      $form['newsletter'] = [
        '#type'  => 'checkbox',
        '#title' => $this->t('_dcu_newsletter_placeholder'),
        '#prefix' => $this->t('DCU newsletter'),
        '#default_value' => empty($this->store->get('newsletter')) ? 0 : 1,
      ];
    }
    $form['contact_consent'] = [
      '#title' => $this->t('Yes to concent'),
      '#description' => $this->t('I would like to receive information and hear about relevant benefits and offers from the Danish Camping Union. I can unsubscribe at any time. <a href="/samtykke" target="_blank">Read the terms of consent here</a>'),
      '#type' => 'checkbox',
      '#default_value' => empty($this->store->get('contact_consent')) ? 0 : 1,
    ];

    $form['actions']['previous'] = array(
      '#type' => 'link',
      '#title' => $this->t('Previous'),
      '#attributes' => array(
        'class' => array('button'),
      ),
      '#weight' => 0,
      '#url' => Url::fromRoute('dcu_member.member'),
    );

    return $form;
  }


  public function validateForm(array &$form, FormStateInterface $form_state) {
    $firstname = $form_state->getValue('firstname');
    $birthdate = $form_state->getValue('birthdate');
    if (empty($firstname)) {
      $form_state->setErrorByName('firstname', $this->t('You must enter your firstname'));
    }
    else if (strpos($firstname, '&') == true) {
      $form_state->setErrorByName('firstname', $this->t('& not allowed. Only one name in firstname. Add other persons later as partner'));
    }
    if (!preg_match('/^(\+?)([\s\d]*$)/', $form_state->getValue('mobile'))){
      $form_state->setErrorByName('mobile', $this->t('Phonenumber can only be numbers and areacode'));
    }
    if (!empty($birthdate)) {
      if (!$age = dcu_utility_calculate_age($birthdate)) {
        $form_state->setErrorByName('birthdate', $this->t('There seems to be an issue with the input from birthday. Please check the date. Format should be dd/mm/yyyy.'));
      }
      else {
        if ($age > 150) {
          $form_state->setErrorByName('birthdate', $this->t('There seems to be an issue with your age. Please check the birthdate input. Format should be dd/mm/yyyy.'));
        }
        if ($age < 18) {
          $form_state->setErrorByName('birthdate', $this->t('You must be older than 18 years old to become a member of DCU'));
        }
        if ($this->store->get('member_type') == 'ungdom') {
          if ($age >= 25) {
            $form_state->setErrorByName('birthdate', $this->t('The Youth membership is only available for age under 25'));
          }
        }
      }
    }
  }


  /**
   * Ajax callback - Fetches cityname from dawa address service and fills city
   * name if present (only for danish zipcodes)
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return mixed
   */
  public function zipToCityAjaxCallback(array &$form, FormStateInterface $form_state) {
    $zip = $form_state->getValue('zipcode');
    if (!empty($zip)) {
      try {
        $uri = 'https://dawa.aws.dk/postnumre/' . $zip;
        $response = \Drupal::httpClient()->get($uri, ['http_errors' => FALSE, 'headers' => ['Accept' => 'text/plain']]);
        if ($response->getStatusCode() == 200) {
          $citydata = json_decode((string) $response->getBody());
          if (!empty($citydata)) {
            $form['city']['#value'] = $citydata->navn;
          }
        }
      } catch (RequestException $e) {
        watchdog_exception('dcu_member', $e);
      }
    }
    return $form['city'];
  }


  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->store->set('firstname', ucwords(strtolower($form_state->getValue('firstname'))));
    $this->store->set('lastname', ucwords(strtolower($form_state->getValue('lastname'))));
    $this->store->set('mobile', $form_state->getValue('mobile'));
    $this->store->set('birthdate', $form_state->getValue('birthdate'));
    $this->store->set('street', $form_state->getValue('street'));
    $this->store->set('zipcode', $form_state->getValue('zipcode'));
    $this->store->set('city', $form_state->getValue('city'));
    $this->store->set('newsletter', $form_state->getValue('newsletter'));
    $this->store->set('contact_consent', $form_state->getValue('contact_consent'));
    parent::saveData();
  }
}
