<?php
/**
 * Implements hook_drush_command().
 */
function dcu_utility_drush_command() {
  $items['dcu_utility-campsites-advantage'] = array(
    'callback' => 'dcu_utility_campsites_advantage',
    'aliases' => array('campsite_advantage'),
  );
  $items['dcu_utility-touch-all-campsites'] = array(
    'callback' => 'dcu_utility_touch_all_campsites',
    'aliases' => array('touch_all_campsites'),
  );
  $items['dcu_utility-touch-all-users'] = array(
    'callback' => 'dcu_utility_touch_all_users',
    'options' => array(
      'role' => 'role',
      'uid' => 'uid'
    ),
    'aliases' => array('touch_all_users'),
  );

  //Run through all active Nav members and update Drupal users.
  $items['dcu_utility-sync-nav-users-with-drupal-users'] = array(
    'callback' => 'dcu_utility_sync_nav_users_with_drupal_users',
    'aliases' => array('sync_active_nav_users'),
  );

  //Create missing Drupal users from data in Nav active members.
  $items['dcu_utility-create-missing-drupal-users-from-nav-active-members'] = array(
    'callback' => 'dcu_utility_create_missing_drupal_users_from_nav_active_members',
    'aliases' => array('create_missing_drupal_members'),
  );

  //Nav active users total count.
  $items['dcu_utility-nav-count-active-users'] = array(
    'callback' => 'dcu_utility_nav_count_active_users',
    'aliases' => array('active_nav_members_count'),
  );

  $items['dcu_utility-forum-cleanup'] = array(
    'callback' => 'dcu_utility_forum_cleanup',
    'aliases' => array('forum_cleanup'),
  );
  $items['dcu_utility-failed-bambora'] = array(
    'callback' => 'dcu_utility_failed_bambora_callbacks',
    'aliases' => array('get_failed_bam'),
  );
  $items['dcu_utility-fetch-google-placeid'] = array(
    'callback' => 'dcu_utility_google_placeid',
    'aliases' => array('dcu_gpid'),
    'options' => array(
      'nid' => 'nid',
      'forceoverwrite' => 'forceoverwrite',
    ),
  );
  $items['dcu_utility-campaign-monitor-csv'] = array(
    'callback' => 'dcu_utility_campaign_monitor_csv',
    'aliases' => array('cm_csv'),
  );
  $items['dcu_utility-set-automatic-url-alias'] = array(
    'callback' => 'dcu_utility_set_automatic_url_alias',
    'aliases' => array('set_alias'),
  );
  $items['dcu_utility-set-country-region-campsites'] = array(
    'callback' => 'dcu_utility_set_country_region_campsites',
    'aliases' => array('campsites'),
  );
  $items['dcu_utility-email-check'] = array(
    'callback' => 'dcu_utility_email_check',
    'aliases' => array('email_check'),
  );
  $items['dcu_utility-email-lower'] = array(
    'callback' => 'dcu_utility_email_lower',
    'aliases' => array('email_lower'),
  );
  $items['dcu_utility-expiredate'] = array(
    'callback' => 'dcu_utility_expiredate',
    'aliases' => array('qrcodedate'),
  );
  $items['dcu_utility-subpage-redirects'] = array(
    'callback' => 'dcu_utility_subpage_redirects',
    'aliases' => array('subpage_redirects'),
  );

  $items['dcu_utility-nav-drupal-sync'] = array(
    'callback' => 'dcu_utility_nav_drupal_sync',
    'aliases' => array('nav_drupal_sync'),
  );

  return $items;
}

function dcu_utility_expiredate() {
  print 'Fest ' . date("d-m-Y", strtotime('+30 day')) . PHP_EOL;
}
/**
 * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
 * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
 * @throws \Drupal\Core\Entity\EntityStorageException
 */
