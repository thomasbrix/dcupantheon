<?php

namespace Drupal\dcu_admin\Form;

use Drupal\advancedqueue\Entity\Queue;
use Drupal\advancedqueue\Job;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\dcu_navision\Client\NavisionRestClient;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class MemberMail.
 */
class MemberMail extends FormBase {

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
    return 'member_mail';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory()
      ->getEditable('dcu_admin.member_mail');
    $values = $form_state->getValues();

    //$recurQueue = Queue::load('member_mail');
    //$countJobs = $recurQueue->getBackend()->countJobs();
    //$itemCnt = $countJobs['queued'];
    $itemCnt = 0; //
    $form['membermail'] = array(
      '#type' => 'fieldset',
      '#title' => t('DCU Member notification email'),
    );
    $form['membermail']['markup'] = array(
      '#markup' => '<p>' . t('Send notification email to members. All members in the chosen target group will receive this email.') . '</p>',
    );
    $form['membermail']['queue'] = array(
      '#markup' => '<p>' . t('There are currently @itemcnt elements in mail queue, waiting to be send by cron.', ['@itemcnt' => $itemCnt]) . '</p>',
    );
    $form['membermail']['subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Email subject'),
      '#default_value' => $config->get('subject'),
      '#maxlength' => 64,
      '#size' => 64,
      '#weight' => '0',
    ];
    $form['membermail']['message'] = [
      '#type' => 'text_format',
      '#format' => 'basic_html',
      '#title' => $this->t('Email message body'),
      '#weight' => '0',
      '#default_value' => $config->get('message'),
      '#allowed_formats' => ['basic_html'],
      '#description' => $this->t('Tokens available for message body: %name, %memberno, %membertype',
        ['%name' => '[name]', '%memberno' => '[memberno]', '%membertype' => '[membertype]'])
    ];
    $form['receivers_wrapper']['test_email'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Test email'),
      '#description' => $this->t('Set this to send only one email to test address and not process email to recurring members'),
      '#maxlength' => 64,
      '#size' => 64,
      '#weight' => '0',
    ];
    $form['membermail']['target'] = [
      '#type' => 'select',
      '#title' => $this->t('Target group'),
      '#description' => $this->t('Choose target group for email'),
      '#default_value' => (isset($values['receivers']) ? $values['receivers'] : ''),
      '#options' => [
        '' => 'Vælg modtager eller ryd formular',
        'test' => 'Test email',
        'member_balance' => 'Members with balance > 0',
        'active_members' => 'Active members',
        'clear' => 'Ryd Formular data',
      ],
      '#ajax' => [
        'callback' => [$this, 'changeReceiver'],
        'event' => 'change',
        'wrapper' => 'receivers_wrapper',
      ],
    ];
    // Build a wrapper for the ajax response.
    $form['receivers_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'receivers_wrapper',
      ]
    ];
    $form['receivers_wrapper']['targets'] = [
      '#markup' => '<p>' . $this->t('The current target group is empty') . '</p>',
    ];

    // ONLY LOADED IN AJAX RESPONSE OR IF FORM STATE VALUES POPULATED.
    if (!empty($values) && !empty($values['target'])) {
      $form['receivers_wrapper']['targets'] = [
        '#markup' => '<p>' .$this->t('The current selected target group is: ') . $values['target'] . '</p>',
      ];
      if ($values['target'] == 'test') {
        $form['receivers_wrapper']['test_email'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Test email'),
          '#description' => $this->t('Set this to send only one email to test address and not process email to recurring members'),
          '#maxlength' => 64,
          '#size' => 64,
          '#weight' => '0',
        ];
      }
      elseif ($values['target'] == 'member_balance') {

      }
    }
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save email & Send email to all chosen target group'),
    ];
    return $form;
  }

   /**
   * The callback function for when the `receivers` element is changed.
   */
  public function changeReceiver(array $form, FormStateInterface $form_state) {
    // Return the element that will replace the wrapper (we return itself).
    return $form['receivers_wrapper'];
  }


  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $messageraw = '';
    // Save or clear current subject and message to settings.
    if (!empty($form_state->getValue('target')) && $form_state->getValue('target') == 'clear') {
      $this->configFactory()
        ->getEditable('dcu_admin.member_mail')
        ->set('subject', '')
        ->set('message', '')
        ->save();
      \Drupal::messenger()->addMessage(t('Member email fields reset'), 'status');
    }
    else {
      $messageraw = !empty($form_state->getValue('message')) ? $form_state->getValue('message')['value'] : '';
      $this->configFactory()
        ->getEditable('dcu_admin.member_mail')
        ->set('subject', $form_state->getValue('subject'))
        ->set('message', $messageraw)
        ->save();
    }
    $receivers = [];
    if (!empty($form_state->getValue('test_email'))) {
      $name = 'NAVN';
      $membertype = 'MEDLEMSTYPE';
      $memberno = '0000001';
      $message = str_replace('[name]', $name, $messageraw);
      $message = str_replace('[memberno]', $memberno, $message);
      $message = str_replace('[membertype]', $membertype, $message);
      $receivers[] =
        [
          'name' => $name,
          'membertype' => $membertype,
          'memberno' => $memberno,
          'email' => $form_state->getValue('test_email'),
          'subject' => $form_state->getValue('subject'),
          'message' => $message,
        ];
    }
    elseif($form_state->getValue('target') == 'member_balance') {
      $balanceMembers = $this->navisionClient->membersWithBalance();
      if (empty($balanceMembers)) {
        \Drupal::messenger()->addMessage(t('Navision returned empty data for members with balance'), 'info');
        return;
      }
      foreach ($balanceMembers as $member) {
        if (empty($member->email) || strpos($member->email, 'random') !== FALSE || strpos($member->email, 'nomail' ) !== FALSE ) {
          continue;
        }
        if ($member->balance > 0) {
          $name = $member->firstname;
          $membertype = $member->membertype;
          $memberno = $member->memberno;
          $message = str_replace('[name]', $name, $messageraw);
          $message = str_replace('[memberno]', $memberno, $message);
          $message = str_replace('[membertype]', $membertype, $message);
          $receivers[] =
            [
              'name' => $name,
              'membertype' => $membertype,
              'memberno' => $memberno,
              'email' => $member->email,
              'subject' => $form_state->getValue('subject'),
              'message' => $message,
            ];
        }
      }
      \Drupal::logger('dcu_member_message_mail')->notice('Process dcu_member_message_mail submitted. Members being queue now.');
    }
    elseif ($form_state->getValue('target') == 'active_members') {
      $activeMembers = $this->navisionClient->getActiveMembers();
      if (empty($activeMembers)) {
        \Drupal::messenger()->addMessage(t('Navision returned empty data for members with balance'), 'info');
        return;
      }
      foreach ($activeMembers as $member) {
        if (empty($member->email) || strpos($member->email, 'random') !== FALSE || strpos($member->email, 'nomail' ) !== FALSE ) {
          continue;
        }
        $name = $member->membername;
        $membertype = $member->membertype;
        $memberno = $member->memberno;
        $message = str_replace('[name]', $name, $messageraw);
        $message = str_replace('[memberno]', $memberno, $message);
        $message = str_replace('[membertype]', $membertype, $message);
        $receivers[] =
          [
            'name' => $name,
            'membertype' => $membertype,
            'memberno' => $memberno,
            'email' => $member->email,
            'subject' => $form_state->getValue('subject'),
            'message' => $message,
          ];
      }
      \Drupal::logger('dcu_member_message_mail')->notice('Process dcu_member_message_mail submitted. Members being queue now.');
    }
    $recurQueue = Queue::load('member_mail');
    $successCount = 0;
    $failed = [];
    foreach ($receivers as $receiver) {
      $job = Job::create('dcu_member_message_mail', $receiver);
      $recurQueue->enqueueJob($job);
      if (!$job->getState() == 'queued') {
        \Drupal::logger('dcu_admin_member_message_mail_error')->error('Error trying to queue item for dcu_member_message_mail. Item: @info', ['@info' => print_r($receiver, 1)]);
        $failed[] = $receiver;
      }
      else {
        $successCount++;
      }
    }
    \Drupal::messenger()->addMessage(t('Information email to @count members where queued for sending', array('@count' => $successCount)), 'status');
    if ($successCount != count($receivers)) {
      $diff = count($receivers) - $successCount;
      \Drupal::messenger()->addMessage(t('Not all email succeeded in being queued. @count failed', array('@count' => $diff)), 'error');
      \Drupal::logger('dcu_admin_member_mail_queue_fail')->notice('@data', ['@data' => Json::encode($failed)]);
    }
  }

}
