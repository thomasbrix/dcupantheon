<?php

namespace Drupal\dcu_member\Form\Profile;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\dcu_navision\Client\NavisionRestClient;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class MembershipForm.
 */
class MembershipForm extends FormBase {

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
    return 'profile_membership_form';
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
    $memberType = $navData->membertype;
    $showMag = dcu_member_is_magazine_membertype($memberType);
    $useMagAddr = !empty($navData->magazineaddress);
    $allowedMembertypes = [];
    $disallowMembertypes = [];
    $selected = '';

    //If confirmed Danish citizenship - its OK to show all options.
    if ($navData->country != 'DK' && $user->field_confirmed_dk_citizenship->value == 0) {
      $allowedMembertypes = [
        'Deal',
        'Gratis',
      ];
    }
    else {
      $disallowMembertypes = [
        'Deal',
        'Gratis'
      ];
    }

    $age = dcu_utility_calculate_age($navData->birthday);
    if (($age < 18) || ($age > 25)) {
      $disallowMembertypes[] = 'Ungdom';
    }
    $membershipTerms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('membershiptypes',  0, NULL, TRUE);
    foreach ($membershipTerms as $term) {
      if (!empty($allowedMembertypes) && !in_array( $term->getName(), $allowedMembertypes)) {
        continue;
      }
      elseif (!empty($disallowMembertypes) && in_array( $term->getName(), $disallowMembertypes)) {
        continue;
      }
      if (strcasecmp($term->get('field_navision_membertype')->getValue()[0]['value'], $memberType) == 0) {
        $selected = $term->id();
      }
      $options[$term->id()] = $term->getName();
    }

    $form['userid'] = array(
      '#type' => 'value',
      '#value' => $userId,
    );
    $form['#theme'] = $this->getFormId();
    $form['header'] = [
      '#markup' => '<h1>' . $this->t('Membership') . '</h1>',
    ];
    if (!empty($navData->unsubscribeddate)) {
      $unsubscribed = t('You have cancelled your membership on @date.',
        [
          '@date' => date("d-m-Y", strtotime($navData->unsubscribeddate)),
        ]);
      $enddate = '';
      if (!empty($navData->subsenddate)) {
        $enddate = t('Your membership runs to @enddate', [
          '@enddate' => date("d-m-Y", strtotime($navData->subsenddate)),
        ]);
      }
      $form['unsubscribed'] = ['#markup' => $unsubscribed . " " . $enddate];
      $form['resubscribe'] = [
        '#title' => t('I want to reactivate my membership'),
        '#type' => 'checkbox',
        '#default_value' => 0,
      ];
    }
    elseif ($navData->memberstatus == 'AKTIV') {
      $cancelurl = Url::fromRoute('dcu_member.profile_cancel_membership_form', ['userId' => $user->id()], ['attributes' => ['class' => ['cancelmembership'],],]);
      $cancellink = Link::fromTextAndUrl($this->t('Cancel membership'), $cancelurl);
      $form['cancelmembership'] = [
        '#markup' =>  $cancellink->toString(),
      ];
    }
    if (!empty($navData->bsactive)) {
      $form['bsactive'] = [
        '#markup' => '<div class="mb-3">' . t('You are registered for Betalingsservice and your subscription is automatically renewed.') . '</div>',
      ];
    }
    $form['membershiptype'] = [
      '#title' => t('Abonnement'),
      '#type' => 'select',
      '#required' => TRUE,
      '#options' => $options,
      '#default_value' => $selected,
    ];
    $form['membershiptype_orig'] = [
      '#type' => 'value',
      '#default_value' => $selected,
    ];
    $form['membership_active'] = [
      '#type' => 'value',
      '#default_value' => $selected,
    ];
    $form['membership_status'] = [
      '#type' => 'value',
      '#default_value' => $navData->memberstatus,
    ];

    //Only danish members are allowed to add alternative delivery address.