function dcu_utility_campsites_advantage() {
  ini_set('memory_limit', '1048M');
  ini_set('max_execution_time', 600);

  $entity = \Drupal::entityTypeManager()->getStorage('node');
  $database = \Drupal::database();
  $query = $database->query("select
    n.nid,
    ac.field_advantage_campsite_value as acv
    from node n
    left join node__field_advantage_campsite ac on ac.entity_id = n.nid
    where n.type = 'campsites'"
  );
  $result = $query->fetchAll();

  //$query = db_query("select zip from {zipcodes} where city = :city limit 1", array(":city" => $city))->fetchObject();

  if (!empty($result)) {
    $i = 1;
    foreach ($result as $campsite) {
      $node = $entity->load($campsite->nid);
      if ($node->get('field_advantage_campsite')->first()->value == 1) {
        $node->set('field_search_icons', ['target_id' => 3742]);
        $node->save();
      }
      else {
        $node->set('field_search_icons', ['target_id' => 3743]);
        $node->save();
      }
      print $i++ . ' ' . $node->label() . PHP_EOL;
    }
  }
}

/**
 * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
 * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
 * @throws \Drupal\Core\Entity\EntityStorageException
 */
function dcu_utility_touch_all_campsites() {
  ini_set('memory_limit', '1048M');
  ini_set('max_execution_time', 600);

  $entity = \Drupal::entityTypeManager()->getStorage('node');
  $database = \Drupal::database();
  $query = $database->query("select n.nid from node n where n.type = 'campsites' or n.type = 'dcu_campsite'"
  );
  $result = $query->fetchAll();

  if (!empty($result)) {
    $i = 1;
    foreach ($result as $campsite) {
      $node = $entity->load($campsite->nid);
      $node->save();
      print $i++ . ' ' . $node->label() . PHP_EOL;
    }
  }
}

/**
 * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
 * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
 * @throws \Drupal\Core\Entity\EntityStorageException
 */
function dcu_utility_touch_all_users() {
  $role_to_work = drush_get_option('role');
  $uid = drush_get_option('uid');

  ini_set('memory_limit', '1048M');
  ini_set('max_execution_time', 1200);

  $roles = array(
    'administrator',
    'agent',
    'benefit_owner',
    'campsite_editor',
    'dcu_campsite_owner',
    'dcu_membership',
    'developer',
    'district_editor',
    'forum',
    'icamp',
    'member_service',
    'other_campsite_owner',
    'primary_agent',
    'site_administrator'
  );

  foreach ($roles as $role) {
    print 'Rolle....................' . $role . PHP_EOL;
    $database = \Drupal::database();
    $query = $database->query("select u.uid, count(1) as count
      from users u
      left join user__roles ur on ur.entity_id = u.uid
      where ur.roles_target_id = :role
      group by u.uid
      HAVING COUNT(*) > 1
      order by u.uid desc", array(':role' => $role)
    );
    $result = $query->fetchAll();

    if (!empty($result)) {
      $i = 1;
      foreach ($result as $uid) {
        //$user = $entity->load($uid->uid);
        $user = \Drupal\user\Entity\User::load($uid->uid);
        $roles = $user->getRoles();
        foreach ($roles as $role) {
          if ($role != 'authenticated') {
            $user->addRole($role);
            $user->save();
          }
        }
        print $i++ . ' ' . $uid->uid . PHP_EOL;
      }
    }
  }
}

/**
 * This can update all Drupal users with data from Nav.
 * First remove 'DCU membership' on all users.
 * Then loop over all active nav members and set 'DCU membership'
 */
use Drupal\Component\Serialization\Json;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;

