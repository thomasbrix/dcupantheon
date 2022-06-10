<?php
/**
 * @file
 * Contains \Drupal\dcu_admin\Plugin\QueueWorker\RecurringPaymentQueueWorker.
 */

namespace Drupal\dcu_admin\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;

/**
 * Processes recurring payment queue to send payment transaction request to Bambora.
 * Queue is filled by dcu_admin form.
 *
 * @QueueWorker(
 *   id = "dcu_recurring_payment",
 *   title = @Translation("DCU Admin: Recurring payment Queue worker"),
 *   cron = {"time" = 120}
 * )
 */
class RecurringPaymentQueueWorker extends QueueWorkerBase {

  /**
   * TBX: This worker is deprecated. Worker has been implemented with advanced queue instead
   * Check RecurringPaymentQueueJob
   *
   * {@inheritdoc}
   */
  public function processItem($item) {

    $name = $item['name'];
    $email = $item['email'];
    if (empty($item['email'])) {
      \Drupal::logger('dcu_admin_recurring_payment_error')->error('Error in queue item dcu_recurring_payment email missing item: @info', ['@info' => print_r($item, 1)]);
      return;
    }
    $paymentResult = $this->bamboraPaymentRequest($item);
    if ($paymentResult['status'] == 'ok') {
      \Drupal::logger('dcu_admin_recurring_payment_done')->notice('Processed dcu_recurring_payment queue item: @info . Result: @result', ['@info' => print_r($item, 1), '@result' => print_r($paymentResult, 1)]);
    }
    else {
      \Drupal::logger('dcu_admin_recurring_payment_error')->error('Error processing payment in dcu_recurring_payment queue item: @info . Result: @result', ['@info' => print_r($item, 1), '@result' => print_r($paymentResult, 1)]);
    }
  }

  private function bamboraPaymentRequest($paymentData) {
    return ['status' => 'ok'];
  }

}
