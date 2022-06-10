<?php

namespace Drupal\dcu_admin\Plugin\AdvancedQueue\JobType;

use Drupal\advancedqueue\Job;
use Drupal\advancedqueue\JobResult;
use Drupal\advancedqueue\Plugin\AdvancedQueue\JobType\JobTypeBase;
use Drupal\Component\Serialization\Json;

/**
 * @AdvancedQueueJobType(
 *   id = "dcu_member_message_mail",
 *   label = @Translation("Member notification emails"),
 *   max_retries = 2,
 *   retry_delay = 60,
 * )
 */
class MemberMailQueueJob extends JobTypeBase {

  /**
   * {@inheritdoc}
   */
  public function process(Job $job) {
    try {
      $payload = $job->getPayload();
      if (empty($payload['email'])) {
        \Drupal::logger('dcu_admin_member_mail_error')->error('@data', ['@data' => Json::encode(['message' => 'Error in queue item dcu_member_message_mail email missing item', 'failed' => $payload])]);
        return JobResult::failure('Member email not valid.', 0);
      }
      $email = $payload['email'];
      if (!\Drupal::service('email.validator')->isValid($email)) {
        \Drupal::logger('dcu_admin_member_mail_error')->error('@data', ['@data' => Json::encode(['message' => 'Error in queue item dcu_member_message_mail email not valid', 'failed' => $payload])]);
        return JobResult::failure('Member email not valid.', 0);
      }
      $mailParams = [
        'subject' => $payload['subject'],
        'to' => $email,
        'name' =>  $payload['name'],
        'membertype' => $payload['membertype'],
        'memberno' => $payload['memberno'],
        'message' => $payload['message'],
        'language' => 'da',
      ];
      if (dcu_member_send_mail('member_mail', $mailParams)) {
        \Drupal::logger('dcu_admin_member_mail_sent')->notice('@data', ['@data' => Json::encode(['message' => 'Processed dcu_member_message_mail queue item', 'item' => $payload])]);
        return JobResult::success('Mail was sent to: ' . $email);
      }
      else {
        \Drupal::logger('dcu_admin_member_mail_error')->error('@data', ['@data' => Json::encode(['message' => 'Error sending mail in dcu_member_message_mail queue item', 'failed' => $payload])]);
      }
      return JobResult::failure('Failed sending email to: ' . $email);
    }
    catch (\Exception $e) {
      return JobResult::failure($e->getMessage());
    }
  }

}
