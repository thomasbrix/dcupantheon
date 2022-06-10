<?php
//THIS IS NOT USED ANYMORE
//THIS IS NOT USED ANYMORE
//THIS IS NOT USED ANYMORE
//THIS IS NOT USED ANYMORE
//THIS IS NOT USED ANYMORE

namespace Drupal\dcu_navision\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Site\Settings;
use SoapClient;

class DcuNavisionSoapClient {
  var $url;
  var $options;

  function __construct() {
    $this->url = Settings::get('dcu_navision_url', '');
    $this->options = [
      "login" => Settings::get('dcu_navision_login', NULL),
      "password" => Settings::get('dcu_navision_psw', NULL),
      "cache_wsdl" => WSDL_CACHE_NONE,
    ];
  }

  function getEnvironment() {
    if (strpos($this->url, 'TEST')) {
      return 'TEST';
    }
    else {
      return 'PRODUCTION';
    }
  }

  function getUrl() {
    return $this->url;
  }

  private function connect($method) {
    \Drupal::messenger()->addMessage('Trying to connect to SOAP NAV', 'error');
    \Drupal::logger('dcu_navision')->error('Using soap. Connecting to Dcu Navision. Method: @message',
      ['@message' => $method]
    );
    try {
      $navEndpoint = $this->url . $method;
      return new SoapClient($navEndpoint, $this->options);
    }
    catch(\SoapFault $e) {
      \Drupal::messenger()->addMessage('There was an error connecting DCU Nav', 'error');
      \Drupal::logger('dcu_navision')->error('Error connecting to Dcu Navision. Message: @message',
        ['@message' => $e->getMessage()]
      );
      //TODO: tbx mailto on prod - from dp7
      /*      if (IS_PRODUCTION) {
              $mailto = variable_get('dcu_navision_notification_email', '');
              if (!empty($mailto)) {
                $params = [
                  'subject' => 'Navision fejl på dcu.dk',
                  'message' => 'Der opstod en fejl ved forbindelse til Navision på dcu.dk. Fejlbesked: ' . $e->getMessage(),
                ];
                drupal_mail('dcu_navision', 'navisionerror', $mailto, $lang, $params);
              }
            }*/

      return FALSE;
    }
  }

  /**
   * Get memberdata from Navision by membernumber.
   *
   * @param $memberNumber
   *
   * @return memberdata or FALSE on error
   */
  function getMemberData($memberNumber) {
    $client = $this->connect('Page/GetMember');
    if (!$client || empty($memberNumber)) {
      \Drupal::logger('dcu_navision')->error('Error calling GetMember on Navision.');
      return FALSE;
    }
    try {
      return $client->Read(array('memberno' => $memberNumber));
    }
    catch (\SoapFault $e) {
      \Drupal::logger('dcu_navision')->error(
        'Error fetching member data from Dcu Navision. Message: @message',
        ['@message' => $e->getMessage()]
      );
      return FALSE;
    }
  }

  /**
   * Get relatives data from Nav.
   * @param $memberid
   * @return bool, array
   */
  function getRelatives($memberNumber) {
    $client = $this->connect( 'Page/GetRelatives');
    $relatives = [];
    try {
      $params = ['filter' => [['Field' => 'memberno', 'Criteria' => $memberNumber]], 'setSize' => 1000];
      $result = $client->ReadMultiple($params);
      if (!empty($result->ReadMultiple_Result)) {
        if (empty($result->ReadMultiple_Result->GetRelatives)) {
          $relatives= [];
        }
        elseif (!is_array($result->ReadMultiple_Result->GetRelatives)) {
          $relatives = [$result->ReadMultiple_Result->GetRelatives];
        }
        else {
          $relatives = $result->ReadMultiple_Result->GetRelatives;
        }
      }
      return $relatives;
    }
    catch (\SoapFault $e) {
      \Drupal::logger('dcu_navision')->error(
        'Error fetching relatives data from Dcu Navision. Message: @message',
        ['@message' => $e->getMessage()]);
      \Drupal::messenger()->addMessage(t('Could not fetch relatives for member'), 'error');
      return FALSE;
    }
  }