    if ($navData->country == 'DK' || $user->field_confirmed_dk_citizenship->value == 1) {
      if ($showMag) {
        $form['magaddress'] = [
          '#title' => t('Use different delivery address for Magazine'),
          '#type' => 'checkbox',
          '#default_value' => $useMagAddr,
        ];
        $form['magazinedata'] = [
          '#title' => t('Magazine delivery address'),
          '#type' => 'fieldset',
          '#states' => [
            'visible' => [
              ':input[name="magaddress"]' => [
                'checked' => TRUE,
              ],
            ],
          ],
        ];
        $form['magazinedata']['magazinefirstname'] = [
          '#title' => t('Firstname'),
          '#type' => 'textfield',
          '#attributes' => ['placeholder' => t('Firstname')],
          '#default_value' => !empty($navData->magazinefirstname) ? $navData->magazinefirstname : '',
        ];
        $form['magazinedata']['magazinelastname'] = [
          '#title' => t('Surname'),
          '#type' => 'textfield',
          '#attributes' => ['placeholder' => t('Surname')],
          '#default_value' => !empty($navData->magazinelastname) ? $navData->magazinelastname : '',
        ];
        $form['magazinedata']['magazineaddress'] = [
          '#title' => t('Address'),
          '#type' => 'textfield',
          '#attributes' => ['placeholder' => t('Address')],
          '#default_value' => !empty($navData->magazineaddress) ? $navData->magazineaddress : '',
        ];
        $form['magazinedata']['magazinepostalcode'] = [
          '#title' => t('Zipcode'),
          '#type' => 'textfield',
          '#attributes' => ['placeholder' => t('Zipcode')],
          '#default_value' => !empty($navData->magazinepostalcode) ? $navData->magazinepostalcode : '',
        ];
        $form['magazinedata']['magazinecity'] = [
          '#title' => t('City'),
          '#type' => 'textfield',
          '#attributes' => ['placeholder' => t('City')],
          '#default_value' => !empty($navData->magazinecity) ? $navData->magazinecity : '',
        ];
      }
    }

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

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (!empty($form_state->getValue('magaddress'))) {
      $required_field_keys = array(
        'magazinefirstname',
        'magazinelastname',
        'magazineaddress',
        'magazinepostalcode',
        'magazinecity',
      );
      foreach ($required_field_keys as $field_key) {
        if (empty($form_state->getValue($field_key)) ) {
          $field_title = $form['magazinedata'][$field_key]['#title']->getUntranslatedString();
          $form_state->setErrorByName($field_key, $this->t('@field is required for specifying custom Magazine address', ['@field' => $field_title]));
        }
      }
    }
    if (!empty($form_state->getValue('magazinepostalcode')) && !is_numeric($form_state->getValue('magazinepostalcode'))) {
      $form_state->setErrorByName('magazinepostalcode', $this->t('Only numbers are allowed in Zipcode'));
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $userId = $form_state->getValue('userid');
    $user = User::load($userId);
    if (!empty($form_state->getValue('magaddress'))) {
      $user->set('field_magazine_first_name', $form_state->getValue('magazinefirstname'));
      $user->set('field_magazine_last_name', $form_state->getValue('magazinelastname'));
      $user->set('field_magazine_address', $form_state->getValue('magazineaddress'));
      $user->set('field_magazine_zip', $form_state->getValue('magazinepostalcode'));
      $user->set('field_magazine_city', $form_state->getValue('magazinecity'));
      $user->set('field_use_other_magazine_address', 1);
    }
    else {
      $user->set('field_magazine_first_name', '');
      $user->set('field_magazine_last_name', '');
      $user->set('field_magazine_address', '');
      $user->set('field_magazine_zip', NULL);
      $user->set('field_magazine_city', '');
      $user->set('field_use_other_magazine_address', 0);
    }

    if (!empty($form_state->getValue('resubscribe'))) {
      dcu_member_profile_reactivate_membership($user->get('field_memberid')->getString());
    }
    $new_membershiptype = $form_state->getValue('membershiptype');
    $orig_membershiptype = $form_state->getValue('membershiptype_orig');
    if ($new_membershiptype != $orig_membershiptype) {
      $new_membership_term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($new_membershiptype);
      $nav_membershiptype = ucfirst($new_membership_term->get('field_navision_membertype')->getValue()[0]['value']);
      $nav_membership_params = array (
        'memberno' => $user->get('field_memberid')->getString(),
        'newmembertype' => $nav_membershiptype,
        'pensionvalidated' => TRUE,
        'changedby' => dcu_member_get_member_update_role(),
        'partnerid' => 'Drupal'
      );
      $response = $this->navisionClient->changeSubscriptionType($nav_membership_params);
      if (!$response) {
        \Drupal::messenger()->addMessage($this->t('There was a problem changing your membership data. Try again or contact memberservice.'), 'error');
      }
      else {
        if (in_array($nav_membershiptype, ['gratis', 'deal']) && ($form_state->getValue('membership_status') === 'AKTIV')) {
          \Drupal::messenger()->addMessage($this->t('Your membership has been updated. Membership will be ACTIVE until membership runs out.'), 'status');
        }
        else{
          \Drupal::messenger()->addMessage($this->t('Your membership has been updated.'), 'status');
        }
        $user->set('field_membership_type', $new_membership_term->id());
      }
    }

    try {
      $user->save();
    } catch (EntityStorageException $e) {
      \Drupal::messenger()->addMessage($this->t('There was an error updating your information'), 'error');
      \Drupal::logger('dcu_member')->error('Error trying to save memberdata Drupal account. userid: @uid message: @message', ['@uid' => $userId, '@message' => $e->getMessage()]);
      return FALSE;
    }
    if (!$navresult = dcu_member_send_userdata_to_nav($userId)) {
      \Drupal::messenger()->addMessage($this->t('There was an error updating your information'), 'error');
      return FALSE;
    }
    \Drupal::messenger()->addMessage($this->t('User data updated'), 'status');
    $url = Url::fromRoute('dcu_member.user_profile', ['userId' => $userId]);
    return $form_state->setRedirectUrl($url);
  }

}
