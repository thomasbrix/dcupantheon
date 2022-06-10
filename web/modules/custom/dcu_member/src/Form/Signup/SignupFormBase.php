<?php

/**
 * @file
 * Contains \Drupal\dcu_member\Form\Signup\SignupFormBase.
 */

namespace Drupal\dcu_member\Form\Signup;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\SessionManagerInterface;
use Drupal\Core\Url;
use Drupal\dcu_navision\Client\NavisionRestClient;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;


abstract class SignupFormBase extends FormBase {

  /**
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * @var \Drupal\Core\Session\SessionManagerInterface
   */
  private $sessionManager;

  /**
   * @var \Drupal\Core\Session\AccountInterface
   */
  private $currentUser;

  /**
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected $store;

  protected $signUpFields;

  /**
   * @var NavisionRestClient $navisionClient
   */

  protected $navisionClient;

  /**
   * Constructs a \Drupal\dcu_member\Form\Signup\SignupFormBase.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   * @param \Drupal\Core\Session\SessionManagerInterface $session_manager
   * @param \Drupal\Core\Session\AccountInterface $current_user
   * @param \Drupal\dcu_navision\Client\NavisionRestClient $navisionClient
   */
  public function __construct(PrivateTempStoreFactory $temp_store_factory, SessionManagerInterface $session_manager, AccountInterface $current_user, NavisionRestClient $navisionClient) {
    $this->tempStoreFactory = $temp_store_factory;
    $this->sessionManager = $session_manager;
    $this->currentUser = $current_user;
    $this->store = $this->tempStoreFactory->get('signup_data');
    $this->signUpFields = [
      'member_type', 'email', 'password', 'consent', 'country', 'campaigncode', 'firstname',
      'lastname', 'mobile', 'birthdate', 'street', 'zipcode',
      'city', 'newsletter', 'contact_consent',
    ];
    $this->default_member_type = 'enlig';
    $this->navisionClient = $navisionClient;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('tempstore.private'),
      $container->get('session_manager'),
      $container->get('current_user'),
      $container->get('dcu_navision.client')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Start a manual session for anonymous users, though not on ajax submits.
    if (empty($form_state->getTriggeringElement())) {
      if (!empty($this->currentUser) && $this->currentUser->isAnonymous() && !isset($_SESSION['signup_form_holds_session'])) {
        $_SESSION['signup_form_holds_session'] = TRUE;
        $this->sessionManager->start();
      }
    }
    $form = array();
    $this->setMemberType();
    $form['#theme'] = $this->getFormId();
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#button_type' => 'primary',
      '#weight' => 10,
      '#attributes' => [
        'class' => ['disable-on-click'],
      ],
    );