function dcu_utility_sync_nav_users_with_drupal_users() {
  ini_set('memory_limit', '1548M');
  ini_set('max_execution_time', 1200);

  //Update all Drupal users by remove 'DCU membership'.
  $database = \Drupal::database();
  $query = $database->query("select u.uid, m.field_memberid_value as memberid
    from users u
    left join user__field_memberid m on m.entity_id = u.uid
    left join user__roles ur on ur.entity_id = u.uid
    where m.field_memberid_value is not null and ur.roles_target_id = 'dcu_membership'"
  );
  $drupal_dcu_membership_users = $query->fetchAll();

  $j = 1;
  foreach ($drupal_dcu_membership_users as $drupal_membership_user) {
    print $j++ . ' reset user ' . $drupal_membership_user->uid . PHP_EOL;
    $user = \Drupal\user\Entity\User::load($drupal_membership_user->uid);
    $user->removeRole('dcu_membership');
    $user->save();
  }

  //All active members.
  //TODO: tbx test new client
  $nav_members = \Drupal::service('dcu_navision.client')->getActiveMembers();
  foreach ($nav_members as $nav_active_member) {
    $nav_active_members[] = $nav_active_member->memberno;
  }

  $database = \Drupal::database();
  $query = $database->query("select u.uid, m.field_memberid_value as memberid
    from users u
    left join user__field_memberid m on m.entity_id = u.uid"
  );
  $drupal_users = $query->fetchAll();

  //Update Drupal users with info from Nav.
  $active_count = 1;
  $i = 0;
  foreach ($drupal_users as $drupal_user) {
    if (in_array($drupal_user->memberid, $nav_active_members)) {
      print $i++ . ' Active user - ' . $drupal_user->memberid . '   - uid ' . $drupal_user->uid . PHP_EOL;
      $active_count++;

      $user = \Drupal\user\Entity\User::load($drupal_user->uid);
      $user->addRole('dcu_membership');
      $user->save();
    }
  }
  print 'Total ' . $active_count . PHP_EOL;
}

/**
 * Make sure all active users in Nav olso exist in Drupal.
 * This should not be necessary - but we found some that only
 * existed in Nav.
 */
function dcu_utility_create_missing_drupal_users_from_nav_active_members() {
  ini_set('memory_limit', '1048M');
  ini_set('max_execution_time', 1200);

  //All active Nav members.
  //TODO: tbx test new client
  $nav_members = \Drupal::service('dcu_navision.client')->getActiveMembers();
  foreach ($nav_members as $nav_active_member) {
    $nav_active_members[] = $nav_active_member->memberno;
  }

  //All Drupal users.
  $database = \Drupal::database();
  $query = $database->query("select u.uid, m.field_memberid_value as memberid
    from users u
    left join user__field_memberid m on m.entity_id = u.uid"
  );
  $drupal_users = $query->fetchAll();

  $drupal_users_only_memberid = [];
  foreach ($drupal_users as $dam) {
    $drupal_users_only_memberid[] = $dam->memberid;
  }

  //Build array of missing members.
  foreach ($nav_active_members as $memberid) {
    if (!in_array($memberid, $drupal_users_only_memberid)) {
      print $memberid . PHP_EOL;
      $nav_member_data = \Drupal::service('dcu_navision.client')->getMemberData($memberid);
      $password = base64_encode(openssl_random_pseudo_bytes(10));
      dcu_navision_create_user_from_nav_data($nav_member_data, $password);
    }
  }
}

/**
 * Lazy drush function to show how many active users in Nav.
 */
function dcu_utility_nav_count_active_users() {
  ini_set('memory_limit', '1048M');
  ini_set('max_execution_time', 1200);

  //All active Nav members.
  $nav_members = \Drupal::service('dcu_navision.client')->getActiveMembers();
  foreach ($nav_members as $nav_active_member) {
    $nav_active_members[] = $nav_active_member->memberno;
  }
  print 'Total active members in Nav: ' . count($nav_active_members) . PHP_EOL;
}

/**
 * Forum clean up - and it also delete comments.
 */
