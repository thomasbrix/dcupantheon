<?php

namespace Drupal\dcu_member\Form\Profile;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\user\Entity\User;

use Drupal\comment\CommentInterface;

/**
 * Class NotificationsForm.
 */
class NotificationsForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'profile_notifications_form';
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
    $mailchimp_status = dcu_member_mailchimp_status($navData->email);

    $form['userid'] = array(
      '#type' => 'value',
      '#value' => $userId,
    );
    $form['#theme'] = $this->getFormId();
    $form['header'] = [
      '#markup' => '<h1>' . $this->t('Notifications') . '</h1>',
    ];

    $user_settings = \Drupal::service('comment_notify.user_settings');
    $notify_settings = $user->id() && $user_settings->getSettings($user->id()) ? $user_settings->getSettings($user->id()) : $user_settings->getDefaultSettings();
    $form['comment_notify_settings'] = [
      '#type' => 'fieldset',
      '#title' => t('Comment follow-up notification settings'),
      '#weight' => 0,
      '#open' => TRUE,
    ];
    $form['comment_notify_settings']['entity_notify'] = [
      '#title' => t('Receive email when someone comments on your forum posts'),
      '#type' => 'checkbox',
      '#default_value' => isset($notify_settings['entity_notify']) ? $notify_settings['entity_notify'] : NULL,
    ];
    $notify_options[] = t('No notifications');
    $notify_options += _comment_notify_options();
    $form['comment_notify_settings']['comment_notify'] = [
      '#title' => t('Receive email when someone comments on your comments'),
      '#type' => 'radios',
      '#options' => $notify_options,
      '#default_value' => isset($notify_settings['comment_notify']) ? $notify_settings['comment_notify'] : NULL,
      //'#description' => t('Receive email when someone comments on your comments')
    ];

    $form['newsletter_settings'] = [
      '#type' => 'fieldset',
      '#title' => t('Newsletter'),
      '#weight' => 0,
      '#open' => TRUE,
    ];
    $form['newsletter_settings']['newsletter'] = [
      '#title' => t('Subscribe to DCU newsletter'),
      '#type' => 'checkbox',
      '#default_value' => !empty($mailchimp_status),
    ];

    $form['consent_settings'] = [
      '#type' => 'fieldset',
      '#title' => t('Consent'),
      '#weight' => 0,
      '#open' => TRUE,
    ];
    $form['consent_settings']['contact_consent'] = [
      '#title' => t('Ja tak'),
      '#description' => t('I would like to receive information and hear about relevant benefits and offers from the Danish Camping Union. I can unsubscribe at any time. <a href="/samtykke" target="_blank">Read the terms of consent here</a>'),
      '#type' => 'checkbox',
      '#default_value' => empty($navData->consent) ? 0 : 1,
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
    foreach ($form_state->getValues() as $key => $value) {
      // @TODO: Validate fields.
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $userId = $form_state->getValue('userid');
    $user = User::load($userId);


    if (!$user->isAnonymous() && is_numeric($form_state->getValue('entity_notify')) && is_numeric($form_state->getValue('comment_notify'))) {
      $user_settings = \Drupal::service('comment_notify.user_settings');
      $user_settings->saveSettings($user->id(), $form_state->getValue('entity_notify'), $form_state->getValue('comment_notify'));
    }

    $newsletter = $form_state->getValue('newsletter');
    $consent = $form_state->getValue('contact_consent');
    $mailchimp_status = dcu_member_mailchimp_status($user->getEmail());
    $update = FALSE;
    if (empty($mailchimp_status) != empty($newsletter)) {
      $update = TRUE;
      $user->set('field_newsletter', $newsletter);
      if (empty($newsletter)) {
        dcu_member_mailchimp_unsubsribe($user->getEmail());
      }
      else {
        dcu_member_mailchimp_subscribe($user);
      }
    }
    if (empty($user->get('field_contact_consent')->getString()) != empty($consent)) {
      $update = TRUE;
      if (empty($form_state->getValue('contact_consent'))) {
        $consent_date = NULL;
      }
      else {
        $consent_date = date('Y-m-d');
      }
      $user->set('field_contact_consent', $consent_date);
    }
    if ($update) {
      try {
        $user->save();
      } catch (EntityStorageException $e) {
        \Drupal::messenger()->addMessage($this->t('There was an error updating your information'), 'error');
      }
      if (!$navresult = dcu_member_send_userdata_to_nav($userId)) {
        \Drupal::messenger()->addMessage($this->t('There was an error updating your information'), 'error');
        return FALSE;
      }
      \Drupal::messenger()->addMessage($this->t('User data updated'), 'status');
    }
    $url = Url::fromRoute('dcu_member.user_profile', ['userId' => $userId]);
    return $form_state->setRedirectUrl($url);
  }

}
