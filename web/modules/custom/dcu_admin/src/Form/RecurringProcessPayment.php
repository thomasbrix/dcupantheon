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
 * Class RecurringProcessPayment.
 */
class RecurringProcessPayment extends FormBase {

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
    return 'recurring_process_payment';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $recurQueue = Queue::load('recurring_payment');
    $countJobs = $recurQueue->getBackend()->countJobs();
    $itemCnt = $countJobs['queued'];
    $form['recurring'] = array(
      '#type' => 'fieldset',
      '#title' => t('Start payment process'),
    );
    $form['recurring']['markup'] = array(
      '#markup' => '<p>' . t('Queue all members with an active recurring payment for transaction with Bamborra. If testing, click Fill Testdata, edit input and send') . '</p>',
    );
    $form['recurring']['ajaxfill'] = array(
      '#type' =>'button',
      '#value' => t('Fill Testdata input with default test data'),
      '#ajax' => array(
        'callback' =>  array($this, 'recurringSetTestData'),
        'wrapper' => 'testdatainput',
        'effect' => 'fade',
        'prevent'=> 'sumbmit',
      ),
    );
    $form['recurring']['testdata'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Testdata'),
      '#description' => $this->t('Set this to add and process only testdata'),
      '#prefix' => '<div id="testdatainput">',
      '#suffix' => '</div>'
    );
    $form['recurring']['queue'] = array(
      '#markup' => '<p>' . t('There are currently @itemcnt elements in the transaction queue, waiting to be send to bamborra by cron.', array('@itemcnt' => $itemCnt)) . '</p>',
    );
    $form['recurring']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Process recurring payments'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $recurringMembers = [];
    if (!empty($form_state->getValue('testdata'))) {
      $recurringMembers[] = json_decode($form_state->getValue('testdata'), TRUE);
    }
    else {
      $recurringMembers = $this->navisionClient->getRecurring();
    }
    if (empty($recurringMembers)) {
      \Drupal::messenger()->addMessage(t('Navision returned empty data for recurring members'), 'info');
      return;
    }
    \Drupal::logger('dcu_recurring_payment')->notice('Process dcu_recurring_payment submitted. @count members being queue now.', ['@count' => count($recurringMembers)]);
    $recurQueue = Queue::load('recurring_payment');
    $successCount = 0;
    $failed = [];
    foreach ($recurringMembers as $member) {
      $memberData = (array)$member;
      if (empty($memberData)) {
        \Drupal::logger('dcu_admin_recurring_payment_queue_error')->error('@data', ['@data' => Json::encode(['message' => 'Empty item trying to queue dcu_recurring_process_payment'])]);
        continue;
      }
      $job = Job::create('dcu_recurring_process_payment', $memberData);
      $recurQueue->enqueueJob($job);
      if (!$job->getState() == 'queued') {
        \Drupal::logger('dcu_admin_recurring_payment_queue_error')->error('@data', ['@data' => Json::encode(['message' => 'Error trying to queue item for dcu_recurring_process_payment', 'item' => $memberData])]);
        $failed[] = $memberData;
      }
      else {
        $successCount++;
      }
    }
    \Drupal::messenger()->addMessage(t('Payments will automatically be requested via Bambora for @count members via batch processing.', ['@count' => $successCount]), 'status');
    if ($successCount != count($recurringMembers)) {
      $diff = count($recurringMembers) - $successCount;
      \Drupal::messenger()->addMessage(t('Not all recurring payment succeeded in being queued. @count failed', array('@count' => $diff)), 'error');
      \Drupal::logger('dcu_admin_recurring_payment_queue_error')->error('@data', ['@data' => Json::encode(['message' => 'Not all recurring payment succeeded in being queued', 'failed' =>$failed])]);
    }
  }

  public function recurringSetTestData(&$form,$form_state) {
    $testdata = [
      'Key' => 'aa',
      'memberno' => 40054694,
      'name' => 'Thomas Brix',
      'recurringID' => 14678459,
      'orderid' => 484591,
      'amount' => 485,
      'currency' => 'DKK',
      'email' => 'brix@sweetlemon.dk'
    ];
    //{"Key":"testaa","memberno":40075252,"name":"Thomas Brix","recurringID":15908674,"orderid":"test399484591","amount":375,"currency":"DKK","email":"brix@sweetlemon.dk"}
    $form['recurring']['testdata']['#value'] = json_encode($testdata);
    return $form['recurring']['testdata'];
  }

}