  /**
   * Get membership price from membertype and optional campaigncode.
   *
   * @param $membertype
   * @param string $campaigncode
   *
   * @return false
   */
  public function getMembershipPrice($membertype, $campaigncode = '') {
    $config = \Drupal::config('dcu_admin.sitesettings');
    if ($config->get('block_access_to_user_data')) {
      return FALSE;
    }
    if (empty($membertype)) {
      return FALSE;
    }
    try {
      $client = $this->connect('Codeunit/MemberManagement');
      if (!$client) {
        \Drupal::logger('dcu_navision')->error('Failed to connect to Navision nav getMembershipPrice');
        return FALSE;
      }
      return $client->GetPrice(array('membertype' => $membertype, 'campaigncode' => $campaigncode))->return_value;
    }
    catch(\SoapFault $e) {
      \Drupal::logger('dcu_navision')->error(
        'Error getting membership price. Message: @message',
        ['@message' => $e->getMessage()]
      );
      return FALSE;
    }
  }

  /**
   * @param $account
   *
   * @return memberid on success else FALSE
   */
  public function createMember($account, $campaigncode = NULL) {
    if (empty($account)) {
      return FALSE;
    }
    \Drupal::logger('nav_create_member_data_prepare')->notice('@data', ['@data' => Json::encode($account->id())]);
    $memberParams = $this->prepareNavisionMemberParams($account, TRUE, $campaigncode);
    \Drupal::logger('nav_create_member_data_ready')->notice('@data', ['@data' => Json::encode($memberParams)]);
    try {
      $client = $this->connect('Codeunit/MemberManagement');
      $created = $client->CreateMember($memberParams);
      \Drupal::logger('nav_create_member_result')->notice('@data', ['@data' => Json::encode($created)]);
    }
    catch(\SoapFault $e) {
      \Drupal::logger('dcu_navision')->error('Error creating membership. Message: @message', ['@message' => $e->getMessage()]);
      \Drupal::logger('nav_create_member_error')->notice('@data', ['@data' => Json::encode([$memberParams, $e->getMessage()])]);
      return FALSE;
    }
    if (!$created) {
      \Drupal::logger('nav_create_member_failed')->notice('@data', ['@data' => Json::encode($memberParams)]);
      return FALSE;
    }
    $member_id = $created->return_value;
    if (!is_numeric((int)$member_id)) {
      return FALSE;
    }
    return $member_id;
  }

  /**
   * @param $userData
   *
   * @return false
   */
  function updateMember($account) {
    $userData = $this->prepareNavisionMemberParams($account);
    \Drupal::logger('nav_update_member_data_params')->notice('@data', ['@data' => Json::encode($userData)]);
    try {
      $client = $this->connect('Codeunit/MemberManagement');
      $response = $client->UpdateMember($userData);
      \Drupal::logger('nav_update_member_data_response')->notice('@data', ['@data' => Json::encode($response)]);
      return $response;
    }
    catch(\SoapFault $e) {
      \Drupal::logger('nav_update_member_data_response_error')->notice('@data', ['@data' => Json::encode([$e->getMessage()])]);
      return FALSE;
    }
  }


  public function registerPayment($params) {
    \Drupal::logger('nav_register_payment_call')->notice('@data', ['@data' => Json::encode($params)]);
    $client = $this->connect('Codeunit/MemberManagement');
    if (!$client) {
      \Drupal::logger('nav_register_payment_fail')->error('@data', ['@data' => Json::encode($params)]);
      \Drupal::logger('dcu_navision')->error('Failed to create navision client in nav registerPayment');
      return FALSE;
    }
    try {
      $response = $client->MembershipPayment($params);
      \Drupal::logger('nav_register_payment_response')->notice('@data', ['@data' => Json::encode($response)]);
    }
    catch(\SoapFault $e) {
      //TODO: tbx Send mail to admin about this error.
      /*      drupal_mail(
              'dcu_memberportal',
              'error_payment',
              'stub@raindrop.dk,anders@klean.dk',
              language_default(),
              [
                'memberno' => $params['memberno'],
                'transactionid' => $params['transactionid'],
                'paymentdate' => $params['paymentdate'],
              ]
            );
            */
      \Drupal::logger('nav_register_payment_error')->error('@data', ['@data' => Json::encode($e->getMessage())]);
      \Drupal::logger('dcu_navision')->error('Failed to register payment receipt with navision. Message : @exception', ['@exception' => $e->getMessage()]);
      $response = $e;
    }
    return $response;

  }