function dcu_utility_forum_cleanup() {
  ini_set('memory_limit', '1048M');
  ini_set('max_execution_time', 1200);
  $entity = \Drupal::entityTypeManager()->getStorage('node');
  $database = \Drupal::database();
  $query = $database->query("select
    n.nid,
    n.created,
    from_unixtime(n.created) as created2

    from node_field_data n
    where
    n.type = 'forum' and n.created < 1451516400
    order by n.created desc"
  );
  $result = $query->fetchAll();

  if (!empty($result)) {
    $i = 1;
    foreach ($result as $forum) {
      $node = $entity->load($forum->nid);
      //      $node->save();
      $node->delete();
      print $i++ . ' ' . $forum->nid . PHP_EOL;
    }
  }
}

/**
 * Export rejected bambora payment calls from dblog to csv file.
 * Originally used at site launch to aid bookkeeping.
 */
function dcu_utility_failed_bambora_callbacks() {
  $database = \Drupal::database();
  $query = $database->query("select * from dblog_persistent where type = :type order by timestamp", array(':type' => 'register_bambora_payment_call_reject'));
  $results = $query->fetchAll();

  echo 'dblog fix';
  $count = 1;
  $fp = fopen('dcu_bambora_payment_reject.csv', 'w');
  $headers = [
    'Log tidspunkt',
    'Transactionsid',
    'Orderid',
    'Subscriptionid',
    'Beløb',
    'Medlemsnummer',
    'Fornavn',
    'Efternavn',
  ];
  fputcsv($fp, $headers);
  foreach ($results as $log_line) {
    $variables = unserialize($log_line->variables);
    $data = json_decode($variables['@data']);
    echo print_r($data, 1);
    $uid = $data->userid;
    $wrong_uid = $data->paramuid;
    if ($uid == 1 || !$paid_user = \Drupal\user\Entity\User::load($uid)) {
      continue;
    }
    $paid_memberno = $paid_user->get('field_memberid')->getString();
    $paid_membername = $paid_user->get('field_first_name')->getString();
    $paid_memberlastname = $paid_user->get('field_last_name')->getString();
    $payment = [
      date('d-m-Y H:i:s', $log_line->timestamp),
      $data->epay_response->txnid,
      $data->epay_response->orderid,
      $data->epay_response->subscriptionid,
      $data->epay_response->amount,
      $paid_memberno,
      $paid_membername,
      $paid_memberlastname,
    ];
    fputcsv($fp, $payment);
    echo "row number : " . $count;
    $count++;
  }
  fclose($fp);
  echo 'dblog done';
}

/**
 * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
 * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
 * @throws \Drupal\Core\Entity\EntityMalformedException
 * @throws \Drupal\Core\Entity\EntityStorageException
 */
function dcu_utility_google_placeid() {
  $apiurl = "https://maps.googleapis.com/maps/api/place/findplacefromtext/json";
  $query_parameters = [
    'input' => '',
    'inputtype' => 'textquery',
    'key' => 'AIzaSyArP7FJnpPdPiNg22J6jCZPCEMVOWPfpa8',
  ];

  $nid = drush_get_option('nid');
  $force = drush_get_option('forceoverwrite');
  ini_set('memory_limit', '1048M');
  ini_set('max_execution_time', 1200);
  $query = \Drupal::entityQuery('node')
    ->condition('type', ['dcu_campsite', 'campsites'], 'in');
  if (!empty($nid)) {
    $query->condition('nid', $nid);
  }
  $result = $query->execute();
  $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($result);
  foreach ($nodes as $node) {
    if (!empty($node->get('field_google_place_id')) && !$force) {
      continue;
    }
    $address = $node->get('field_address')->first();
    $query_parameters['input'] = $node->label() . ' ' . $address->address_line1 . ' ' . $address->locality . ' ' . $address->country_code;
    $search_url = $apiurl . '?' . http_build_query($query_parameters);
    $place_id = dcu_utility_fetch_google_place_api_data($search_url);
    if ($place_id == 'ZERO_RESULTS') {
      // Try with name and country only.
      $query_parameters['input'] = $node->label() . ' ' . $address->locality . ' ' . $address->country_code;
      $place_id = dcu_utility_fetch_google_place_api_data($search_url);
    }
    $node->set('field_google_place_id', $place_id);
    $node->set('field_google_place_id_search_url', $search_url);
    $node->save();
    echo $place_id . ',' . 'https://www.dcu.dk' . $node->toUrl('edit-form')->toString() . ',https://maps.googleapis.com/maps/api/place/details/json?key=AIzaSyArP7FJnpPdPiNg22J6jCZPCEMVOWPfpa8&place_id=' . $place_id . "\n";
  }

}

