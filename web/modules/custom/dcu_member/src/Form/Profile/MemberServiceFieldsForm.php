<?php

namespace Drupal\dcu_member\Form\Profile;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\user\Entity\User;

/**
 * Class MemberServiceFieldsForm.
 */
class MemberServiceFieldsForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'profile_memberservice_fields_form';
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

    $form['userid'] = array(
      '#type' => 'value',
      '#value' => $userId,
    );
    $form['#theme'] = $this->getFormId();
    $form['header'] = [
      '#markup' => '<h1>' . $this->t('User settings for memberservice') . '</h1>',
    ];

    //Only if country = DK.
    if ($navData->country == 'DK' || !empty($user->field_confirmed_dk_citizenship->value)) {
      $form['receive_magazine'] = [
        '#title' => t('Modtage magasinet Camping-fritid'),
        '#type' => 'checkbox',
        '#default_value' => !empty($navData->magazineletter) ? 1 : 0,
      ];
    }
    else {
      $form['receive_magazine'] = [
        '#title' => t('Modtage magasinet Camping-fritid(Disabled - only danish members)'),
        '#description' => t('It is not possible for members outside DK to receive the magazine.'),
        '#type' => 'checkbox',
        '#default_value' => 0,
        '#disabled' => TRUE,
      ];
    }

    //This field is only in Drupal. Not part of Nav.
    $form['confirmed_dk_citizenship'] = [
      '#title' => t('Confirmed danish citizenship'),
      '#type' => 'checkbox',
      '#description' => t('Check here - if documentation of Danish citizenship has been seen.<br>When this is checked and SAVED it will be possible to receive magazine - by clicking the above checkbox'),
      '#default_value' => !empty($user->field_confirmed_dk_citizenship->value) ? 1 : 0,
    ];

    $comments = '';
    if (isset($navData->comment)) {
      $comments = $navData->comment;
    }
    $form['comments'] = array(
      '#title' => t('Comments'),
      '#type' => 'textarea',
      '#attributes' => ['placeholder' => t('Comments')],
      '#default_value' => $comments,
      '#required' => FALSE,
      '#description' => t('This information is only shown for Member service. Its hidden for the actual user.'),
    );
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
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $userId = $form_state->getValue('userid');
    $user = User::load($userId);
    $user->set('field_magazine', $form_state->getValue('receive_magazine'));
    $user->set('field_customer_comments', $form_state->getValue('comments'));
    $user->set('field_confirmed_dk_citizenship', $form_state->getValue('confirmed_dk_citizenship'));
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