  /**
   * Check if email address exists in Navision.
   * Returns TRUE on exist FALSE on nonexist.
   *
   * @param $email
   *
   * @return bool
   */
  function emailExist($email) {
    $client = $this->connect('Codeunit/MemberManagement');
    if (!$client) {
      \Drupal::logger('dcu_navision')->error('Error calling emailExist on Navision.');
      return FALSE;
    }
    try {
      $response = $client->ValidateEmail(array('email' => $email));
      return $response->return_value;
    }
    catch(\SoapFault $e) {
      // Check failed on navision. Return true to avoid duplicate emails.
      \Drupal::logger('dcu_navision')->error('Error validating email from Dcu Navision. Message: @message',
        ['@message' => $e->getMessage()]
      );
      return TRUE;
    }
  }

  /**
   * Validates coupon code.
   *
   * @param $code
   *
   * @return bool
   */
  function validateCuponCode($code = NULL) {
    if (empty($code)) {
      return FALSE;
    }
    try {
      $client = $this->connect('Codeunit/MemberManagement');
      $is_valid = $client->ValidateCampaignCode(array('campaigncode' => $code));
      \Drupal::logger('nav_cuponcode_validation')->notice('@data', ['@data' => Json::encode(['code' => $code, 'valid' => $is_valid])]);
      return $is_valid->return_value;
    }
    catch(\SoapFault $e) {
      \Drupal::logger('dcu_navision')->error('Error validating coupon code. Message: @message',
        ['@message' => $e->getMessage()]
      );
      return FALSE;
    }
  }

  /**
   * Change membership type in Nav.
   * @param $params
   *
   * @return bool
   */
  function changeSubscriptionType($params) {
    \Drupal::logger('nav_changesubtype_params')->notice('@data', ['@data' => Json::encode($params)]);
    try {
      $client = $this->connect('Codeunit/MemberManagement');
      $response = $client->ChangeSubscriptionType($params);
      \Drupal::logger('nav_changesubtype_response')->notice('@data', ['@data' => Json::encode($response)]);
      return $response;
    }
    catch(\SoapFault $e) {
      \Drupal::logger('nav_changesubtype_response_error')->notice('@data', ['@data' => Json::encode([$e->getMessage()])]);
      return FALSE;
    }
  }

  /**
   * Resubscribe cancelled membership in Nav.
   * @param $params
   *
   * @return bool
   */
  function resubscribe($memberNumber) {
    $params = array(
      'memberno' => $memberNumber,
      'reason' => 'tbx',
      'changedby' => dcu_member_get_member_update_role(),
      'partnerid' => 'Drupal'
    );
    \Drupal::logger('nav_resubscribe_params')->notice('@data', ['@data' => Json::encode($params)]);
    try {
      $client = $this->connect('Codeunit/MemberManagement');
      $response = $client->ReSubscribe($params);
      \Drupal::logger('nav_resubscribe_response')->notice('@data', ['@data' => Json::encode($response)]);
      return $response;
    }
    catch(\SoapFault $e) {
      \Drupal::logger('nav_resubscribe_response_error')->notice('@data', ['@data' => Json::encode([$e->getMessage()])]);
      return FALSE;
    }
  }

  function createRelative($memberid, $relative) {
    $params = [
      'memberno' => $memberid,
      'reltype' => $relative['reltype'],
      'relname' => $relative['relname'],
      'relbirthday' => $relative['relbirthday'],
      'partnerid' => 'Drupal',
    ];
    try {
      $client = $this->connect('Codeunit/MemberManagement');
      return $client->CreateRelatives($params);
    }
    catch (\SoapFault $e) {
      \Drupal::logger('nav_createrelative')->notice('@data', ['@data' => Json::encode($params, [$e->getMessage()])]);
      return FALSE;
    }
  }

  function updateRelative($memberid, $relative) {
    $params = [
      'memberno' => $memberid,
      'relativeno' => $relative['relativeno'],
      'reltype' => $relative['reltype'],
      'relname' => $relative['relname'],
      'relbirthday' => $relative['relbirthday'],
      'partnerid' => 'Drupal',
    ];
    try {
      $client = $this->connect('Codeunit/MemberManagement');
      return $client->UpdateRelatives($params);
    }
    catch (\SoapFault $e) {
      \Drupal::logger('nav_updaterelative')->notice('@data', ['@data' => Json::encode($params, [$e->getMessage()])]);
      return FALSE;
    }
  }

