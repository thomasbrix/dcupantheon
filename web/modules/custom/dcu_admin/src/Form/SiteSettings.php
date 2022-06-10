<?php

namespace Drupal\dcu_admin\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class SiteSettings.
 */
class SiteSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'dcu_admin.sitesettings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'site_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('dcu_admin.sitesettings');
    $form['advanced'] = array(
      '#type' => 'vertical_tabs',
      '#title' => t('Different settings for DCU'),
    );
    $form['general'] = array(
      '#type' => 'details',
      '#title' => t('Mails'),
      '#group' => 'advanced',
    );
    $form['general']['campsite_admin_email'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Campsites admin email'),
      '#description' => $this->t('Only one email - as it is used to be printed to inform campsiteowners who to contact.<br /><strong>This mail is never used to send any mails.</strong>'),
      '#maxlength' => 255,
      '#size' => 128,
      '#default_value' => $config->get('campsite_admin_email'),
    ];

    $form['general']['service_mails'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Service emails'),
      '#description' => $this->t('Email addresses to receive system service emails. Comma separate multiple addresses.'),
      '#maxlength' => 255,
      '#size' => 128,
      '#default_value' => $config->get('service_mails'),
    ];
    $form['general']['alert_mails'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Alert emails'),
      '#description' => $this->t('Email addresses to receive system alert emails. Comma separate multiple addresses.'),
      '#maxlength' => 255,
      '#size' => 128,
      '#default_value' => $config->get('alert_mails'),
    ];
    $form['tokens'] = array(
      '#type' => 'details',
      '#title' => t('DCU tokens'),
      '#group' => 'advanced',
    );
    $form['tokens']['number_of_dcu_campsites'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Number of DCU campites'),
      '#description' => 'Token: [dcu_tokens:number_of_dcu_campsites]',
      '#maxlength' => 32,
      '#size' => 32,
      '#default_value' => $config->get('number_of_dcu_campsites'),
    ];
    $form['tokens']['dcu_tokens_info'] = [
      '#markup' => '<br /><h2>Price tokens from Nav</h2>
        <p>Family price: [dcu_tokens:price_family]</p>
        <p>Person price: [dcu_tokens:price_person]</p>
        <p>Pensioner price: [dcu_tokens:price_pensioner]</p>
        <p>Youth price: [dcu_tokens:price_youth]</p>
        <p>DEAL price: [dcu_tokens:price_deal]</p>'
    ];
    $form['navision'] = array(
      '#type' => 'details',
      '#title' => t('Navision'),
      '#group' => 'advanced',
    );
    $form['navision']['block_access_to_user_data'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Block access to user data'),
      '#description' => $this->t('Prevents access to new member creation and member update. Used if Navision is inaccessible.'),
      '#default_value' => $config->get('block_access_to_user_data'),
    ];
    $form['navision']['blocked_user_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Blocked user message'),
      '#default_value' => $config->get('blocked_user_message'),
      '#description' => $this->t('Message shown on the page to the user, when user data is blocked.'),
    ];
    $form['navision']['blocked_redirect'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Blocked redirect'),
      '#description' => $this->t('Url to redirect to when user data is blocked.'),
      '#maxlength' => 128,
      '#size' => 64,
      '#default_value' => $config->get('blocked_redirect'),
    ];
    $form['app'] = array(
      '#type' => 'details',
      '#title' => t('DCU APP'),
      '#group' => 'advanced',
    );
    $form['app']['broadcast'] = array(
      '#type' => 'details',
      '#title' => t('Broadcast message'),
      '#open' => FALSE,
    );
    $form['app']['broadcast']['app_broadcast_message_active'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Broadcast message active'),
      '#description' => $this->t('When active this message will be send to the app, regardless of version'),
      '#default_value' => $config->get('app_broadcast_message_active'),
    ];
    $form['app']['broadcast']['app_broadcast_message_content'] = [
      '#type' => 'text_format',
      '#format' => 'basic_html',
      '#title' => $this->t('Message'),
      '#default_value' => $config->get('app_broadcast_message_content'),
      '#allowed_formats' => ['basic_html'],
    ];
    $form['app']['lessthan'] = array(
      '#type' => 'details',
      '#title' => t('Version less than message'),
      '#open' => FALSE,
    );
    $form['app']['lessthan']['app_lessthan_message_active'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Lessthan message active'),
      '#description' => $this->t('When active this message will be send to the app if version is less than version'),
      '#default_value' => $config->get('app_lessthan_message_active'),
    ];
    $form['app']['lessthan']['app_lessthan_message_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#description' => $this->t('All version lower than this will be served this message'),
      '#maxlength' => 32,
      '#size' => 32,
      '#default_value' => $config->get('app_lessthan_message_version'),
    ];
    $form['app']['lessthan']['app_lessthan_message_content'] = [
      '#type' => 'text_format',
      '#format' => 'basic_html',
      '#title' => $this->t('Message'),
      '#default_value' => $config->get('app_lessthan_message_content'),
      '#allowed_formats' => ['basic_html'],
    ];
    $form['app']['equalto'] = array(
      '#type' => 'details',
      '#title' => t('Version equal to message'),
      '#open' => FALSE,
    );
    $form['app']['equalto']['app_equalto_message_active'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Equal to message active'),
      '#description' => $this->t('When active this message will be send to the app if version is equal to version'),
      '#default_value' => $config->get('app_equalto_message_active'),
    ];
    $form['app']['equalto']['app_equalto_message_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#description' => $this->t('Version equal to this will be served this message'),
      '#maxlength' => 32,
      '#size' => 32,
      '#default_value' => $config->get('app_equalto_message_version'),
    ];
    $form['app']['equalto']['app_equalto_message_content'] = [
      '#type' => 'text_format',
      '#format' => 'basic_html',
      '#title' => $this->t('Message'),
      '#default_value' => $config->get('app_equalto_message_content'),
      '#allowed_formats' => ['basic_html'],
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->config('dcu_admin.sitesettings')
      ->set('block_access_to_user_data', $form_state->getValue('block_access_to_user_data'))
      ->set('blocked_user_message', $form_state->getValue('blocked_user_message'))
      ->set('blocked_redirect', $form_state->getValue('blocked_redirect'))
      ->set('campsite_admin_email', $form_state->getValue('campsite_admin_email'))
      ->set('service_mails', $form_state->getValue('service_mails'))
      ->set('alert_mails', $form_state->getValue('alert_mails'))
      ->set('app_broadcast_message_active', $form_state->getValue('app_broadcast_message_active'))
      ->set('app_broadcast_message_content', $form_state->getValue('app_broadcast_message_content')['value'])
      ->set('app_equalto_message_active', $form_state->getValue('app_equalto_message_active'))
      ->set('app_equalto_message_version', $form_state->getValue('app_equalto_message_version'))
      ->set('app_equalto_message_content', $form_state->getValue('app_equalto_message_content')['value'])
      ->set('app_lessthan_message_active', $form_state->getValue('app_lessthan_message_active'))
      ->set('app_lessthan_message_version', $form_state->getValue('app_lessthan_message_version'))
      ->set('app_lessthan_message_content', $form_state->getValue('app_lessthan_message_content')['value'])
      ->save();
  }

}
