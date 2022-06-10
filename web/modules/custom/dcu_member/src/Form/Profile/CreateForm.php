<?php

namespace Drupal\dcu_member\Form\Profile;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\dcu_navision\Client\NavisionRestClient;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class CreateForm.
 */
class CreateForm extends FormBase {

  /**
   * @var NavisionRestClient $navisionClient
   */
  protected $navisionClient;

  /**
   * @param \Drupal\dcu_navision\Client $navisionClient
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
    return 'profile_create_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $userId = NULL) {
    if ($redirect = dcu_admin_check_navision_redirect ()) {
      return $redirect;
    }
    $form['#theme'] = $this->getFormId();
    $form['header'] = [
      '#markup' => '<h1>' . $this->t('Create new DCU member') . '</h1>',
    ];

    //Info to memberservice.
    $form['info'] = [
      '#markup' => '<div class="bg-green pb-3">' . t('If the member does not live in Denmark and wants a membership other than DEAL - then we must see documentation of Danish citizenship.') . '</div>'
    ];

    //This field is only in Drupal. Not part of Nav.
    $form['confirmed_dk_citizenship'] = [
      '#title' => t('Confirmed danish citizenship'),
      '#type' => 'checkbox',
      '#description' => t('Check here - if documentation of Danish citizenship has been seen.'),
      '#default_value' => !empty($user->field_confirmed_dk_citizenship->value) ? 1 : 0,
    ];

    //Membertypes.
    $membershipTerms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('membershiptypes',  0, NULL, TRUE);
    foreach ($membershipTerms as $term) {
      $options[$term->id()] = $term->getName();
    }
    $form['membershiptype'] = [
      '#title' => t('Abonnement'),
      '#type' => 'select',
      '#required' => TRUE,
      '#options' => $options,
    ];

    $form['firstname'] = array(
      '#title' => t('Firstname'),
      '#type' => 'textfield',
      '#attributes' => ['placeholder' => t('Firstname')],
      '#required' => TRUE,
    );
    $form['lastname'] = [
      '#title' => t('Lastname'),
      '#type' => 'textfield',
      '#attributes' => ['placeholder' => t('Lastname')],
      '#required' => TRUE,
    ];
    $form['street'] = [
      '#title' => t('Address'),
      '#type' => 'textfield',
      '#attributes' => ['placeholder' => t('Address')],
      '#required' => TRUE,
    ];
    $form['zipcode'] = [
      '#title' => t('Zipcode'),
      '#type' => 'textfield',
      '#attributes' => ['placeholder' => t('Zipcode')],
      '#required' => TRUE,
    ];
    $form['city'] = [
      '#title' => t('City'),
      '#type' => 'textfield',
      '#attributes' => ['placeholder' => t('City')],
      '#required' => TRUE,
    ];

    $countries = \Drupal::service('country_manager')->getList();
    $form['country'] = [
      '#title' => t('Country'),
      '#type'          => 'select',
      '#required'      => TRUE,
      '#options'       => $countries,
    ];
    $form['email'] = [
      '#type' => 'textfield',
      '#title' => t('Email'),
      '#attributes' => ['placeholder' => t('Email')],
      '#required' => TRUE,
    ];
    $form['password'] = [
      '#title' => t('Desired password'),
      '#type' => 'password',
      '#size' => 25,
      '#attributes' => ['placeholder' => t('Desired password')],
    ];
    $form['mobile'] = [
      '#title' => $this->t('Phonenumber'),
      '#type'  => 'tel',
      '#attributes' => ['placeholder' => $this->t('Phonenumber')],
      '#required' => TRUE,
    ];
    $form['birthdate'] = [
      '#title' => $this->t('Birthdate'),
      '#type'  => 'date',
      '#format' => 'd/m/Y',
      '#date_date_format' => 'd/m/Y',
      '#required' => TRUE,
    ];
    $form['campaigncode'] = [
      '#title' => t('Campaigncode'),
      '#type'     => 'textfield',
      '#attributes' => ['placeholder' => t('Campaigncode')],
    ];

    $preferred_langcode_options = dcu_member_language_options();
    $form['preferred_langcode'] = [
      '#title' => t('Preferred langcode'),
      '#description' => t("This account's preferred language for emails and site presentation."),
      '#type'          => 'select',
      '#required'      => TRUE,
      '#default_value' => 'da',
      '#options'       => $preferred_langcode_options,
    ];

    $form['newsletter'] = [
      '#title' => t('Subscribe to DCU newsletter'),
      '#type' => 'checkbox',
    ];
    $form['contact_consent'] = [
      '#title' => t('Ja tak'),
      '#description' => t('I would like to receive information and hear about relevant benefits and offers from the Danish Camping Union. I can unsubscribe at any time. <a href="/samtykke" target="_blank">Read the terms of consent here</a>'),
      '#type' => 'checkbox',
    ];
    $form['receive_magazine'] = [
      '#title' => t('Modtage magasinet Camping-fritid'),
      '#type' => 'checkbox',
      '#description' => t('Only for members in Denmark'),
      '#default_value' => 0
    ];
    $form['comments'] = array(
      '#title' => t('Comments'),
      '#type' => 'textarea',
      '#attributes' => ['placeholder' => t('Comments')],
      '#required' => FALSE,
      '#description' => t('This information is only shown for Member service. Its hidden for the actual user.'),
    );
    $form['next'] = [
      '#type' => 'submit',
      '#value' => t('Create'),
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
    if (empty($form_state->getValue('email'))) {
      $form_state->setErrorByName('email', $this->t('You must enter an E-mail address.', ['@val' => $form_state->getValue('email')]));
    }
    else {
      //Further email check.
      $email = strtolower(str_replace(' ', '', $form_state->getValue('email')));
      $form_state->setValue('email', $email);

      if (!\Drupal::service('email.validator')->isValid($email)) {
        $form_state->setErrorByName('email', $this->t('@val is not a valid E-mail address.', ['@val' => $email]));
      }
      else {
        if ($this->navisionClient->emailExist($email)) {
          $form_state->setErrorByName('email', $this->t('The E-mail address @val already exists at dcu. <a href="/user">Login with E-mail and password here</a>.', ['@val' => $email]));
        }
        else {
          if (user_load_by_mail($email)) {
            $form_state->setErrorByName('email', $this->t('The E-mail address @val already exists at dcu. <a href="/user">Login with E-mail and password here</a>.', ['@val' => $email]));
          }
        }
      }
    }
    if (!empty($code = $form_state->getValue('campaigncode'))) {
      if(!$this->navisionClient->validateCampaignCode($code)) {
        $form_state->setErrorByName('campaigncode', $this->t('Cuponcode is invalid : @code', ['@code' => $code]));
      }
    }

    //Only allowed to receive magazine in Denmark.
    if ($form_state->getValue("receive_magazine") == 1 && $form_state->getValue("country") != 'DK') {
      $form_state->setErrorByName('receive_magazine', $this->t('Its not possible to receive magazine outside Denmark. Please uncheck checkbox.'));
    }

    //Membershiptype != DEAL and Country != DK - we need to see documentation of Danish citizenship.
    if ($form_state->getValue("confirmed_dk_citizenship") == 0 && $form_state->getValue("membershiptype") != 6 && $form_state->getValue("country") != 'DK') {
      $form_state->setErrorByName('confirmed_dk_citizenship', $this->t('Please get documention of Danish citizenship and check below checkbox -> Confirmed Danish citizenship'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    //Membertype.
    $membership_term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($form_state->getValue('membershiptype'));
    $member_type = ucfirst($membership_term->get('field_navision_membertype')->getValue()[0]['value']);
    $user_data = [
      'member_type' => $member_type,
      'email' => $form_state->getValue('email'),
      'password' => $form_state->getValue('password'),
      'country' => $form_state->getValue('country'),
      'campaigncode' => $form_state->getValue('campaigncode'),
      'firstname' => $form_state->getValue('firstname'),
      'lastname' => $form_state->getValue('lastname'),
      'mobile' => $form_state->getValue('mobile'),
      'birthdate' => date('Y-m-d', strtotime($form_state->getValue('birthdate'))),
      'street' => $form_state->getValue('street'),
      'zipcode' => $form_state->getValue('zipcode'),
      'city' => $form_state->getValue('city'),
      'newsletter' => $form_state->getValue('newsletter'),
      'contact_consent' => $form_state->getValue('contact_consent'),
      'receive_magazine' => $form_state->getValue('receive_magazine'),
      'comment' => $form_state->getValue('comments'),
      'confirmed_dk_citizenship' => $form_state->getValue('confirmed_dk_citizenship'),
      'preferred_langcode' => $form_state->getValue('preferred_langcode')
    ];

    //Campaigncode
    $campaigncode = false;
    if (!empty($form_state->getValue('campaigncode'))) {
      $campaigncode = $form_state->getValue('campaigncode');
    }

    $account = dcu_member_create_dcu_member($user_data);
    // Unset data password to prevent from sending it to errorlogs.
    unset($user_data['password']);

    if (!$account) {
      // TODO: How to treat user creation fail ?
      \Drupal::logger('dcu_member')->error('Function user_save returned false when trying to create user with values: @data', ['@data' => print_r($user_data, TRUE)]);
      \Drupal::messenger()->addMessage($this->t('New membership could not be created. Please try again or contact dcu memberservice'), 'error');
      return FALSE;
    }

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
      $account->addRole('dcu_membership');
      $account->activate();
      $account->save();
      \Drupal::messenger()->addMessage($this->t('User has been created'), 'status');

      //Send welcome mail.
      $mail_params = [
        'to' => $account->getEmail(),
        'subject' => t('You have created a user account on dcu.dk'),
        'name' => $user_data['firstname'],
        'member_no' => $nav_member_id,
        'language' => \Drupal::languageManager()->getCurrentLanguage()->getId(),
      ];
      dcu_member_send_mail('welcome', $mail_params);
    }
    catch (Exception $e) {
      // TODO: How to treat user update fail ?
      \Drupal::logger('dcu_member')->error('Unable to set username and memberid for userid: @user', ['@user' => $account->id()]);
      \Drupal::messenger()->addMessage($this->t('There was an unexpected error creating membership. Please try again or contact dcu memberservice'), 'error');
      return FALSE;
    }

    //Mailchimp.
    if (!empty($user_data['newsletter'])) {
      $result = dcu_member_mailchimp_subscribe($account);
      if ($result) {
        \Drupal::messenger()->addMessage($this->t('User has been subscribed to newsletter'), 'status');
      }
      else {
        \Drupal::messenger()->addMessage($this->t('User has NOT subscribed to newsletter'), 'error');
      }
    }
  }
}