  function deleteRelative($memberid, $relativeid) {
    $params = [
      'memberno' => $memberid,
      'relativeno' => $relativeid,
    ];
    try {
      $client = $this->connect('Codeunit/MemberManagement');
      return $client->DeleteRelatives($params);
    }
    catch (\SoapFault $e) {
      \Drupal::logger('nav_deleterelative')->notice('@data', ['@data' => Json::encode($params, [$e->getMessage()])]);
      return FALSE;
    }
  }


  /**
   * Get active members by date.
   * @return response data
   */
  function getActiveByDate($params) {
    try {
      $client = $this->connect( 'Page/ActiveMembersByDate');
      return $client->ReadMultiple($params);
    }
    catch(\SoapFault $e) {
      \Drupal::logger('dcu_navision')->error('Error fetching activemembers by date. Message: @message',
        ['@message' => $e->getMessage()]
      );
      return FALSE;
    }
  }

  /**
   * Get active members.
   * @return response data
   */
  function getActiveMembers() {
    try {
      $client = $this->connect( 'Page/ActiveMembers');
      return $client->ReadMultiple();
    }
    catch(\SoapFault $e) {
      \Drupal::logger('dcu_navision')->error('Error fetching activemembers. Message: @message',
        ['@message' => $e->getMessage()]
      );
      return FALSE;
    }
  }

  /**
   * Get magazine members. Read multiple row and returns an array of memberdata.
   * @return array data
   */
  function getMagazineMembers() {
    try {
      $client = $this->connect( 'Page/GetMagazineMembers');
      $result = $client->ReadMultiple();
      if (!empty($result->ReadMultiple_Result)) {
        if (empty($result->ReadMultiple_Result->GetMagazineMembers)) {
          $magazineMembers = [];
        }
        elseif (!is_array($result->ReadMultiple_Result->GetMagazineMembers)) {
          $magazineMembers = [$result->ReadMultiple_Result->GetMagazineMembers];
        }
        else {
          $magazineMembers = $result->ReadMultiple_Result->GetMagazineMembers;
        }
      }
      return $magazineMembers;
    }
    catch(\SoapFault $e) {
      \Drupal::logger('dcu_navision')->error('Error fetching magazine members. Message: @message',
        ['@message' => $e->getMessage()]
      );
      return FALSE;
    }
  }


  /**
   * Get members with recurring payment.
   * @return response data
   */
  function getRecurring() {
    try {
      $client = $this->connect( 'Page/MembersWithRecurringID');
      if (!$client) {
        \Drupal::logger('dcu_navision')->error('Error connecting to MembersWithRecurringID on Navision.');
        return FALSE;
      }
      $result = $client->ReadMultiple();
      $recurringMembers = [];
      if (!empty($result->ReadMultiple_Result)) {
        if (!is_array($result->ReadMultiple_Result->MembersWithRecurringID)) {
          $recurringMembers = [$result->ReadMultiple_Result->MembersWithRecurringID];
        }
        else {
          $recurringMembers = $result->ReadMultiple_Result->MembersWithRecurringID;
        }
      }
      return $recurringMembers;
    }
    catch(\SoapFault $e) {
      \Drupal::logger('dcu_navision')->error('Error fetching recurring memberdata. Message: @message',
        ['@message' => $e->getMessage()]
      );
      return FALSE;
    }
  }

  /**
   * Get members with balance greater than 0
   * @return response data
   */
  function membersWithBalance() {
    ini_set("default_socket_timeout", 3000);
    ini_set('memory_limit','1024M');
    try {
      $client = $this->connect( 'Page/MembersWithBalance');
      if (!$client) {
        \Drupal::logger('dcu_navision')->error('Error connecting to MembersWithBalance on Navision.');
        return FALSE;
      }
      $result = $client->ReadMultiple();
      $members = [];
      if (!empty($result->ReadMultiple_Result)) {
        if (!is_array($result->ReadMultiple_Result->MembersWithBalance)) {
          $members = [$result->ReadMultiple_Result->MembersWithBalance];
        }
        else {
          $members = $result->ReadMultiple_Result->MembersWithBalance;
        }
      }
      return $members;
    }
    catch(\SoapFault $e) {
      \Drupal::logger('dcu_navision')->error('Error fetching membersWithBalance memberdata. Message: @message',
        ['@message' => $e->getMessage()]
      );
      return FALSE;
    }
  }

