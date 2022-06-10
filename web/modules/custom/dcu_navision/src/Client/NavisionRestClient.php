<?php

namespace Drupal\dcu_navision\Client;

use Drupal\Core\Site\Settings;
use Drupal\Component\Serialization\Json;
use Drupal\user\Entity\User;
use GuzzleHttp\Exception\RequestException;
use Drupal\Core\Cache\Cache;
use GuzzleHttp\Client;

class NavisionRestClient {

  /**
   * @var \GuzzleHttp\Client
   */
  protected $client;
  protected $url;
  protected $options;
  protected $auth;

  function __construct($http_client_factory) {
    $usr = Settings::get('dcu_navision_rest_login', '');
    $psw = Settings::get('dcu_navision_rest_psw', '');
    $this->url = Settings::get('dcu_navision_rest_url', '');

    if (!empty(Settings::get('dcu_navision_rest_oauth', ''))) {
      $accessToken = \Drupal::service('oauth2_client.service')->getAccessToken('nav_oauth');
      $refreshToken = $accessToken->getRefreshToken();
      $expirationTimestamp = $accessToken->getExpires();
      if (!empty($expirationTimestamp) && $accessToken->hasExpired() && empty($refreshToken)) {
        // should be taken care of by refreshtoken, but that doesnt seem to work pt $accessToken->getRefreshToken();
        \Drupal::service('oauth2_client.service')->clearAccessToken('nav_oauth');
        $accessToken = \Drupal::service('oauth2_client.service')->getAccessToken('nav_oauth');
      }
      $token = $accessToken->getToken();
      $this->auth = 'Bearer ' . $token;
    }
    else {
      $this->auth = 'Basic '. base64_encode ($usr . ':' . $psw);
    }

    $this->options = [
      'base_uri' => $this->url,
      'headers' => [
        'Authorization' => $this->auth,
        'Content-Type' => 'application/json',
      ]
    ];
    try {
      $this->client = $http_client_factory->fromOptions([$this->options]);
    }
    catch (RequestException $e) {
      watchdog_exception('navision_rest_client', $e);
    }
  }

  /**
   * Check environment navision url and returns if its test or production base.
   * @return string
   */
  public function getEnvironment() {
    if (strpos($this->url, 'DCU_Sandbox')) {
      return 'TEST';
    }
    else {
      return 'PRODUCTION';
    }
  }

  public function getUrl() {
    return $this->url;
  }

