<?php

namespace Drupal\dcu_admin\Plugin\AdvancedQueue\JobType;

use Drupal\advancedqueue\Entity\Queue;
use Drupal\advancedqueue\Job;
use Drupal\advancedqueue\JobResult;
use Drupal\advancedqueue\Plugin\AdvancedQueue\JobType\JobTypeBase;
use Drupal\Component\Serialization\Json;
use Drupal\dcu_member\Controller\BamboraPaymentController;
use Drupal\dcu_navision\Client\NavisionRestClient;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @AdvancedQueueJobType(
 *   id = "dcu_recurring_process_payment",
 *   label = @Translation("Process recurring payment"),
 *   max_retries = 2,
 *   retry_delay = 60,
 * )
 */
class RecurringPaymentQueueJob extends JobTypeBase {

  /**
   * @var NavisionRestClient $navisionClient
   */
  protected $navisionClient;

  /**
   * @param \Drupal\dcu_navision\Client $navisionClient
   *
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
  public function process(Job $job) {
    try {
      $payload = $job->getPayload();
      \Drupal::logger('dcu_admin_recurring_payment_process')->notice('@data', ['@data' => Json::encode(['message' => 'Processing recurring payment for member', 'item' => $payload])]);
      if (empty($payload['memberno']) || empty($payload['recurringID'])) {
        \Drupal::logger('dcu_admin_recurring_payment_error')->error('@data', ['@data' => Json::encode(['message' => 'Missing data', 'item' => $payload])]);
        return JobResult::failure('Missing data in item: ' .  print_r($payload, 1), 0);
      }
      $paymentResult = $this->processPaymentRequest($payload);
      if ($paymentResult['success']) {
        \Drupal::logger('dcu_admin_recurring_payment_success')->notice('@data', ['@data' => Json::encode($paymentResult)]);
        return JobResult::success('Successful payment transaction. Result: ' . print_r($paymentResult, 1));
      }
      $errorMessage = 'Error processing recurring payment in dcu_recurring_process_payment queue';
      \Drupal::logger('dcu_admin_recurring_payment_error')->error('@data', ['@data' => Json::encode(['message' => $errorMessage, 'payload' => $payload, 'result' => $paymentResult])]);
      if (!$paymentResult['retry']) {
        return JobResult::failure('Failed Payment transaction: ' .  print_r($paymentResult, 1), 0);
      }
      return JobResult::failure('Failed Payment transaction: ' .  print_r($paymentResult, 1));
    }
    catch (\Exception $e) {
      \Drupal::logger('dcu_admin_recurring_payment_error')->error('@data', ['@data' => Json::encode(['message' => $e->getMessage(), 'payload' => $payload])]);
      return JobResult::failure($e->getMessage());
    }
  }

  private function processPaymentRequest($paymentData) {
    if (!$navMemberData = $this->navisionClient->getMemberData($paymentData['memberno'])) {
      return ['success' => FALSE, 'retry' => TRUE, 'result' => ['message' => 'Error getting memberdata from Navision memberno:' . $paymentData['memberno']]];
    };
    if ($navMemberData->balance <= 0) {
      return [
        'success' => TRUE,
        'result' => ['message' => 'Member balance not positive', 'balance' => $navMemberData->balance, 'memberno' => $paymentData['memberno']],
      ];
    }
    $config = \Drupal::config('dcu_admin.recurring_settings');
    if (!$config->get('process_payment_queue')) {
      // Do not process recurring.
      return ['success' => TRUE, 'result' => ['message' => 'Payment test item removed from queue']];
    }
    // Process payment

    $bamboraClient = new BamboraPaymentController();
    if (!$bamboraClient->soapConnect()) {
      return ['success' => FALSE, 'retry' => TRUE, 'result' => ['message' => 'Error connecting to Bambora soap client']];
    }
    if (empty($transactionResult = $bamboraClient->authorizeRecurringPayment($paymentData))) {
      return ['success' => FALSE, 'retry' => TRUE, 'result' => ['message' => 'Empty reply from Bambora']];
    }
    // IF not Authorize payment
    if (!$transactionResult->authorizeResult) {
      // There was an error capturing recurring payment from Bamborra.
      $recipient = $paymentData['email'];
      $mailParams = [
        'subject' => t('Betaling af dit DCU medlemskab'),
        'to' => $recipient,
        'language' => 'da',
        'name' => $paymentData['name'],
      ];
      dcu_member_send_mail('recurring_reject', $mailParams);
      return ['success' => FALSE, 'retry' => FALSE, 'result' => ['message' => $transactionResult]];
    }
    // Register payment in Nav
    $navParams =  [
      'memberno' =>  $paymentData['memberno'],
      'paymentdate' => date('dmY'),
      'transactionid' => $transactionResult->transactionid,
      'amount' => $paymentData['amount'],
      'changedby' => 'Recurring',
      'payment' => 'Kort',
      'domesticcard' => TRUE,
      'partnerid' => 'Drupal',
      'recurringid' => $paymentData['recurringID'],
    ];
    $navPaymentRegistered = $this->navisionClient->registerPayment($navParams);
    if (!$navPaymentRegistered) {
      // Transaction successful but Nav register failed. Add a job to try again later.
      $recurQueue = Queue::load('recurring_receipt_mail_retry');
      $job = Job::create('recurring_receipt_reprocess', $navParams);
      $recurQueue->enqueueJob($job);
      \Drupal::logger('dcu_admin_nav_recurring_payment_register_fail')->error('@data', ['@data' => Json::encode($navParams)]);
      return ['success' => TRUE, 'result' => ['message' => 'Payment successful. Nav registration postponed.', 'result' => $transactionResult]];
    }
    $recipient = $paymentData['email'];
    $mailParams = [
      'subject' => t('Dit medlemskab hos DCU er blevet fornyet'),
      'to' => $recipient,
      'language' => 'da',
      'name' => $paymentData['name'],
    ];
    if (dcu_member_send_mail('recurring_receipt', $mailParams)) {
      \Drupal::logger('dcu_admin_recurring_payment_receipt')->notice('Recurring payment Email receipt send to @mail', ['@mail' => $recipient]);
    }
    else {
      \Drupal::logger('dcu_admin_recurring_payment_receipt')->error('Recurring payment Email receipt failed to send to @mail', ['@mail' => $recipient]);
    }
    return ['success' => TRUE, 'result' => $transactionResult];
  }
}
