<?php

namespace Drupal\dcu_member\Form\Profile;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

use Drupal\user\Entity\User;

/**
 * Class ProfileForm.
 */
class BaseForm extends FormBase {


  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'profile_base_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $userId = NULL) {
    if (empty($userId)) {
      $userId = \Drupal::currentUser()->id();
    }
    $user = User::load($userId);
    $navData = dcu_member_get_user_navdata($user);

    $country = $navData->country;
    $birthday = date('Y-m-d', strtotime($navData->birthday));
    $memberType = $navData->membertype;

    $form['userid'] = array(
      '#type' => 'value',
      '#value' => $userId,
    );

    $form['#theme'] = $this->getFormId();
    $form['header'] = [
      '#markup' => '<h1>' . $this->t('Personal information') . '</h1>',
    ];

    //Check who is editing the user.
    $currentUserId = \Drupal::currentUser()->id();
    $currentUser = User::load($currentUserId);
    if ($currentUser->hasRole('administrator') || $currentUser->hasRole('member_service')) {
      $member_service_user = 1;
    }

    $form['firstname'] = array(
      '#title' => t('Firstname'),
      '#type' => 'textfield',
      '#attributes' => ['placeholder' => t('Firstname')],
      '#default_value' => $navData->firstname,
      '#required' => TRUE,
      '#disabled' => isset($member_service_user) ? FALSE : TRUE,
      '#description' => isset($member_service_user) ? '' : t('Please contact info@dcu.dk, if you wish to change your firstname.'),
    );

    $form['lastname'] = [
      '#title' => t('Lastname'),
      '#type'     => 'textfield',
      '#attributes' => ['placeholder' => t('Lastname')],
      '#default_value' => $navData->lastname,
      '#required' => TRUE,
    ];

    $countries = \Drupal::service('country_manager')->getList();
    if ($memberType == 'DEAL') {
      unset($countries['DK']);
    }
    $form['country'] = [
      '#title' => t('Country'),
      '#type'          => 'select',
      '#required'      => TRUE,
      '#default_value' => $country,
      '#options'       => $countries,
    ];
    if (in_array($memberType, ['PERSON', 'FAMILIE', 'PENSIONIST', 'UNGDOM'])) {
      $form['country']['#disabled'] = TRUE;
    }

    $form['cellphone'] = [
      '#title' => t('Phonenumber'),
      '#type'     => 'textfield',
      '#attributes' => ['placeholder' => t('Phonenumber')],
      '#default_value' => $navData->phoneno,
      '#required' => TRUE,
    ];

    $form['birthdate'] = [
      '#title' => $this->t('Birthdate'),
      '#type'  => 'date',
      '#format' => 'd/m/Y',
      '#date_date_format' => 'd/m/Y',
      '#default_value' => $birthday,
      '#required' => TRUE,
    ];
    $form['street'] = [
      '#title' => t('Address'),
      '#type' => 'textfield',
      '#attributes' => ['placeholder' => t('Address')],
      '#default_value' => $navData->address,
      '#required' => TRUE,
    ];
    $form['zipcode'] = [
      '#title' => t('Zipcode'),
      '#type'     => 'textfield',
      '#attributes' => ['placeholder' => t('Zipcode')],
      '#required' => TRUE,
      '#default_value' => $navData->postalcode,
    ];
    if ($country == 'DK') {
      $form['zipcode']['#ajax'] = [
        'callback' => 'dcu_member_zip_to_city_ajax_callback',
        'event'    => 'focusout',
        'wrapper' => 'update-city-value',
        'disable-refocus' => TRUE,
      ];
    }
    $form['city'] = [
      '#title' => t('City'),
      '#type'     => 'textfield',
      '#attributes' => ['placeholder' => t('City'), 'autocomplete' => 'off'],
      '#required' => TRUE,
      '#default_value' => $navData->city,
      '#prefix' => "<div id='update-city-value'>",
      '#suffix' => "</div>",
    ];

    $preferred_langcode_options = dcu_member_language_options();
    $form['preferred_langcode'] = [
      '#title' => t('Preferred langcode'),
      '#description' => t("This account's preferred language for emails and site presentation."),
      '#type'          => 'select',
      '#required'      => TRUE,
      '#default_value' => $user->preferred_langcode->value,
      '#options'       => $preferred_langcode_options,
    ];
    $form['next'] = [
      '#type' => 'submit',
      '#value' => t('Save settings'),
      '#attributes' => [
        'class' => ['disable-on-click'],
      ],
    ];
    $form['cancel'] = [
      '#type' => 'button',
      '#value' => t('Cancel'),
      '#attributes' => ['onClick' => 'history.go(-1); event.preventDefault();'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (!empty($form_state->getValue('zipcode')) && !is_numeric($form_state->getValue('zipcode'))) {
      $form_state->setErrorByName('zipcode', $this->t('Only numbers are allowed in Zipcode.'));
    }
    if (!empty($form_state->getValue('cellphone'))) {
      if (!preg_match('/^(\+?)([\s\d]*$)/', $form_state->getValue('cellphone'))){
        $form_state->setErrorByName('cellphone', $this->t('Phonenumber can only be numbers and areacode'));
      }
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $userId = $form_state->getValue('userid');
    $user = User::load($userId);
    $user->set('field_first_name', $form_state->getValue('firstname'));
    $user->set('field_last_name', $form_state->getValue('lastname'));
    $user->set('field_address', $form_state->getValue('street'));
    $user->set('field_zip', $form_state->getValue('zipcode'));
    $user->set('field_city', $form_state->getValue('city'));
    $user->set('field_country', $form_state->getValue('country'));
    $user->set('field_mobile_phone', $form_state->getValue('cellphone'));
    $user->set('field_birthday', date('Y-m-d', strtotime($form_state->getValue('birthdate'))));
    $user->set('preferred_langcode', $form_state->getValue('preferred_langcode'));

    try {
      $user->save();
    } catch (EntityStorageException $e) {
      \Drupal::messenger()->addMessage($this->t('There was an error updating your information'), 'error');
      \Drupal::logger('dcu_member')->error('Error trying to save memberdata Drupal account. userid: @uid message: @message', ['@uid' => $userId, '@message' => $e->getMessage()]);
      return FALSE;
    }
    if (!$navresult = dcu_member_send_userdata_to_nav($userId)) {
      \Drupal::messenger()->addMessage($this->t('There was an error updating your information'), 'error');
      return FALSE;
    }
    \Drupal::messenger()->addMessage($this->t('User data updated'), 'status');
    $url = Url::fromRoute('dcu_member.user_profile', ['userId' => $userId]);
    return $form_state->setRedirectUrl($url);
  }
}
