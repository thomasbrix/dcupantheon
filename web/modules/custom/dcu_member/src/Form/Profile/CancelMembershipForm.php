<?php

namespace Drupal\dcu_member\Form\Profile;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\dcu_navision\Client\NavisionRestClient;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class CancelMembershipForm.
 */
class CancelMembershipForm extends FormBase {

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
    return 'profile_cancel_membership_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $userId = NULL) {
    if (empty($userId)) {
      $userId = \Drupal::currentUser()->id();
    }
    $form['userid'] = array(
      '#type' => 'value',
      '#value' => $userId,
    );
    $form['header'] = [
      '#markup' => '<h1>' . $this->t('Cancel membership') . '</h1>',
    ];
    $form['message'] = [
      '#markup' => '<p>' . t('Please confirm that you want to cancel your membership at dcu') . '</p>',
      '#weight' => -8
    ];
    $form['next'] = [
      '#type' => 'submit',
      '#value' => t('Cancel my membership'),
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
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $userId = $form_state->getValue('userid');
    $user = User::load($userId);
    $navData = dcu_member_get_user_navdata($user);

    /* TBX Should this just be done with unsubscribe ? It does not work -
    so this function is implemented by changing membership type to Gratis */
    //$response = dcu_navision_unsubscribe_member($memberno);

    $nav_membershiptype = 'gratis';
    $new_membership_term = dcu_member_get_membership_term_from_navname($nav_membershiptype);
    $nav_membership_params = array (
      'memberno' => $navData->memberno,
      'newmembertype' => ucfirst($nav_membershiptype),
      'pensionvalidated' => TRUE,
      'changedby' => dcu_member_get_member_update_role(),
      'partnerid' => 'Drupal'
    );
    $response = $this->navisionClient->changeSubscriptionType($nav_membership_params);

    if (!$response) {
      \Drupal::messenger()->addMessage($this->t('There was a problem changing your membership data. Try again or contact memberservice.'), 'error');
    }
    else {
      if ($navData->memberstatus === 'AKTIV') {
        \Drupal::messenger()->addMessage($this->t('Your membership has been updated. Membership will be ACTIVE until membership runs out.'), 'status');
      }
      else{
        \Drupal::messenger()->addMessage($this->t('Your membership has been updated.'), 'status');
      }
      $user->set('field_membership_type', $new_membership_term->id());
      try {
        $user->save();
      } catch (EntityStorageException $e) {
        \Drupal::messenger()->addMessage($this->t('There was an error updating your information'), 'error');
        \Drupal::logger('dcu_member')->error('Error trying to save memberdata Drupal account. userid: @uid message: @message', ['@uid' => $userId, '@message' => $e->getMessage()]);
      }
    }
    $url = Url::fromRoute('dcu_member.user_profile', ['userId' => $userId]);
    return $form_state->setRedirectUrl($url);
  }

}