    $form['summaryprice'] = [
      '#type' => 'table',
      '#header' => [],
      '#rows' => $this->getpriceTableRows(),
      '#attributes' => [
        'class' => [
          'table',
        ],
      ]
    ];
    //$form['#attached']['library'][] = 'core/drupal.form'; WE HAVE ADDED THIS TO DCU.LIBRARIES.YML - NOT NEEDED HERE?
    return $form;
  }

  protected function setMemberType() {
    $arg_type = \Drupal::request()->get('member_type');
    $stored_type = $this->store->get('member_type');
    if (empty($arg_type)) {
      if (!empty($stored_type)) {
        return;
      }
      // Called with no arg init to default.
      $arg_type = $this->default_member_type;
    }
    $member_type = dcu_member_parse_drupal_nav_membertype($arg_type, 'nav');
    if (empty($member_type)) {
      // Called with invalid type. Set default type person.
      $member_type = dcu_member_parse_drupal_nav_membertype($this->default_member_type, 'nav');
    }
    if ($stored_type == $member_type) {
      return;
    }
    // Changed membertype.
    $this->store->set('member_type', $member_type);
    $this->setPrice();
  }

  protected function setPrice() {
    $member_type = $this->store->get('member_type');
    $campaign_code = $this->store->get('campaigncode');
    $price = \Drupal::service('dcu_navision.client')->getMembershipPrice($member_type, $campaign_code);
    $this->store->set('price', $price);
  }

  public function getFormHeader() {
    $membership_name = dcu_member_parse_drupal_nav_membertype($this->store->get('member_type'), 'name');
    if (!$membership_name) {
      return FALSE;
    }
    if ($membership_name == 'deal') {
      return $this->t('DCU Deal');
    }
    return $this->t('DCU ' . $membership_name . ' membership');
  }

  protected function getpriceTableRows() {
    if (empty($member_type = $this->store->get('member_type'))) {
      return t("Membertype missing");
    }

    $this->setPrice();
    $price = $this->store->get('price');

    $title = dcu_member_parse_drupal_nav_membertype($member_type, 'name');
    $product['title'] = $this->t($title);
    $product['price'] = $price . ' dkk';
    $product['period'] = $this->t('January - December');
    if ($member_type == 'gratis') {
      $product['period'] = ' - ';
    }
    $rows = [
      [t('Membership'), ['data' => $product['title'] , 'class' => 'table-value']],
      [t('Period'), ['data' => $product['period'], 'class' => 'table-value']],
      [t('Price'), ['data' => $product['price'], 'class' => 'table-value']],
    ];
    if (!empty($this->store->get('campaigncode'))) {
      $rows[] = [$this->t('Discount is deducted.'), ['data' => '', 'class' => 'table-value']];
    }
    return $rows;
  }

  /**
   * Last step submitted successfully.
   * Create user and redirects to payment page on success.
   */
  protected function saveData() {
    $user_data = $this->getStore();

    $account = dcu_member_create_dcu_member($user_data);
    $password = $user_data['password'];
    // Unset data password to prevent from sending it to errorlogs .
    unset($user_data['password']);
    if (!$account) {
      // TODO: How to treat user creation fail ?
      \Drupal::logger('dcu_member')->error('Function user_save returned false when trying to create user with values: @data', ['@data' => print_r($user_data, TRUE)]);
      \Drupal::messenger()->addMessage($this->t('New membership could not be created. Please try again or contact dcu memberservice'), 'error');
      return FALSE;
    }
    $campaigncode = $user_data["campaigncode"] ? $user_data["campaigncode"] : '';
    if (!$nav_member_id = $this->navisionClient->createMember($account, $campaigncode)) {
      \Drupal::logger('nav_create_member_error')->notice('@data', ['@data' => Json::encode($account->id())]);
      return FALSE;
    };
    try {
      $existing = \Drupal::entityQuery('user')
        ->condition('field_memberid', $nav_member_id)
        ->execute();
      if (!empty($existing)) {
        // Check if memberid already exists. This should not happen on production
        // But will happen when test navision is out of sync with dev environments
        $nav_member_id = $nav_member_id . '-invalid';
      }
      $account->set('field_memberid', $nav_member_id);
      $account->set('name', $nav_member_id);
      $account->activate();
      $account->save();
    }
    catch (Exception $e) {
      // TODO: How to treat user update fail ?
      \Drupal::logger('dcu_member')->error('Unable to set username and memberid for userid: @user', ['@user' => $account->id()]);
      \Drupal::messenger()->addMessage($this->t('There was an unexpected error creating membership. Please try again or contact dcu memberservice'), 'error');
      return FALSE;
    }
    if (!empty($user_data['newsletter'])) {
      dcu_member_mailchimp_subscribe($account);
    }

    $mail_params = [
      'to' => $account->getEmail(),
      'subject' => t('You have created a user account on dcu.dk'),
      'name' => $user_data['firstname'],
      'member_no' => $nav_member_id,
      'language' => \Drupal::languageManager()->getCurrentLanguage()->getId(),
    ];
    dcu_member_send_mail('welcome', $mail_params);

    $uid = \Drupal::service('user.auth')->authenticate($account->get('name')->getString(), $password);
    $user = User::load($uid);
    user_login_finalize($user);
    $this->deleteStore();
    \Drupal::logger('signup_member_created')->notice('@data', ['@data' => Json::encode(['uid' => $account->id(), 'nav_memberno' => $nav_member_id])]);
    $response = new RedirectResponse(Url::fromRoute('dcu_member.payment')->toString());
    $response->send();
    return TRUE;
  }

  /**
   * Helper method that removes all the keys from the store collection used for
   * the signup form.
   */
  protected function deleteStore() {
    foreach ($this->signUpFields as $key) {
      $this->store->delete($key);
    }
  }

  public function getStore() {
    $values = [];
    foreach ($this->signUpFields as $key) {
      $values[$key] = $this->store->get($key);
    }
    return $values;
  }
}
