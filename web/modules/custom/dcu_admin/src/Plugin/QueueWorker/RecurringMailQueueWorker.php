<?php
/**
 * @file
 * Contains \Drupal\dcu_admin\Plugin\QueueWorker\RecurringMailQueueWorker.
 */

namespace Drupal\dcu_admin\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;

/**
 * Processes Email notification queue to send Recurring payment notification emails to members.
 * Queue is filled by dcu_admin form.
 *
 * @QueueWorker(
 *   id = "dcu_recurring_message_mail",
 *   title = @Translation("DCU Admin: Recurring notification email Queue worker"),
 *   cron = {"time" = 120}
 * )
 */
class RecurringMailQueueWorker extends QueueWorkerBase {

  /**
   * TBX: This worker is deprecated. Worker has been implemented with advanced queue instead
   * Check RecurringMailQueueJob
   * {@inheritdoc}
   */
  public function processItem($item) {

    $name = $item['name'];
    $email = $item['email'];
    if (empty($item['email'])) {
      \Drupal::logger('dcu_admin_recurring_mail_error')->error('Error in queue item dcu_recurring_message_mail email missing item: @info', ['@info' => print_r($item, 1)]);
      return;
    }
    $mailParams = [
      'subject' => t('Dit medlemskab hos DCU bliver fornyet'),
      'to' => $email,
      'language' => 'da',
      'name' => $name,
    ];
    if (dcu_member_send_mail('recurring_info', $mailParams)) {
      \Drupal::logger('dcu_admin_recurring_mail_sent')->notice('Processed dcu_recurring_message_mail queue item: @info', ['@info' => print_r($item, 1)]);
    }
    else {
      \Drupal::logger('dcu_admin_recurring_mail_error')->error('Error sending mail in dcu_recurring_message_mail queue item: @info', ['@info' => print_r($item, 1)]);
    }
  }

}
