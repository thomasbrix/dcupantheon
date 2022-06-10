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
 * Class RecurringMail.
 */
class RecurringMail extends FormBase {

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
    return 'recurring_mail';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $recurQueue = Queue::load('recurring_mail');
    $countJobs = $recurQueue->getBackend()->countJobs();
    $itemCnt = $countJobs['queued'];
    $form['recurring'] = array(
      '#type' => 'fieldset',
      '#title' => t('Recurring member notification email'),
    );
    $form['recurring']['markup'] = array(
      '#markup' => '<p>' . t('Send notification email about recurring payments. All members with an active recurring payment will receive email.') . '</p>',
    );
    $form['recurring']['queue'] = array(
      '#markup' => '<p>' . t('There are currently @itemcnt elements in mail queue, waiting to be send by cron.', ['@itemcnt' => $itemCnt]) . '</p>',
    );
    $form['recurring']['test_email'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Test email'),
      '#description' => $this->t('Set this to send only one email to test address and not process email to recurring members'),
      '#maxlength' => 64,
      '#size' => 64,
      '#weight' => '0',
    ];
    $form['recurring']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send email to all recurring members'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if (!empty($form_state->getValue('test_email'))) {
      $recurringMembers = [
        [
          'name' => 'Test name',
          'email' => $form_state->getValue('test_email'),
        ]
      ];
    }
    else {
      $recurringMembers = $this->navisionClient->getRecurring();
      if (empty($recurringMembers)) {
        \Drupal::messenger()->addMessage(t('Navision returned empty data for recurring members'), 'info');
        return;
      }
      \Drupal::logger('dcu_admin_recurring_mail')->notice('Process dcu_recurring_message_mail submitted. members being queue now.');
    }
    $recurQueue = Queue::load('recurring_mail');
    $successCount = 0;
    $failed = [];
    foreach ($recurringMembers as $member) {
      $memberData = (array)$member;
      $job = Job::create('dcu_recurring_message_mail', $memberData);
      $recurQueue->enqueueJob($job);
      if (!$job->getState() == 'queued') {
        \Drupal::logger('dcu_admin_recurring_mail_error')->error('Error trying to queue item for dcu_recurring_message_mail. Item: @info', ['@info' => print_r($memberData, 1)]);
        $failed[] = $memberData;
      }
      else {
        $successCount++;
      }
    }
    \Drupal::messenger()->addMessage(t('Information email to @count members where queued for sending', array('@count' => $successCount)), 'status');
    if ($successCount != count($recurringMembers)) {
      $diff = count($recurringMembers) - $successCount;
      \Drupal::messenger()->addMessage(t('Not all recurring email succeeded in being queued. @count failed', array('@count' => $diff)), 'error');
      \Drupal::logger('dcu_admin_recurring_mail_queue_fail')->notice('@data', ['@data' => Json::encode($failed)]);
    }
  }

}