/**
 * @param $search_url
 * @return string
 */
function dcu_utility_fetch_google_place_api_data($search_url) {
  $client = \Drupal::httpClient();
  $response = $client->get($search_url);
  $code = $response->getStatusCode();
  $result = 'ERROR';
  if ($code == 200) {
    $response_data = json_decode($response->getBody()->getContents());
    $result = $response_data->status;
    if (!empty($response_data->candidates)){
      $result = $response_data->candidates[0]->place_id;
    }
  }
  return $result;

}

/**
 *
 */
function dcu_utility_campaign_monitor_csv() {
  //Get array of countries.
  $countries = \Drupal::service('country_manager')->getList();

  $database = \Drupal::database();
  $query = $database->query("select
    n.nid,
    n.title as name,
    adr.field_address_postal_code as zip,
    adr.field_address_country_code as country_code,
    adv.field_advantage_campsite_value as advantage,
    alias.alias as www,
    from_unixtime(n.changed, '%Y-%m-%d') as changed,
    n.uid,
    u.name,
    u.mail,
    COALESCE(p.field_price_list_year_value,'') as price_year,
	  COALESCE(sd.field_season_period_value,'') as season_start_date
    from
    node_field_data n
    left join node__field_address adr on adr.entity_id = n.nid
    left join node__field_advantage_campsite adv on adv.entity_id = n.nid
    left join path_alias as alias on CONCAT('/node/', n.nid ) = alias.path
    left join users_field_data u on u.uid = n.uid
    left join node__field_price_list_year p on p.entity_id = n.nid
    left join node__field_season_period sd on sd.entity_id = n.nid
    where
    n.type = 'campsites' and
    n.status = 1
    order by n.title asc"
  );
  $result = $query->fetchAll();

  $fh = fopen('campaign-monitor-file.csv', 'w');
  $fields = [
    'Campsiteurl',
    'Land',
    'Campingplads navn',
    'Fordelsplads',
    'Update date',
    'Postnr',
    'Username',
    'Drupal user emal adresse',
    'Price year',
    'Season start date'
  ];
  fputcsv($fh, $fields);

  if (!empty($result)) {
    $i = 1;
    foreach ($result as $campsite) {
      print $i++ . ' country: ' . $countries[$campsite->country_code] . PHP_EOL;
      $csv_data = [
        'https://dcu.dk' . $campsite->www,
        $countries[$campsite->country_code],
        ltrim($campsite->name),
        $campsite->advantage,
        $campsite->changed,
        $campsite->zip,
        $campsite->name,
        $campsite->mail,
        $campsite->price_year,
        $campsite->season_start_date
      ];
      fputcsv($fh, $csv_data);
    }
  }
  fclose($fh);
}

/**
 * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
 * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
 * @throws \Drupal\Core\Entity\EntityStorageException
 */
function dcu_utility_set_automatic_url_alias() {
  ini_set('memory_limit', '1048M');
  ini_set('max_execution_time', 600);

  $entity = \Drupal::entityTypeManager()->getStorage('node');
  $database = \Drupal::database();
  $query = $database->query("select nid,type from node where type='article'"
  );
  $result = $query->fetchAll();

  $i = 1;
  foreach ($result as $content) {
    print $i++ . ' nid ' . $content->nid . ' og type ' . $content->type . PHP_EOL;
    $node = $entity->load($content->nid);
    $node->set('path', ['pathauto' => TRUE]);
    $node->save();
  }
}

