<?php

namespace Drupal\dcu_navision\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\dcu_navision\Client\NavisionRestClient;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class getMemberData.
 */
class getMemberDataRest extends FormBase {

  /**
   * @var NavisionRestClient $navisionClient
   */
  protected $navisionClient;

  /**
   * getMemberDataRest constructor.
   *
   */
  public function __construct($navisionClient) {
    $this->navisionClient = $navisionClient;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
        $container->get('dcu_navision.client')
    );
  }


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
    $navEnv = $this->navisionClient->getEnvironment();
    $message = 'Looks like you are running on the ' . $navEnv . ' environment. Using NAV REST URL: ' .  $this->navisionClient->getUrl();
    $messType = $navEnv == 'PRODUCTION' ? 'warning' : 'status';
    \Drupal::messenger()->addMessage($message, $messType);
    $form['member_number'] = [
      '#type' => 'number',
      '#title' => $this->t('Navision Member number'),
      '#description' => $this->t('Input member number you want to see data for from Navision'),
      '#weight' => '0',
    ];
    $form['member_email'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Email on user'),
      '#description' => $this->t('Input user email you want to see data for from Navision'),
      '#weight' => '0',
    ];
    $form['member_quick_create'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Create user in Drupal if exist in Navision'),
      '#weight' => '0',
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
    $memberNo = $form_state->getValue('member_number');
    $memberEmail = $form_state->getValue('member_email');
    $memberCreate = $form_state->getValue('member_quick_create');

    if (!empty($memberNo)) {
      if (empty($memberData = $this->navisionClient->getMemberData($memberNo))) {
        \Drupal::messenger()->addMessage('No data found for membernumber');
        return;
      }
    }
    if (!empty($memberEmail)) {
      if (empty($memberData = $this->navisionClient->getMemberDataByEmail($memberEmail))) {
        \Drupal::messenger()->addMessage('No data found for member email');
        return;
      }
    }

    if (is_object($memberData)) {
      //Check if user in Drupal.
      if (empty(dcu_member_load_drupal_user_from_memberid($memberData->memberno)) && empty(user_load_by_mail($memberData->email))) {
        \Drupal::messenger()->addMessage('User is not in Drupal');

        if ($memberCreate == 1) {
          $password = base64_encode(openssl_random_pseudo_bytes(10));
          $user = dcu_navision_create_user_from_nav_data($memberData, $password);
          if ($user) {
            \Drupal::messenger()->addMessage('User has been created in Drupal');
            $user->activate();//Set to active to be able to use Forgot password.
            $user->save();
          }
          else {
            \Drupal::messenger()->addMessage('There was an error creating the user', 'error');
          }
        }
      }
      else {
        \Drupal::messenger()->addMessage('User exist in Drupal');
      }
      foreach ($memberData as $key => $value) {
        $tableData[] = [$key, $value];
      }
    }
    else {
      $tableData[] = ['Result', $memberData];
    }
    $form_state->set('member_data', $tableData);
    $form_state->setRebuild(TRUE);
  }
}


//TODO: tbx all testdata and functions should be removed at some point.
//Saved test data from Thomas Brix
//FOR various test
//    $account = User::load(366847);
//    $nav_membershiptype = 'gratis';
//    $new_membership_term = dcu_member_get_membership_term_from_navname($nav_membershiptype);
//    $nav_membership_params = array (
//      'memberno' => $memberNo,
//      'newmembertype' => ucfirst($nav_membershiptype),
//      'pensionvalidated' => TRUE,
//      'changedby' => dcu_member_get_member_update_role(),
//      'partnerid' => 'Drupal'
//    );
//    $newrelative = [
//      'reltype' => 'Child',
//      'relname' => 'Thomas test',
//      'relbirthday' => '19102003',
//    ];
//    $updaterel = [
//      'memberno' => '152036',
//      'reltype' => 'Child',
//      'relname' => 'Thomas testupdate',
//      'relbirthday' => '19102002',
//      'relativeno' => '80067306',
//      ];
//    $paymentparams =  array (
//      'memberno' =>  '40080312',
//      'paymentdate' => date('dmY'),
//      'transactionid' => 'tbxtest01',
//      'amount' => 77,
//      'changedby' => dcu_member_get_member_update_role(),
//      'payment' => 'Kort',
//      'domesticcard' => TRUE,
//      'partnerid' => 'Drupal',
//      'recurringid' => '',
//    );

//if (empty($memberData = $this->navisionClient->changeSubscriptionType($nav_membership_params))) {
//if (empty($memberData = $this->navisionClient->emailExist('tester1@sweetlemon.dk'))) {
//if (empty($memberData = $this->navisionClient->getMemberNumberByEmail('tester1@sweetlemon.dk'))) {
//if (empty($memberData = $this->navisionClient->updateMember($account))) {
//if (empty($memberData = $this->navisionClient->validateCampaignCode('DKK21'))) {
//if (empty($memberData = $this->navisionClient->getMembershipPrice('PERSON', 'DKK21'))) {
//if (empty($memberData = $this->navisionClient->getRelatives($memberNo))) {
//if (empty($memberData = $this->navisionClient->deleteRelative('152036', '80067306'))) {
//if (empty($memberData = $this->navisionClient->updateRelative($memberNo, $updaterel))) {
//if (empty($memberData = $this->navisionClient->createRelative($memberNo, $newrelative))) {
//if (empty($memberData = $this->navisionClient->createMember($account))) {
//if (empty($memberData = $this->navisionClient->resubscribe($memberNo))) {
//if (empty($memberData = $this->navisionClient->getActiveMembers())) {
//if (empty($memberData = $this->navisionClient->getActiveByDate(['type' => 'createddate', 'from' => '2021-01-01', 'to' => '2021-03-01']))) {
//if (empty($memberData = $this->navisionClient->getMagazineMembers())) {
//if (empty($memberData = $this->navisionClient->getRecurring())) {
//if (empty($memberData = $this->navisionClient->membersWithBalance())) {
//if (empty($memberData = $this->navisionClient->registerPayment($paymentparams))) {
