<?php

namespace Drupal\dcu_member\Form\Profile;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\dcu_navision\Client\NavisionRestClient;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class RelativesForm.
 */
class RelativesForm extends FormBase {

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
    return 'profile_relatives_form';
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
      '#markup' => '<h1>' . $this->t('Family') . '</h1>',
    ];

    $children = [];
    $spouse = FALSE;
    if (!empty($navData->relatives)) {
      foreach ($navData->relatives as $relative) {
        if ($relative->reltype == 'Spouse') {
          $spouse = $relative;
        }
        if ($relative->reltype == 'Child') {
          $children[] = $relative;
        }
      }
    }

    if (!in_array(strtolower($navData->membertype), ['ungdom', 'person'])) {
      $form['showspouse'] = [
        '#type' => 'hidden',
        '#value' => TRUE,
      ];
      $form['spousename'] = [
        '#title' => t('Name'),
        '#type' => 'textfield',
        '#default_value' => !empty($spouse->relname) ? $spouse->relname : '',
      ];
      $form['spousebirthday'] = [
        '#title' => t('Birthday'),
        '#type' => 'textfield',
        '#default_value' => !empty($spouse->relbirthday) ? $spouse->relbirthday : '',
      ];
      $form['spouserelid'] = [
        '#type' => 'hidden',
        '#default_value' => !empty($spouse->relativeno) ? $spouse->relativeno : '',
      ];
    }
    $nextEmptyChild = 0;
    for ($i = 0; $i<DCU_MEMBER_MAX_CHILDREN; $i++) {
      $childname = !empty($children[$i]) ? $children[$i]->relname : '';
      $childbd = !empty($children[$i]->relbirthday) ? $children[$i]->relbirthday : '';
      $navid = !empty($children[$i]) ? $children[$i]->relativeno : '';
      $form['children'][$i]['name_' . $i] = [
        '#title' => t('Name'),
        '#type' => 'textfield',
        '#default_value' => $childname,
      ];
      $form['children'][$i]['birthday_' . $i] = [
        '#title' => t('Birthday'),
        '#type' => 'textfield',
        '#default_value' => $childbd,
      ];
      $form['children'][$i]['navid_' . $i] = [
        '#type' => 'hidden',
        '#default_value' => $navid,
      ];
      if (!empty($navid)) {
        $form['children'][$i]['delete_' . $i] = [
          '#type' => 'checkboxes',
          '#default_value' => [],
          '#options' => [$navid => $this->t('Remove'),],
          '#title' => '',
        ];
        $form['children'][$i]['#collapse'] = '';
        $nextEmptyChild++;
      }
      else {
        $form['children'][$i]['#collapse'] = 'collapse';
      }
    }
    $form['nextchild'] = [
      '#type' => 'value',
      '#value' => $nextEmptyChild,
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
    $form['#attached']['library'][] = 'dcu_member/dcu_member.profile';
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
    $userId = $form_state->getValue('userid');
    $user = User::load($userId);
    $navData = dcu_member_get_user_navdata($user);
    $children = [];
    $spouse = FALSE;
    if (!empty($navData->relatives)) {
      foreach ($navData->relatives as $relative) {
        if ($relative->reltype == 'Spouse') {
          $spouse = $relative;
          if (empty($spouse->relname)) {
            // Avoid undefined as Navision does not send full parameter set.
            $spouse->relname = '';
          }
          if (empty($spouse->relbirthday)) {
            $spouse->relbirthday = '';
          }
        }
        if ($relative->reltype == 'Child') {
          $children[] = $relative;
        }
      }
    }
    if (empty($spouse) && (!empty($form_state->getValue('spousename')) || !empty($form_state->getValue('spousebirthday')))) {
      $this->navisionClient->createRelative(
        $navData->memberno, [
        'reltype' => 'Spouse',
        'relname' => $form_state->getValue('spousename'),
        'relbirthday' => $form_state->getValue('spousebirthday'),
      ]);
    }
    elseif (!empty($spouse) && ($spouse->relname != $form_state->getValue('spousename') || $spouse->relbirthday != $form_state->getValue('spousebirthday'))) {
      $this->navisionClient->updateRelative(
        $navData->memberno, [
          'relativeno' => $form_state->getValue('spouserelid'),
          'reltype' => 'Spouse',
          'relname' => $form_state->getValue('spousename'),
          'relbirthday' =>$form_state->getValue('spousebirthday'),
        ]
      );
    }
    for ($i = 0; $i < DCU_MEMBER_MAX_CHILDREN; $i++) {
      $namekey = 'name_' . $i;
      $bdkey = 'birthday_' . $i;
      $navidkey = 'navid_' . $i;
      $deletekey = 'delete_' . $i;
      if (!empty($form_state->getValue($navidkey))) {
        if (!empty($navrelid = reset($form_state->getValue($deletekey)))) {
          $this->navisionClient->deleteRelative($navData->memberno, $navrelid);
        }
        else {
          $this->navisionClient->updateRelative(
            $navData->memberno, [
              'relativeno' => $form_state->getValue($navidkey),
              'reltype' => 'Child',
              'relname' => $form_state->getValue($namekey),
              'relbirthday' => $form_state->getValue($bdkey),
            ]
          );
        }
      }
      elseif (!empty($form_state->getValue($namekey))) {
        $this->navisionClient->createRelative(
          $navData->memberno, [
          'reltype' => 'Child',
          'relname' => $form_state->getValue($namekey),
          'relbirthday' => $form_state->getValue($bdkey),
        ]);
      }
    }
    \Drupal::messenger()->addMessage($this->t('User data updated'), 'status');
    $url = Url::fromRoute('dcu_member.user_profile', ['userId' => $userId]);
    return $form_state->setRedirectUrl($url);
  }

}
