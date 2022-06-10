<?php

namespace Drupal\dcu_admin\Plugin\AdvancedQueue\JobType;

use Drupal\advancedqueue\Job;
use Drupal\advancedqueue\JobResult;
use Drupal\advancedqueue\Plugin\AdvancedQueue\JobType\JobTypeBase;
use Drupal\Component\Serialization\Json;

/**
 * @AdvancedQueueJobType(
 *   id = "dcu_recurring_message_mail",
 *   label = @Translation("Recurring notification email"),
 *   max_retries = 2,
 *   retry_delay = 60,
 * )
 */
class RecurringMailQueueJob extends JobTypeBase {

  /**
   * {@inheritdoc}
   */
  public function process(Job $job) {
    try {
      $payload = $job->getPayload();
      $name = $payload['name'];
      $email = $payload['email'];
      if (empty($payload['email'])) {
        \Drupal::logger('dcu_admin_recurring_mail_error')->error('@data', ['@data' => Json::encode(['message' => 'Error in queue item dcu_recurring_message_mail email missing item', 'failed' => $payload])]);
        return JobResult::failure('Recurring email not valid.', 0);
      }
      if (!\Drupal::service('email.validator')->isValid($email)) {
        \Drupal::logger('dcu_admin_recurring_mail_error')->error('@data', ['@data' => Json::encode(['message' => 'Error in queue item dcu_recurring_message_mail email not valid', 'failed' => $payload])]);
        return JobResult::failure('Recurring email not valid.', 0);
      }
      $mailParams = [
        'subject' => t('Dit medlemskab hos DCU bliver fornyet'),
        'to' => $email,
        'language' => 'da',
        'name' => $name,
      ];
      $config = \Drupal::config('dcu_admin.recurring_settings');
      if (!$config->get('process_payment_queue')) {
        // Do not process recurring.
        \Drupal::logger('dcu_admin_recurring_mail_test')->notice('Removed item from dcu_recurring_message_mail queue: @info', ['@info' => print_r($payload, 1)]);
        return JobResult::success('Mail item was removed from queue. Mail: ' . $email);
      }
      if (dcu_member_send_mail('recurring_info', $mailParams)) {
        \Drupal::logger('dcu_admin_recurring_mail_sent')->notice('@data', ['@data' => Json::encode(['message' => 'Processed dcu_recurring_message_mail queue item', 'item' => $payload])]);
        return JobResult::success('Mail was sent to: ' . $email);
      }
      else {
        \Drupal::logger('dcu_admin_recurring_mail_error')->error('@data', ['@data' => Json::encode(['message' => 'Error sending mail in dcu_recurring_message_mail queue item', 'failed' => $payload])]);
      }
      return JobResult::failure('Failed sending email to: ' . $email);
    }
    catch (\Exception $e) {
      return JobResult::failure($e->getMessage());
    }
  }

}