  /**
   * Check if account has an active membership
   * If membership is active, return true
   *
   * @param $account
   * @return bool
   */
  function checkActiveMembership($account){
    $navData = dcu_member_get_user_navdata($account);
    $membershipEndDate = $navData->subsenddate;
    return strtotime($membershipEndDate) > time();
  }

  /* END NOT YET TESTET FROM D7 */


  /**
   * Returns data array for create and update member from drupal account
   *
   * @param $account
   * @param false $newAccount
   *
   * @return array
   */
  function prepareNavisionMemberParams($account, $newAccount = FALSE, $campaigncode = NULL) {
    $member_term = $account->get('field_membership_type')->referencedEntities();
    $member_term = reset($member_term);
    if (empty($member_term)) {
      return [];
    }
    $member_type = $member_term->get('field_navision_membertype')->value;
    $consent = !empty($account->get('field_contact_consent')->value) ? $account->get('field_contact_consent')->date->format('dmY') : "";
    $birthday = !empty($account->get('field_birthday')->value) ? $account->get('field_birthday')->date->format('dmY') : "";
    $country = $account->get('field_country')->getString();

    if ($account->get('field_use_other_magazine_address')->value) {
      $m_firstname = !$account->get('field_magazine_first_name')->isempty() ? $account->get('field_magazine_first_name')->first()->getString() : '';
      $m_lastname = !$account->get('field_magazine_last_name')->isempty() ? $account->get('field_magazine_last_name')->first()->getString() : '';
      $m_address = !$account->get('field_magazine_address')->isempty() ? $account->get('field_magazine_address')->first()->getString() : '';
      $m_postalcode = !$account->get('field_magazine_zip')->isempty() ? (int)$account->get('field_magazine_zip')->first()->getString() : '';
      $m_city = !$account->get('field_magazine_city')->isempty() ? $account->get('field_magazine_city')->first()->getString() : '';
      $m_country = !$account->get('field_magazine_country')->isempty() ? $account->get('field_magazine_country')->first()->getString() : '';
    }
    else {
      $m_firstname = '';
      $m_lastname = '';
      $m_address = '';
      $m_postalcode = '';
      $m_city = '';
      $m_country = '';
    }
    $mailchimp_status = dcu_member_mailchimp_status($account->get('mail')->getString());

    $params = [
      'firstname' => $account->get('field_first_name')->getString(),
      'lastname' => $account->get('field_last_name')->getString(),
      'birthday' => $birthday,
      'phoneno' => $account->get('field_mobile_phone')->getString(),
      'email' => $account->get('mail')->getString(),
      'address' => $account->get('field_address')->getString(),
      'postalcode' => (int)$account->get('field_zip')->getString(),
      'city' => $account->get('field_city')->getString(),
      'country' => $country,
      'magazinefirstname' => $m_firstname,
      'magazinelastname' => $m_lastname,
      'magazineaddress' => $m_address,
      'magazinepostalcode' => $m_postalcode,
      'magazinecity' => $m_city,
      'magazinecountry' => $m_country,
      'newsletter' => !empty($mailchimp_status),
      'magazineletter' => !empty($account->get('field_magazine')->value),
      'partnerid' => 'Drupal',
      'chargetype' => empty($account->get('field_free_member')->value) ? 'Korttilmelding' : 'Gratist',
      'comment' => $account->get('field_customer_comments')->getString(),
      'consent' => $consent,
      'campaigncode' => !empty($campaigncode) ? $campaigncode : '',
    ];

    if ($newAccount) {
      $params['membertype'] = ucfirst($member_type);
      $params['pensionvalid'] = '';
      $params['createdby'] = dcu_member_get_member_update_role();
    }
    else {
      $params['memberno'] = $account->get('field_memberid')->getString();
      $params['changedby'] = dcu_member_get_member_update_role();
    }
    return $params;
  }
}
