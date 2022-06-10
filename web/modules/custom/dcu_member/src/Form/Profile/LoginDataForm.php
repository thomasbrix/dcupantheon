<?php

namespace Drupal\dcu_member\Form\Profile;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\dcu_navision\Client\NavisionRestClient;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class LoginDataForm.
 */
class LoginDataForm extends FormBase {

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
    return 'profile_logindata_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $userId = NULL) {
    if (empty($userId)) {
      $userId = \Drupal::currentUser()->id();
    }
    $user = User::load($userId);
    $navData = dcu_member_get_user_navdata($user);
    $form['userid'] = array(
      '#type' => 'value',
      '#value' => $userId,
    );
    $form['#theme'] = $this->getFormId();
    $form['header'] = [
      '#markup' => '<h1>' . $this->t('Edit Login') . '</h1>',
    ];
    $form['next'] = [
      '#type' => 'submit',
      '#value' => t('Save settings'),
      '#attributes' => [
        'class' => ['disable-on-click'],
      ],
    ];
    $form['cancel'] = [
      '#type' => 'button',
      '#value' => t('Cancel'),
      '#attributes' => ['onClick' => 'history.go(-1); event.preventDefault();'],
    ];
    $form['email'] = [
      '#type' => 'textfield',
      '#title' => t('Email'),
      '#attributes' => ['placeholder' => t('Email')],
      '#default_value' => $navData->email,
      '#required' => TRUE,
    ];
    $form['newpassword'] = [
      '#title' => t('Change Password'),
      '#type' => 'password',
      '#size' => 25,
      '#attributes' => ['placeholder' => t('New Password')],
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

    //If user change existing email.
    $userId = $form_state->getValue('userid');
    $user = User::load($userId);
    if (!empty($form_state->getValue('email')) && $form_state->getValue('email') != $user->getEmail()) {
      $email = strtolower(str_replace(' ', '', $form_state->getValue('email')));
      $form_state->setValue('email', $email);

      if (!\Drupal::service('email.validator')->isValid($email)) {
        $form_state->setErrorByName('email', $this->t('@val is not a valid E-mail address.', ['@val' => $email]));
      }
      else {
        if ($this->navisionClient->emailExist($email)) {
          $form_state->setErrorByName('email', $this->t('The E-mail address @val already exists at dcu/Nav. <a href="/user">Login with E-mail and password here</a>.', ['@val' => $email]));
        }
        else {
          if (user_load_by_mail($email)) {
            $form_state->setErrorByName('email', $this->t('The E-mail address @val already exists at dcu/Drupal. <a href="/user">Login with E-mail and password here</a>.', ['@val' => $email]));
          }
        }
      }
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $userId = $form_state->getValue('userid');
    $user = User::load($userId);
    $email = $form_state->getValue('email');
    $update = [];
    if (!empty($form_state->getValue('newpassword'))) {
      $user->setPassword($form_state->getValue('newpassword'));
      $user->save();
      $update[] = $this->t('Password');
    }
    if (!empty($email) && $email != $user->getEmail()) {
      $user->setEmail($email);
      $user->save();
      $update[] = $this->t('Email');

      //TODO: TBX Email changed - need to check with MailChimp.
      //dcu_member_profile_mailchimp_check($user->mail, $form_state['values']['email'], $user->uid);
    }
    if (!empty($update)) {
      if (!dcu_member_send_userdata_to_nav($userId)) {
        \Drupal::messenger()->addMessage($this->t('There was an error updating your information'), 'error');
        return FALSE;
      }
      \Drupal::messenger()->addMessage($this->t('User data updated'), 'status');
    }
    $url = Url::fromRoute('dcu_member.user_profile', ['userId' => $userId]);
    return $form_state->setRedirectUrl($url);
  }

}
