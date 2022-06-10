<?php

namespace Drupal\dcu_member\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Class UserProfileController.
 */
class UserProfileController extends ControllerBase {

  /**
   * Content.
   *
   * @param null $user
   *
   */
  public function content($userId = NULL) {
    $currentUserId = \Drupal::currentUser()->id();
    $currentUser = User::load($currentUserId);
    $user = User::load($currentUserId);

    if (!empty($userId)) {
      if ($user->hasRole('administrator') || $user->hasRole('member_service')) {
        $user = User::load($userId);
        if (!$user) {
          \Drupal::messenger()->addMessage($this->t('There was an error loading user profile'), 'error');
          return  [];
        }
      }
      elseif ($user->id() != $userId) {
        throw new AccessDeniedHttpException();
      }
    }
    if ($redirect = dcu_admin_check_navision_redirect()) {
      return $redirect;
    }
    if (dcu_member_user_is_not_nav_member($user)) {
      \Drupal::messenger()->addMessage($this->t('You do not have a memberprofile on DCU'), 'error');
      return new RedirectResponse(Url::fromRoute('user.page')->toString());
    }
    if (!dcu_member_get_account_memberid($user)) {
      \Drupal::messenger()->addMessage($this->t('There is no DCU membership attached to this account'), 'status');
      return new RedirectResponse(Url::fromRoute('<front>')->toString());
    }

    $navdata = dcu_member_fetch_and_sync_drupal_user_from_nav($user);
    if (empty($navdata)) {
      \Drupal::messenger()->addMessage($this->t('There was an error getting your profile data'), 'error');
      return  ['#theme' => 'dcu_member_view_profile', '#profile' => []];
    }
    $link_options = ['attributes' => ['class' => ['btn', 'btn-primary', 'no-underline',],],];

    $url = Url::fromRoute('dcu_member.profile_logindata_form', ['userId' => $user->id()], $link_options);
    $link = Link::fromTextAndUrl($this->t('Edit'), $url);
    $editlogin = $link->toRenderable();
    $login = [
      'memberno' => $navdata->memberno,
      'mail' => $navdata->email,
      'links' => [
        $editlogin,
      ]
    ];

    $url = Url::fromRoute('dcu_member.profile_form', ['userId' => $user->id()], $link_options);
    $link = Link::fromTextAndUrl($this->t('Edit'), $url);
    $editprofile = $link->toRenderable();

    //Just to be safe.
    if ($user->preferred_langcode->value) {
      $preferred_language = t(\Drupal::languageManager()->getLanguages()[$user->preferred_langcode->value]->getName());
    }
    else {
      $preferred_language = \Drupal::languageManager()->getLanguages()['en']->getName();
    }
    $profile = [
      'memberno' => $navdata->memberno,
      'mail' => $navdata->email,
      'firstname' => $navdata->firstname,
      'lastname' => $navdata->lastname,
      'country' => $navdata->country,
      'birthday' => date("d-m-Y", strtotime($navdata->birthday)),
      'cellphone' => isset($navdata->phoneno) ? $navdata->phoneno : '',
      'street' => $navdata->address,
      'zip' => $navdata->postalcode,
      'city' => $navdata->city,
      'address' => $navdata->address . '<br/>' .
        $navdata->postalcode . ' ' . $navdata->city . '<br/>' .
        $navdata->country,
      'preferred_language' => $preferred_language,
      'links' => [
        $editprofile,
      ]
    ];

    $url = Url::fromRoute('dcu_member.profile_membership_form', ['userId' => $user->id()], $link_options);
    $link = Link::fromTextAndUrl($this->t('Edit'), $url);
    $editmembership = $link->toRenderable();
    $unsubscribed = [];
    if (!empty($navdata->unsubscribeddate) && $navdata->memberstatus == 'AKTIV') {
      $unsubscribed['date'] =  date("d-m-Y", strtotime($navdata->unsubscribeddate));
      $unsubscribed['enddate'] =  date("d-m-Y", strtotime($navdata->subsenddate));
      $unsubscribed['message'] =  t('You have cancelled your membership on @date your membership runs to @enddate',
        [
          '@date' => $unsubscribed['date'],
          '@enddate' => $unsubscribed['enddate'],
        ]);
      $reactivateurl = Url::fromRoute('dcu_member.profile_reactivate_membership', ['userId' => $user->id()], $link_options);
      $reactivatelink = Link::fromTextAndUrl($this->t('Reactivate membership'), $reactivateurl);
      $unsubscribed['resubscribelink'] = $reactivatelink->toRenderable();
    }
    $showbalance = (empty($navdata->bsactive) && $navdata->balance > 0);
    $membertype = ($navdata->membertype == 'GRATIS') ? 'INAKTIV' : $navdata->membertype;
    $membership = [
      'membersince' => date("d-m-Y", strtotime($navdata->membersince)),
      'membertype' => $membertype,
      'balance' => $navdata->balance,
      'showbalance' => $showbalance,
      'lastpaymentdate' => !empty($navdata->lastpaymentdate) ?  date("d-m-Y", strtotime($navdata->lastpaymentdate)) : '',
      'bsactive' => !empty($navdata->bsactive),
      'magazineto' => empty($navdata->magazineaddress) ? t("Sendes til profil adresse") : t("Sendes til @magaddr", ['@magaddr' => $navdata->magazineaddress]),
      'magazineletter' => !empty($navdata->magazineletter),
      'showmag' => in_array($navdata->membertype, ['PERSON', 'FAMILIE', 'PENSIONIST']),
      'retirement_valid_message' => ($navdata->awaitingapproval && $navdata->membertype == 'PENSIONIST'),
      'unsubscribed' => $unsubscribed,
      'links' => [
        $editmembership,
      ]
    ];

    $url = Url::fromRoute('dcu_member.profile_relatives_form', ['userId' => $user->id()], $link_options);
    $link = Link::fromTextAndUrl($this->t('Edit'), $url);
    $editrelatives = $link->toRenderable();
    $children = [];
    $spouse = FALSE;
    if (!empty($navdata->relatives)) {
      foreach ($navdata->relatives as $relative) {
        if ($relative->reltype == 'Spouse') {
          $spouse = $relative;
        }
        if ($relative->reltype == 'Child') {
          $children[] = $relative;
        }
      }
    }
    $relatives = [
      'spouse' =>  $spouse,
      'children' => $children,
      'links' => [
        $editrelatives,
      ]
    ];

    //All notifications.
    $notifications = [];

    //Notify settings.
    $user_settings = \Drupal::service('comment_notify.user_settings');
    $notify_settings = $user->id() && $user_settings->getSettings($user->id()) ? $user_settings->getSettings($user->id()) : $user_settings->getDefaultSettings();
    $notification_settings_forum_comments = isset($notify_settings['entity_notify']) && $notify_settings['entity_notify'] == 1 ? t('Yes') : t('No');
    $notification_settings_comments = isset($notify_settings['comment_notify']) && ($notify_settings['comment_notify'] == 1 || $notify_settings['comment_notify'] == 2)? t('Yes') : t('No');
    $url = Url::fromRoute('dcu_member.profile_notifications_form', ['userId' => $user->id()], $link_options);
    $link = Link::fromTextAndUrl($this->t('Edit'), $url);
    $editnotifications = $link->toRenderable();
    $mailchimp_status = dcu_member_mailchimp_status($navdata->email);
    $notifications = [
      'comments1' =>  $notification_settings_forum_comments,
      'comments2' =>  $notification_settings_comments,
      'newsletter' => empty($mailchimp_status) ? t('No') : t('Yes'),
      'contact_consent' => empty($navdata->consent) ? t('No') : t('Yes'),
      'links' => [
        $editnotifications,
      ]
    ];

    //Memberservice.
    $url = Url::fromRoute('dcu_member.profile_memberservice_fields_form', ['userId' => $user->id()], $link_options);
    $link = Link::fromTextAndUrl($this->t('Edit'), $url);
    $editmemberservice = $link->toRenderable();
    $memberservice = [];
    if ($this->accessProfileMemberservice($currentUser)->isAllowed()) {
      $vip = false;
      if (isset($navdata->chargetype) && $navdata->chargetype == 'Gratist') {
        $vip = t('VIP member');
      }

      $magazineletter = t('No');
      if (isset($navdata->magazineletter) && $navdata->magazineletter == true ) {
        if ($navdata->country != 'DK') {
          /* @TODO: Is this needed? */
          $magazineletter = t('Yes - but not allowed. Address is not in Denmark');
        }
        else {
          $magazineletter = t('Yes');
        }
      }

      $confirmed_dk_citizenship = false;
      if ($user->field_confirmed_dk_citizenship->value) {
        $confirmed_dk_citizenship = t('Citizenship is confirmed.');
      }

      $comments = '';
      if (isset($navdata->comment)) {
        $comments = $navdata->comment;
      }
      $memberservice = [
        'vip' => $vip,
        'magazineletter' => $magazineletter,
        'citizenship_confirmed' => $confirmed_dk_citizenship,
        'comments' => $comments,
        'links' => [
          $editmemberservice,
        ]
      ];
    }

    //Values for view-profile.html.twig
    $data = [
      'login' => $login,
      'profile' => $profile,
      'membership' => $membership,
      'relatives' => $relatives,
      'notifications' => $notifications,
      'fix_mail' => !empty(preg_match("/nomail/i", $navdata->email)),
      'memberservice' => $memberservice,
    ];

    /*
    CODE FROM DCU MEMBERPORTAL MODULE
    $charge_type = '';
    if ((in_array('Member service', $user_full->roles) || in_array('Site administrator', $user_full->roles)) && !empty($nav_member_data)) {
      $charge_type = $nav_member_data->chargetype;
    }
    */

    $build = [
      '#theme' => 'dcu_member_view_profile',
      '#profile' => $data,
    ];
    return $build;
  }