  /**
   * Check if admin has blocked access to Navision
   * @return bool
   */
  public function navIsBlocked() {
    $config = \Drupal::config('dcu_admin.sitesettings');
    if ($config->get('block_access_to_user_data')) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Genereric rest call to Navision. Checks if access to navision is blocked.
   * merges given params with default options and sends request type to nav.
   * Logs errors and returns FALSE or body result data on success.
   *
   * @param $endpoint
   * @param $type
   * @param $params
   *
   * @return false|mixed
   */
  private function callRest($endpoint, $type = 'get', $logId = 'dcu_navision_api', $params = []) {
    if ($this->navIsBlocked()) {
      return FALSE;
    }
    try {
      $options = array_merge($this->options, $params);
      switch ($type) {
        case 'get':
          $response = $this->client->get($endpoint, $options);
          break;
        case 'post':
          $response = $this->client->post($endpoint, $options);
          break;
      }
      $result = json_decode($response->getBody());
    }
    catch (RequestException $e) {
      $logErrorId = $logId . "_error";
      watchdog_exception('dcu_navision_rest_client', $e);
      if ($e->hasResponse()) {
        $exception = (string) $e->getResponse()->getBody()->getContents();
      }
      else {
        $exception = $e->getMessage();
      }
      \Drupal::logger($logErrorId)->error('@data', ['@data' => Json::encode(['error' => $exception, 'endpoint' => $endpoint, 'type' => $type, 'params' => $params])]);
      return FALSE;
    }
    return $result;
  }

  /**
   * Create new member based on Drupal account
   *
   * @param $account
   * @param null $campaigncode
   *
   * @return int|bool memberno or FALSE on error
   */
  public function createMember($account, $campaigncode = NULL) {
    if (empty($account)) {
      return FALSE;
    }
    $endpoint = "MemberManagement_CreateMember?company=DCU";
    \Drupal::logger('nav_create_member_data_prepare')->notice('@data', ['@data' => Json::encode($account->id())]);
    $memberParams = $this->prepareNavisionMemberParams($account, TRUE, $campaigncode);
    \Drupal::logger('nav_create_member_data_ready')->notice('@data', ['@data' => Json::encode($memberParams)]);
    try {
      $options = $this->options;
      $options['body'] = Json::encode($memberParams);
      $response = $this->client->post($endpoint, $options);
      $result = json_decode($response->getBody());
      \Drupal::logger('nav_create_member_result')->notice('@data', ['@data' => Json::encode($result)]);
    }
    catch(RequestException $e) {
      if ($e->hasResponse()) {
        $exception = (string) $e->getResponse()->getBody()->getContents();
      }
      else {
        $exception = $e->getMessage();
      }
      \Drupal::logger('dcu_navision')->error('Error creating membership. Message: @message', ['@message' => $exception]);
      \Drupal::logger('nav_create_member_error')->notice('@data', ['@data' => Json::encode([$memberParams, $exception])]);

      //SEND ALERT MAIL.
      dcu_utility_send_alert_mail(['body' => '<pre>' . print_r($memberParams, true) . '</pre><br><br><strong>Error from Nav:</strong><br><pre>' . print_r(Json::decode($exception), true) . '</pre><br><br><strong>Error fra Nav 2</strong><br>'.Json::encode([$exception])]);
      return FALSE;
    }
    if (!$result) {
      \Drupal::logger('nav_create_member_failed')->notice('@data', ['@data' => Json::encode($memberParams)]);
      return FALSE;
    }
    $memberNumber = $result->value;
    if (!is_numeric((int)$memberNumber)) {
      return FALSE;
    }
    return $memberNumber;
  }


  /**
   * Update memberdata from Drupal User account
   *
   * @param $account User Drupal user account to update
   *
   * @return array|bool result or FALSE on error
   */
  function updateMember($account) {
    if (empty($account)) {
      return FALSE;
    }
    $endpoint = "MemberManagement_UpdateMember?company=DCU";
    $memberParams = $this->prepareNavisionMemberParams($account);
    \Drupal::logger('nav_update_member_data_params')->notice('@data', ['@data' => Json::encode($memberParams)]);
    try {
      $options = $this->options;
      $options['body'] = Json::encode($memberParams);
      $response = $this->client->post($endpoint, $options);
      $result = json_decode($response->getBody());
      \Drupal::logger('nav_update_member_result')->notice('@data', ['@data' => Json::encode($result->value)]);
    }
    catch(RequestException $e) {
      if ($e->hasResponse()) {
        $exception = (string) $e->getResponse()->getBody()->getContents();
      }
      else {
        $exception = $e->getMessage();
      }
      \Drupal::logger('dcu_navision')->error('Error updating member. Message: @message', ['@message' => $exception]);
      \Drupal::logger('nav_update_member_data_response_error')->error('@data', ['@data' => Json::encode([$memberParams, $exception, $e->getCode()])]);
      return FALSE;
    }
    if (!$result) {
      \Drupal::logger('nav_update_member_failed')->notice('@data', ['@data' => Json::encode($memberParams)]);
      return FALSE;
    }
    return $result->value;
  }


  /**
   * Get memberdata from Navision by membernumber.
   *
   * @param $memberNumber
   *
   * @return array|bool Navision memberdata or FALSE on error
   */
  public function getMemberData($memberNumber) {
    $endpoint = "Company('DCU')/GetMember('" . $memberNumber . "')";
    if (!$result = $this->callRest($endpoint,'get', 'nav_get_member')) {
      \Drupal::logger('dcu_navision')->error('Error calling getMemberData on Navision with memberno: ' . $memberNumber);
      return FALSE;
    }
    return $result;
  }

  /**
   * @param $memberEmail
   *
   * @return false
   */
  public function getMemberDataByEmail($memberEmail) {
    $endpoint = "Company('DCU')/GetMember()?\$filter=email eq ('" . $memberEmail . "')";
    if (!$result = $this->callRest($endpoint,'get', 'nav_get_member_by_email')) {
      \Drupal::logger('dcu_navision')->error('Error calling getMemberData on Navision with member email: ' . $memberEmail);
      return FALSE;
    }

    if (!empty($result->value)) {
      return $result->value[0];
    }
    else {
      return FALSE;
    }
  }

  /**
   * Get relatives data from Nav.
   * @param $memberNumber
   * @return bool, array
   */
  function getRelatives($memberNumber) {
    if (empty($memberNumber)) {
      return FALSE;
    }
    $endpoint = "Company('DCU')/GetRelatives?\$filter=memberno eq('" . $memberNumber . "')";
    if (!$result = $this->callRest($endpoint,'get', 'nav_relatives')) {
      \Drupal::logger('dcu_navision')->error('Error fetching relatives data from Dcu Navision.');
      \Drupal::messenger()->addMessage(t('Could not fetch relatives for member'), 'error');
      return FALSE;
    }
    return $result->value;
  }


  /**
   * Update relatives data from Nav.
   * @param $memberNumber
   * @return bool, array
   */
  function updateRelative($memberNumber, $relativeData) {
    if (empty($memberNumber) || empty($relativeData)) {
      return FALSE;
    }
    $endpoint = "MemberManagement_UpdateRelatives?company=DCU";
    $params = [
      'memberno' => $memberNumber,
      'relativeno' => $relativeData['relativeno'],
      'reltype' => $relativeData['reltype'],
      'relname' => $relativeData['relname'],
      'relbirthday' => $relativeData['relbirthday'],
      'partnerid' => 'Drupal',
    ];

    $options['body'] = Json::encode($params);
    if (!$result = $this->callRest($endpoint,'post', 'nav_updaterelative', $options)) {
      \Drupal::logger('dcu_navision')->error('Error updating relative data from Dcu Navision.');
      \Drupal::messenger()->addMessage(t('Could not update relative for member'), 'error');
      return FALSE;
    }
    return $result->value;
  }


  /**
   * @param $memberNumber
   * @param $relative
   *
   * @return false|mixed
   */
  function createRelative($memberNumber, $relative) {
    $params = [
      'memberno' => $memberNumber,
      'reltype' => $relative['reltype'],
      'relname' => $relative['relname'],
      'relbirthday' => $relative['relbirthday'],
      'partnerid' => 'Drupal',
    ];
    \Drupal::logger('nav_createrelative_params')->notice('@data', ['@data' => Json::encode($params)]);
    $endpoint = 'MemberManagement_CreateRelatives?company=DCU';
    $options['body'] = Json::encode($params);
    if (!$result = $this->callRest($endpoint,'post', 'nav_createrelative', $options)) {
      \Drupal::logger('nav_createlative_response_error')->error('Error creating relative');
      return FALSE;
    }
    \Drupal::logger('nav_createlative_response')->notice('@data', ['@data' => Json::encode(['params' => $params, 'result' => $result->value])]);
    return $result->value;
  }


  /**
   * Delete relative from member
   *
   * @param $memberNumber
   * @param $relativeId
   *
   * @return false|mixed
   */
  function deleteRelative($memberNumber, $relativeId) {
    if (empty($memberNumber) || empty($relativeId)) {
      return FALSE;
    }
    $endpoint = "MemberManagement_DeleteRelatives?company=DCU";
    $params = [
      'memberno' => $memberNumber,
      'relativeno' => $relativeId,
    ];
    $options['body'] = Json::encode($params);
    if (!$result = $this->callRest($endpoint,'post', 'nav_deleterelative', $options)) {
      \Drupal::logger('dcu_navision')->error('Error deleting relative data from Dcu Navision.');
      \Drupal::messenger()->addMessage(t('Could not delete relative for member'), 'error');
      return FALSE;
    }
    return $result->value;
  }


  /**
   * Get memberno from Navision by email.
   *
   * @param $email
   *
   * @return int|bool Navision memberno or FALSE on error
   */
  public function getMemberNumberByEmail($email) {
    $endpoint = "MemberManagement_GetMemberByEmail?company=DCU";
    $options['body'] = Json::encode(['email' => $email]);
    if (!$result = $this->callRest($endpoint,'post', 'nav_get_member_bymail', $options)) {
      \Drupal::logger('dcu_navision')->error('Error calling getMemberData on Navision with email: ' . $email);
      return FALSE;
    }
    return $result->value;
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
    if (empty($email)) {
      return FALSE;
    }
    $params['email'] = $email;
    $options['body'] = Json::encode($params);
    $endpoint = "MemberManagement_ValidateEmail?company=DCU";
    if (!$result = $this->callRest($endpoint,'post', 'nav_email_exist', $options)) {
      \Drupal::logger('dcu_navision')->error('Error calling emailExist on Navision.');
      return FALSE;
    }

    //Email is in Nav.
    if ($result->value == true) {
      //Lets check if email in Drupal.
      $database = \Drupal::database();
      $row = $database->query("SELECT mail FROM users_field_data WHERE mail = :value", [':value' => $email])->fetchObject();

      //User not in Drupal. Create user with data from Nav.
      if (!$row) {
        if (empty($memberData = $this->getMemberDataByEmail($email))) {
          \Drupal::messenger()->addMessage('No data found for member email');
          return;
        }

        //Create user in Drupal.
        //This result in msg to user to login or forgot password.
        if (is_object($memberData)) {
          $password = base64_encode(openssl_random_pseudo_bytes(10));
          $user = dcu_navision_create_user_from_nav_data($memberData, $password);
          $user->activate();//Set to active to be able to use Forgot password.
          $user->save();
        }
      }
    }
    return $result->value;
  }

  /**
   *
   * Get membership price from membertype and optional campaigncode.
   *
   * @param $membertype
   * @param string $campaigncode
   *
   * @return int|bool Price or FALSE on error
   */
  public function getMembershipPrice($membertype, $campaigncode = '') {
    if (empty($membertype)) {
      return FALSE;
    }
    $cache_id = 'dcu_navision:price:' . strtolower($membertype.$campaigncode);
    $cache_obj = \Drupal::cache()->get($cache_id);

    if (!empty($cache_obj)) {
      return $cache_obj->data;
    }
    else {
      $params['membertype'] = $membertype;
      $params['campaigncode'] = $campaigncode;
      $endpoint = "MemberManagement_GetPrice?company=DCU";
      $options['body'] = Json::encode($params);
      if (!$result = $this->callRest($endpoint,'post', 'nav_memberprice' , $options)) {
        \Drupal::logger('dcu_navision')->error('Error getting membership price.');
        return FALSE;
      }
      \Drupal::cache()->set($cache_id, $result->value, time() + 60*60*12);
      return $result->value;
    }
  }

  /**
   * Validates campaign code.
   *
   * @param $code
   *
   * @return bool
   */
  function validateCampaignCode($code = NULL) {
    if (empty($code)) {
      return FALSE;
    }
    $endpoint = 'MemberManagement_ValidateCampaignCode?company=DCU';
    $params['campaigncode'] = $code;
    $options['body'] = Json::encode($params);
    if (!$result = $this->callRest($endpoint,'post', 'nav_campaigncode', $options)) {
      \Drupal::logger('dcu_navision')->error('Error validating campaigncode');
      return FALSE;
    }
    \Drupal::logger('nav_campaigncode_validation')->notice('@data', ['@data' => Json::encode(['code' => $code, 'valid' => $result->value])]);
    return $result->value;
  }

  /**
   * Change membership type in Nav.
   * @param $params
   *       - 'memberno'
   *       - 'newmembertype'
   *       - 'pensionvalidated'
   *       - 'changedby'
   *       - 'partnerid'
   *
   * @return bool
   */
  function changeSubscriptionType($params) {
    if (empty($params)) {
      return FALSE;
    }
    \Drupal::logger('nav_changesubtype_params')->notice('@data', ['@data' => Json::encode($params)]);
    $endpoint = 'MemberManagement_ChangeSubscriptionType?company=DCU';
    $options['body'] = Json::encode($params);
    if (!$result = $this->callRest($endpoint,'post', 'nav_changesubtype', $options)) {
      \Drupal::logger('nav_changesubtype_response_error')->error('Error changing subscription type');
      return FALSE;
    }
    \Drupal::logger('nav_changesubtype_response')->notice('@data', ['@data' => Json::encode(['params' => $params, 'result' => $result->value])]);
    return $result->value;
  }


  /**
   * TODO: TBX - should this be called with params as changesubcriptiontype ?
   * Resubscribe cancelled membership in Nav.
   * @param $params
   *
   * @return bool
   */
  function resubscribe($memberNumber) {
    if (empty($memberNumber)) {
      return FALSE;
    }
    $params = array(
      'memberno' => $memberNumber,
      'changedby' => dcu_member_get_member_update_role(),
      'partnerid' => 'Drupal'
    );
    \Drupal::logger('nav_resubscribe_params')->notice('@data', ['@data' => Json::encode($params)]);

    $endpoint = 'MemberManagement_ReSubscribe?company=DCU';
    $options['body'] = Json::encode($params);
    if (!$result = $this->callRest($endpoint,'post', 'nav_resubscribe', $options)) {
      \Drupal::logger('nav_changesubtype_response_error')->error('Error changing subscription type');
      return FALSE;
    }
    \Drupal::logger('nav_resubscribe_response')->notice('@data', ['@data' => Json::encode(['params' => $params, 'result' => $result->value])]);
    return $result->value;
  }


  /**
   * Registers payment based on data from paymentresult
   *
   * @param $params
   *       - 'memberno'
   *       - 'paymentdate'
   *       - 'transactionid'
   *       - 'amount'
   *       - 'changedby'
   *       - 'payment'
   *       - 'domesticcard'
   *       - 'partnerid'
   *       - 'recurringid'
   *
   * @return false|mixed
   */
  public function registerPayment($params) {
    \Drupal::logger('nav_register_payment_call')->notice('@data', ['@data' => Json::encode($params)]);
    $endpoint = "MemberManagement_MembershipPayment?company=DCU";
    try {
      $options = $this->options;
      $options['body'] = Json::encode($params);
      $response = $this->client->post($endpoint, $options);
      $result = json_decode($response->getBody());
      \Drupal::logger('nav_register_payment_response')->notice('@data', ['@data' => Json::encode($result->value)]);
    }
    catch(RequestException $e) {
      if ($e->hasResponse()) {
        $exception = (string) $e->getResponse()->getBody()->getContents();
      }
      else {
        $exception = $e->getMessage();
      }
      \Drupal::logger('dcu_navision')->error('Failed to register payment receipt with navision. Message : @exception', ['@exception' => $exception]);
      \Drupal::logger('nav_register_payment_error')->error('@data', ['@data' => Json::encode([$params, $exception, $e->getCode()])]);
      return FALSE;
    }
    if (!$result) {
      \Drupal::logger('nav_register_payment_fail')->notice('@data', ['@data' => Json::encode($params)]);
      return FALSE;
    }
    return $result->value;
  }


  /**
   * Get active members by date.
   *
   * @param $params array Of parameters for filter:
   *  - type : either createddate or changeddate
   *  - from: YYYY-MM-DD
   *  - to: YYYY-MM-DD
   * @return array|bool Navision response data array of memberdata or false on error
   */
  function getActiveByDate($params) {
    $endpoint = "Company('DCU')/ActiveMembersByDate";
    $filter = '$filter=' . $params['type'];
    $logic = '';
    if (!empty($params['from'])) {
      $filter .= ' ge ' . $params['from'];
      $logic = ' and ';
    }
    if (!empty($params['to'])) {
      $filter .= $logic;
      $filter .=  $params['type'] . ' le ' . $params['to'];
    }
    $endpoint .= '?' . $filter;

    if (!$result = $this->callRest($endpoint,'get', 'nav_active_members', ['read_timeout' => 60, 'timeout' => 60])) {
      \Drupal::logger('nav_active_members_error')->error('Error fetching active members by date');
      \Drupal::messenger()->addMessage(t('Could not fetch active members'), 'error');
      return FALSE;
    }
    $members = $result->value;
    while (!empty($result->{'@odata.nextLink'})) {
      $result = $this->callRest($result->{'@odata.nextLink'}, 'get', 'nav_active_members', ['read_timeout' => 60, 'timeout' => 60]);
      $members = array_merge($members, $result->value);
    }
    return $members;
  }


  /**
   * Get active members. Fetch multiple pages and returns an array of memberdata
   * @return array|bool Navision response data array of memberdata or false on error
   */
  function getActiveMembers() {
    $endpoint = "Company('DCU')/ActiveMembers()";
    if (!$result = $this->callRest($endpoint,'get', 'nav_active_members', ['read_timeout' => 60, 'timeout' => 60])) {
      \Drupal::logger('nav_active_members_error')->error('Error fetching active members Navision');
      \Drupal::messenger()->addMessage(t('Could not fetch active members'), 'error');
      return FALSE;
    }
    $members = $result->value;
    while (!empty($result->{'@odata.nextLink'})) {
      $result = $this->callRest($result->{'@odata.nextLink'}, 'get', 'nav_active_members', ['read_timeout' => 60, 'timeout' => 60]);
      $members = array_merge($members, $result->value);
    }
    return $members;
  }


  /**
   * Get magazine members. Fetch multiple pages and returns an array of memberdata.
   * @return array|bool Navision response data array of memberdata or false on error
   */
  function getMagazineMembers() {
    $endpoint = "Company('DCU')/GetMagazineMembers";
    if (!$result = $this->callRest($endpoint,'get', 'nav_magazine_members', ['read_timeout' => 60])) {
      \Drupal::logger('nav_magazine_members_error')->error('Error fetching magazine members Navision');
      \Drupal::messenger()->addMessage(t('Could not fetch magazine members'), 'error');
      return FALSE;
    }
    $members = $result->value;
    while (!empty($result->{'@odata.nextLink'})) {
      $result = $this->callRest($result->{'@odata.nextLink'}, 'get', 'nav_active_members', ['read_timeout' => 60]);
      $members = array_merge($members, $result->value);
    }
    return $members;
  }


  /**
   * Get members with recurring payment. Fetch multiple pages and returns an array of memberdata.
   * @return array|bool Navision response data array of memberdata or false on error
   */
  function getRecurring() {
    $endpoint = "Company('DCU')/MembersWithRecurringID";
    if (!$result = $this->callRest($endpoint,'get', 'nav_recurring_members', ['read_timeout' => 60])) {
      \Drupal::logger('nav_recurring_members_error')->error('Error fetching recurring memberdata');
      \Drupal::messenger()->addMessage(t('Could not fetch recurring members'), 'error');
      return FALSE;
    }
    $members = $result->value;
    while (!empty($result->{'@odata.nextLink'})) {
      $result = $this->callRest($result->{'@odata.nextLink'}, 'get', 'nav_recurring_members', ['read_timeout' => 60]);
      $members = array_merge($members, $result->value);
    }
    return $members;
  }


  /**
   * Get members with balance greater than 0. Fetch multiple pages and returns an array of memberdata.
   * @return array|bool Navision response data array of memberdata or false on error
   */
  function membersWithBalance() {
    $endpoint = "Company('DCU')/MembersWithBalance";
    if (!$result = $this->callRest($endpoint,'get', 'nav_balance_members', ['read_timeout' => 60])) {
      \Drupal::logger('nav_getrecurring_members_error')->error('Error fetching membersWithBalance memberdata');
      \Drupal::messenger()->addMessage(t('Could not fetch members with balance'), 'error');
      return FALSE;
    }
    $members = $result->value;
    while (!empty($result->{'@odata.nextLink'})) {
      $result = $this->callRest($result->{'@odata.nextLink'}, 'get', 'nav_balance_members', ['read_timeout' => 60]);
      $members = array_merge($members, $result->value);
    }
    return $members;
  }


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
      $m_postalcode = !$account->get('field_magazine_zip')->isempty() ? $account->get('field_magazine_zip')->first()->getString() : '';
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
      'mobileno' => '',
      'email' => $account->get('mail')->getString(),
      'address' => $account->get('field_address')->getString(),
      'postalcode' => $account->get('field_zip')->getString(),
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
    ];

    if ($newAccount) {
      $params['membertype'] = ucfirst($member_type);
      $params['pensionvalid'] = FALSE;
      $params['createdby'] = dcu_member_get_member_update_role();
      $params['campaigncode'] = !empty($campaigncode) ? $campaigncode : '';
    }
    else {
      $params['memberno'] = $account->get('field_memberid')->getString();
      $params['changedby'] = dcu_member_get_member_update_role();
    }
    return $params;
  }

}
