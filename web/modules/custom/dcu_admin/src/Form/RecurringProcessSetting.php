<?php

namespace Drupal\dcu_admin\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class RecurringProcessSetting.
 */
class RecurringProcessSetting extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'dcu_admin.recurring_settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'recurring_process_setting';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('dcu_admin.recurring_settings');
    $form['recurring'] = array(
      '#type' => 'fieldset',
      '#title' => t('Recurring payment settings'),
    );
    $form['recurring']['process_payment_queue'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Process recurring email and payment'),
      '#description' => $this->t('If not set the recurring email and payment will not be processed, but only tested'),
      '#weight' => '0',
      '#default_value' => $config->get('process_payment_queue'),
    ];
    $form['recurring']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save setting'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->config('dcu_admin.recurring_settings')
      ->set('process_payment_queue', $form_state->getValue('process_payment_queue'))
      ->save();
  }

}
