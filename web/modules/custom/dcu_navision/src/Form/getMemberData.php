<?php
//THIS IS NOT USED ANYMORE
//THIS IS NOT USED ANYMORE
//THIS IS NOT USED ANYMORE
//THIS IS NOT USED ANYMORE
//THIS IS NOT USED ANYMORE

namespace Drupal\dcu_navision\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\dcu_navision\Controller\DcuNavisionSoapClient;
use Drupal\dcu_navision\NavisionController;

/**
 * Class getMemberData.
 */
class getMemberData extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'get_member_data';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    if (dcu_admin_check_navision_redirect ()) {
      \Drupal::messenger()->addMessage(t('Access to Navision is currently blocked'), 'warning');
    }
    $navClient = new DcuNavisionSoapClient();
    $navEnv = $navClient->getEnvironment();
    $message = 'Looks like you are running on the ' . $navEnv . ' environment. Using NAV SOAP URL: ' . $navClient->getUrl();
    $messType = $navEnv == 'PRODUCTION' ? 'warning' : 'status';
    \Drupal::messenger()->addMessage($message, $messType);
    $form['member_number'] = [
      '#type' => 'number',
      '#title' => $this->t('Navision Member number'),
      '#description' => $this->t('Input member number you want to see data for from Navision'),
      '#weight' => '0',
      '#required' => TRUE,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Fetch member data from Navision'),
    ];
    if ($data = $form_state->get('member_data')) {
      $form['member_data'] = [
        '#type' => 'table',
        '#header' => [],
        '#rows' => $data,
      ];
    }
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
    $navClient = new DcuNavisionSoapClient();
    $memberNo = $form_state->getValue('member_number');
    $memberData = $navClient->getMemberData($memberNo);
    if (!$memberData = $memberData->GetMember) {
      \Drupal::messenger()->addMessage('No data found for membernumber');
      return;
    }
    foreach ($memberData as $key => $value) {
      $tableData[] = [$key, $value];
    }
    $form_state->set('member_data', $tableData);
    $form_state->setRebuild(TRUE);
  }
}
