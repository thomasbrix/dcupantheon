<?php

namespace Drupal\dcu_member\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\dcu_navision\Client\NavisionRestClient;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Class BamboraReceiptController.
 */
class BamboraReceiptController extends ControllerBase {

  /**
   * Drupal\Core\Logger\LoggerChannelFactoryInterface definition.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * @var NavisionRestClient $navisionClient
   */
  protected $navisionClient;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->loggerFactory = $container->get('logger.factory');
    $instance->navisionClient = $container->get('dcu_navision.client');
    return $instance;
  }

  /**
   * Dcu_member.receipt.
   *
   * @return string
   *   Return Hello string.
   */
  public function content() {
    $receipt_data = $this->register_bambora_payment();
    if (!$receipt_data) {
      $response = new RedirectResponse(Url::fromRoute('user.page')->toString());
      return $response;
    }
    $build = [
      '#theme' => 'dcu_member_bambora_receipt',
      '#receipt' => $receipt_data,
    ];
    return $build;
  }


  private function register_bambora_payment() {
    //TODO: There is no hash check for epay response.
    $user_id = \Drupal::currentUser()->id();
    $user = User::load($user_id);
    $epay_response = \Drupal::request()->query->all();
    \Drupal::logger('register_bambora_payment_call')->notice('@data', ['@data' => Json::encode($user->toArray())]);
    if ($user->id() != \Drupal::routeMatch()->getParameter('user') || empty($epay_response['txnid'])) {
      // Only logged in user must register own payment. User id is part of callback url.
      \Drupal::logger('register_bambora_payment_call_reject')
        ->error('@data', ['@data' => Json::encode([
          'userid' => $user->id(),
          'paramuid' => \Drupal::routeMatch()->getParameter('user'),
          'epay_response' => $epay_response,
        ])]);
      $response = new RedirectResponse(Url::fromRoute('user.page')->toString());
      $response->send();
      return FALSE;
    }
    $member_id = $user->get('field_memberid')->getString();

    $language = \Drupal::languageManager()->getCurrentLanguage();
    $language_prefix = '/' . $language->getId();

    $is_domestic_card = $epay_response['paymenttype'] == 1;
    $recurring_id = !empty($epay_response['subscriptionid']) ? $epay_response['subscriptionid'] : 0;
    $params =  array (
      'memberno' =>  $member_id,
      'paymentdate' => date('dmY'),
      'transactionid' => $epay_response['orderid'],
      'amount' => $epay_response['amount']/100,
      'changedby' => dcu_member_get_member_update_role(),
      'payment' => 'Kort',
      'domesticcard' => $is_domestic_card,
      'partnerid' => 'Drupal',
      'recurringid' => $recurring_id,
    );
    \Drupal::logger('nav_membership_payment params')->notice('@data', ['@data' => Json::encode($params)]);
    $nav_payment_registered = $this->navisionClient->registerPayment($params);
    if (empty($nav_payment_registered)) {
      \Drupal::messenger()->addMessage($this->t('There was a problem connecting and saving your data. The page you requested is not available at the moment.'), 'error');
      \Drupal::logger('nav_membership_payment fail')->error('@data', ['@data' => Json::encode($nav_payment_registered)]);
      return FALSE;
    }
    try {
      $user->set('field_epay_subscription_id', $recurring_id);
      $user->addRole('dcu_membership');
      $user->save();
    }
    catch (Exception $e) {
      \Drupal::logger('dcu_member')->error('Unable to set save user on receipt callback: @user', ['@user' => $user->id()]);
      \Drupal::logger('nav_membership_payment user_fail')->error('@data', ['@data' => Json::encode($user->toArray())]);
    }
    $membership_type_name = $user->get('field_membership_type')->entity->getName();
    $membership_card_no = dcu_member_get_card_id($user);

    $config = \Drupal::config('dcu_admin.sitesettings');
    $number_of_campsites = $config->get('number_of_dcu_campsites');

    //Generate QRCode.
    $qrcode = '<img src="https://chart.googleapis.com/chart?cht=qr&chs=200x200&chld=L%7C0&chl=https%3a%2f%2fdcu.dk%2fuser-check%2f/' . $member_id . '">';
    $qrcode_expire_date = date("d-m-Y", strtotime('+60 day'));

    //Send user receipt
    $member_name = $user->get('field_first_name')->getString() . ' ' . $user->get('field_last_name')->getString();
    $receipt_params = [
      'to' => $user->getEmail(),
      'subject' => t('Receipt for purchase on dcu.dk'),
      'name' => $member_name,
      'amount' => $epay_response['amount'] / 100,
      'product' => $membership_type_name,
      'order_number' => $epay_response['orderid'],
      'invoice_date' => (string) date('d.m.y'),
      'member_no' => $member_id,
      'membership_card_no' => $membership_card_no,
      'language' => $language->getId(),
      'number_of_campsites' => $number_of_campsites,
      'qrcode' => $qrcode,
      'qrcode_expire_date' => $qrcode_expire_date
    ];
    dcu_member_send_mail('receipt_mail', $receipt_params);
    $subscription_id = array_key_exists('subscriptionid', $epay_response) ? $epay_response['subscriptionid'] : FALSE;
    $receipt_params['uid'] = $user_id;
    $receipt_params['total'] =  $epay_response['amount'] / 100;
    $receipt_params['txnid'] = $epay_response['txnid'];
    $receipt_params['subscriptionid'] = $subscription_id;
    $receipt_params['profile_link'] = $language_prefix . '/user';
    return $receipt_params;
  }
}