/**
 * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
 * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
 * @throws \Drupal\Core\Entity\EntityStorageException
 */
function dcu_utility_set_country_region_campsites() {
  ini_set('memory_limit', '1048M');
  ini_set('max_execution_time', 600);

  $entity = \Drupal::entityTypeManager()->getStorage('node');
  $database = \Drupal::database();
  $query = $database->query("select n.nid from node n where n.type = 'campsites'");
  $result = $query->fetchAll();

  if (!empty($result)) {
    $i = 1;
    foreach ($result as $campsite) {
      $node = $entity->load($campsite->nid);
      $saved_tids = $node->get('field_country_region')->getString();
      $saved_tids_array = explode(', ', $saved_tids);
      $tids = [];
      foreach ($saved_tids_array as $tid) {
        $tids[] = $tid;
        $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($tid);
        if (!empty($term->parent->target_id)) {
          $tids[] = $term->parent->target_id;

          $term2 = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($tids[1]);
          if (!empty($term2->parent->target_id)) {
            $tids[] = $term2->parent->target_id;
          }
        }
      }
      print $i++ . ' nid ' . $campsite->nid . PHP_EOL;
      print_r($tids) . PHP_EOL;
      $tids = array_unique($tids);
      print_r($tids) . PHP_EOL;

      $node->set('field_country_region', $tids);
      $node->save();
    }
  }
}


/**
 * Loop over all emails in nav to check if it is valid.
 */
function dcu_utility_email_check() {
  ini_set('memory_limit', '1048M');
  ini_set('max_execution_time', 1200);
  //All active Nav members.
  $members = \Drupal::service('dcu_navision.client')->getActiveMembers();
  $i = 1;
  $j = 1;
  $l = 1;
  $m = 1;
  $k = 1;
  $email_missing = [];
  $email_spaces = [];
  $email_uppercases = [];
  $email_invalid = [];
  foreach ($members as $member) {
    $nav_get_member = \Drupal::service('dcu_navision.client')->getMemberData($member->memberno);
//    if (empty($nav_get_member->GetMember->email)) {
//      print $i++ . ' Email mangler - memberno ' . $member->memberno . PHP_EOL;
//      $email_missing[] = $member->memberno;
//    }
    if (!empty($nav_get_member->email)) {
//      if (strpos($nav_get_member->GetMember->email, ' ') !== false) {
//        print $j++ . ' Email har spaces...........memberno ' . $member->memberno . ' email ' . $nav_get_member->GetMember->email . PHP_EOL;
//        $email_spaces[] = $member->memberno . ' - ' . $nav_get_member->GetMember->email;
//      }
      if(preg_match("/[A-Z]/",$nav_get_member->email)) {
        print $l++ . ' Email har uppercases...........memberno ' . $member->memberno . ' email ' . $nav_get_member->email . PHP_EOL;
        $email_uppercases[] = $member->memberno . ' - ' . $nav_get_member->email;
      }
//      if (!\Drupal::service('email.validator')->isValid($nav_get_member->GetMember->email)) {
//        print $m++ . ' Email er invalid...........memberno ' . $member->memberno . ' email ' . $nav_get_member->GetMember->email . PHP_EOL;
//        $email_invalid[] = $member->memberno . ' - ' . $nav_get_member->GetMember->email;
//      }
    }

    print $k++ . '...' . PHP_EOL;
  }

  //print_r($email_missing);
  //print_r($email_spaces);
  print_r($email_uppercases);
  //print_r($email_invalid);

}

/**
 * Lowercase all emails - both drupal and nav.
 */