  /**
   * Route callback to reactivate cancelled but active member.
   */
  public function reactivateMembership($userId = NULL) {
    if (empty($userId)) {
      $userId = \Drupal::currentUser()->id();
    }
    $user = User::load($userId);
    $memberId = $user->get('field_memberid')->getString();
    dcu_member_profile_reactivate_membership($memberId);
    $url = Url::fromRoute('dcu_member.user_profile', ['userId' => $userId]);
    return new RedirectResponse($url->toString());
  }

  /**
   * Redirect siteowner to a list or specific campsite.
   */
  public function siteownerSite() {
    $currentUserId = \Drupal::currentUser()->id();
    $currentUser = User::load($currentUserId);
    if (!empty($currentUserId)) {
      if ($currentUser->hasRole('administrator') || $currentUser->hasRole('other_campsite_owner')) {
        //Get siteowners nid and redirect to the site or overview page.
        dcu_utility_campsite_owner_redirects($currentUserId);
      }
    }
    $response = new RedirectResponse('/');//Send to frontpage.
    $response->send();
    return;
  }

  /**
   * Redirect agent to list of campsites.
   * Access is set in dcu_utility -> dcu_utility_node_access.
   * Check if country on agent match country on campsite.
   */
  public function agentSite() {
    $currentUserId = \Drupal::currentUser()->id();
    $currentUser = User::load($currentUserId);
    if (!empty($currentUserId)) {
      if ($currentUser->hasRole('administrator') || $currentUser->hasRole('agent')) {
        $redirect_path = '/agent-overviews';
        $response = new RedirectResponse($redirect_path);
        $response->send();
      }
    }
    $response = new RedirectResponse('/');//Send to frontpage.
    $response->send();
    return;
  }

  /**
   * Checks access for user profile pages
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   * @param $userId
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function accessProfile(AccountInterface $account, $userId = NULL) {
    if ($account->isAnonymous()) {
      return AccessResult::forbidden();
    }
    if ($account->id() == $userId) {
      // User accessing own profile.
      return AccessResult::allowed();
    }
    if (in_array('administrator', $account->getRoles()) || in_array('member_service', $account->getRoles())) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }

  /**
   * Checks access for user profile pages only for memberservice role.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   * @param $userId
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function accessProfileMemberservice(AccountInterface $account, $userId = NULL) {
    if (in_array('administrator', $account->getRoles()) || in_array('member_service', $account->getRoles())) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }
}