function dcu_utility_email_lower() {
  ini_set('memory_limit', '1048M');
  ini_set('max_execution_time', 600);
  //$entity = \Drupal::entityTypeManager()->getStorage('user');
  $database = \Drupal::database();
  $query = $database->query("select u.uid,
    u.mail,
    um.field_memberid_value as memberid
    from
    users_field_data u
    left join user__field_memberid um on um.entity_id = u.uid
    where
    u.mail REGEXP BINARY '[A-Z]'
    and um.field_memberid_value is not null
    order by u.uid asc limit 500");
  $result = $query->fetchAll();

  $i = 1;
  foreach ($result as $member) {
    $user = \Drupal\user\Entity\User::load($member->uid);
    $user->setEmail(strtolower(str_replace(' ', '', $member->mail)));
    $user->save();

    if (!$navresult = dcu_member_send_userdata_to_nav($member->uid)) {
      //print 'DET GIK GALT.....' . PHP_EOL;
      print $i++ . ' - MemberID ' . $member->memberid . ' - mail: ' . $member->mail . PHP_EOL;
    }
  }
}

/**
 * Add redirects for all subpages.
 */
function dcu_utility_subpage_redirects() {
  ini_set('memory_limit', '1048M');
  ini_set('max_execution_time', 600);

  /*
  74787	København Absalon	37601	DCU-Camping København - Absalon
  74788	Ebeltoft - Mols	37613	DCU-Camping Ebeltoft - Mols
  74790	Hesselhus	37608	DCU-Camping Hesselhus
  74792	Hornbæk	37609	DCU-Camping Hornbæk
  74793	Odense	37615	DCU-Camping Odense
  74797	Rørvig Strand	37616	DCU-Camping Rørvig Strand
  74800	Rågeleje Strand	37617	DCU-Camping Rågeleje
  74805	Tranum	37618	DCU-Camping Tranum
  74814	Ålbæk Strand	37622	DCU-Camping Ålbæk Strand
  74828	Ejsing Strand	37605	DCU-Camping Ejsing Strand
  74830	Flyvesandet Strand	37606	DCU-Camping Flyvesandet Strand
  74845	Gjerrild Strand	37607	DCU-Camping Gjerrild Strand
  74849	Holstebro Sø	37612	DCU-Camping Holstebro Sø
  74850	Kollund	37610	DCU-Camping Kollund
  74852	Kulhuse	37611	DCU-Camping Kulhuse
  74853	Nærum	37614	DCU-Camping Nærum
  74855	Rønne Strand - Galløkken	37623	DCU-Camping Rønne Strand - Galløkken
  74856	Viborg Sø	37620	DCU-Camping Viborg Sø
  74857	Åbyskov Strand	37621	DCU-Camping Åbyskov Strand
  74864	Aarhus - Blommehaven	37604	DCU-Camping Aarhus - Blommehaven
  */

  $old_to_new = array(
    '37601' => '74787',
    '37613' => '74788',
    '37608' => '74790',
    '37609' => '74792',
    '37615' => '74793',
    '37616' => '74797',
    '37617' => '74800',
    '37618' => '74805',
    '37622' => '74814',
    '37605' => '74828',
    '37606' => '74830',
    '37607' => '74845',
    '37612' => '74849',
    '37610' => '74850',
    '37611' => '74852',
    '37614' => '74853',
    '37623' => '74855',
    '37620' => '74856',
    '37621' => '74857',
    '37604' => '74864',
  );


  /*
  Find each subpage and locate parent nid. Use old parent to find new nid
  and create a redirect.
  */

  $entity = \Drupal::entityTypeManager()->getStorage('node');
  $database = \Drupal::database();
  $query = $database->query("select n.nid from node n where n.type = 'dcu_campsites_subpage'");
  $result = $query->fetchAll();

  if (!empty($result)) {

    $j = 1;
    $i = 1;
    foreach($result as $subpage) {
      $j++;
      $subpage_nid = $subpage->nid;

      $query2 = $database->query("select
       distinct n.title,
        ml.id,
        mt.mlid,
        mt.p1,
        mt2.route_param_key,
        replace(mt2.route_param_key,'node=', '') as parent_nid
        from
        menu_link_content_data ml
        left join menu_tree mt on mt.route_param_key = 'node=" . $subpage_nid . "' and mt.menu_name = 'dcu-campsites-all'
        left join menu_tree mt2 on mt2.mlid = mt.p1
                left join node_field_data n on n.nid = " . $subpage_nid . "
        where
        ml.link__uri = 'entity:node/" . $subpage_nid . "'         and n.langcode = 'en' "
      );

      //$i = 1;
      $result2 = $query2->fetchAll();
      if (empty($result2)) {
        //print 'Vi har ingen her ' . $subpage_nid . '..................................' . PHP_EOL;
      }
      foreach($result2 as $parent_node) {
        $alias = \Drupal::service('path_alias.manager')->getAliasByPath('/node/'. $subpage_nid, 'en');
        if ($alias) {
          print 'RedirectMatch 301 ' . $alias . ' ' . '/node/' . $old_to_new[$parent_node->parent_nid] . PHP_EOL;
        }
        //print $i++ . ' - child node ' . $subpage_nid . ' alias: ' . $alias . ' - og parent node ' . $parent_node->parent_nid . PHP_EOL;
        //print 'RedirectMatch 301 ' . $alias . ' ' . '/node/' . $old_to_new[$parent_node->parent_nid] . PHP_EOL;
        //print 'Mapping ' . $parent_node->parent_nid . ' mapper til ' . $old_to_new[$parent_node->parent_nid] . PHP_EOL;
      }
    }
    print 'Total antal subpages ' . $j . PHP_EOL;
  }
}



/**
 *
 */
function dcu_utility_nav_drupal_sync() {
  ini_set('memory_limit', '2048M');
  ini_set('max_execution_time', 600);

  $master_file = fopen('dcumembersoriginal.csv', 'r');
  $result_file = fopen('dcumembersalltest-notindrupal.csv', 'w');
  $result_file_bad = fopen('dcumembersalltest-bademails.csv', 'w');

  $i = 0;
  while (($data = fgetcsv($master_file, 0, ';', '"')) !== FALSE) {
    $memberno = $data[0];
    $email = $data[6];
    $first_name = $data[1];
    $database = \Drupal::database();
    $row = $database->query("SELECT mail FROM users_field_data WHERE mail = :value", [':value' => $email])->fetchObject();
    if (!$row->mail) {
      if (!empty($email)) {
        //Lets check if email is valid.
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
          //Invalid emailaddress
          print 'Hov - dum mail ' . $email . PHP_EOL;
          fputcsv($result_file_bad, array($email));
        }
        else {
          $cvs_data = [$memberno, $email];
          fputcsv($result_file, $cvs_data);
          //Create user in Drupal
          $test = 1;
          if ($test == 1) {
            //dcu_utility_nav_drupal_sync_create_user($memberno, $email);
            $test++;
          }
        }
      }
    }
    print $i++ . ' jobber....' . $email . PHP_EOL;
  }
  fclose($result_file);
}


use \Drupal\user\Entity\User;
function dcu_utility_nav_drupal_sync_create_user($memberno, $email) {
  $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
  // Create user object.
  $member = User::create();
  $member->setPassword(base64_encode(openssl_random_pseudo_bytes(10)));
  $member->enforceIsNew();
  $member->setEmail($email);
  $member->setUsername($memberno);
  $member->set('init', $email);
  $member->set('langcode', $language);
  $member->addRole('icamp');
  $member->activate();
  try {
    $member->save();
  } catch (Exception $e) {
    \Drupal::logger('dcu_member')->error('Failed to create new drupal user. Failed with the following message: @error', ['@error' => $e->getMessage()]);
    return FALSE;
  }
//  return $member;
}
