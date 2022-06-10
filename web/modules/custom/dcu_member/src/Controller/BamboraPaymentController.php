<?php

namespace Drupal\dcu_member\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dcu_navision\Client\NavisionRestClient;
use Drupal\user\Entity\User;
use SoapClient;
use Symfony\Component\DependencyInjection\ContainerInterface;

class BamboraPaymentController extends ControllerBase {
  var $merchantNumber;
  var $soapClient;

  /**
   * @var NavisionRestClient $navisionClient
   */
  protected $navisionClient;


  function __construct($navisionClient) {
    $environment = $navisionClient->getEnvironment();
    $merchantNumber = ($environment == 'PRODUCTION') ? 2765640 : 8029980;
    $this->merchantNumber = $merchantNumber;
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
   * Returns a render-able array for a test page.
   */
  public function content() {
    $build = [
      '#theme' => 'dcu_member_bambora_payment',
      '#payment' => $this->getBamboraPaymentData(),
    ];
    $build['#attached']['library'][] = 'dcu_member/bambora.checkout';
    return $build;
  }

  public function soapConnect() {
    $client = new SoapClient('https://ssl.ditonlinebetalingssystem.dk/remote/subscription.asmx?WSDL');
    if (!$client) {
      return FALSE;
    }
    $this->soapClient = $client;
    return TRUE;
  }



  /**
   * @return array
   */
  public function getBamboraPaymentData() {
    //TODO: tbx test new client
    $user_id = \Drupal::currentUser()->id();
    $current_user = User::load($user_id);
    $host = \Drupal::request()->getSchemeAndHttpHost();
    $member_id = $current_user->get('field_memberid')->getString();
    $nav_member_data = $this->navisionClient->getMemberData($member_id);
    if (empty($nav_member_data)) {
      \Drupal::logger('dcu_member')->error('Got no data from Navision for memberid @memberid', ['@memberid' => $member_id]);
      \Drupal::messenger()->addMessage($this->t('Could not connect. Try again or contact dcu memberservice'), 'error');
      return FALSE;
    }
    //Doc: http://epay.bambora.com/da/specifikation#453
    //Language: da = 1, en = 2, de = 7.
    $bambora_language_options = [
      'da' => 1,
      'en' => 2,
      'de' => 7
    ];
    $language = \Drupal::languageManager()->getCurrentLanguage();
    $bambora_language = $bambora_language_options[$language->getId()];
    $parameters = array(
      'merchantnumber' => $this->merchantNumber,
      'amount' => intval($nav_member_data->balance)*100, // We need the amount in øre
      'currency' => 'DKK',
      'windowstate'=> '3',
      'paymentcollection'=> '1',
      'accepturl' => $host . '/' . $language->getId() . '/signup/receipt/' . $user_id,
      'ordertext' => $member_id,
      'language_user' => $bambora_language,
      'ownreceipt' => 1,
      'subscription' => 1,
      'subscriptiontype' => 'recurring',
    );
    return $parameters;
  }
  public function authorizeRecurringPayment($data) {
    $memberPaymentData = $this->getBamboraRecurringPaymentData($data);
    if (empty($this->soapClient)) {
      // TODO: TBX LOG;
      return FALSE;
    }
    return $this->soapClient->authorize($memberPaymentData);
  }

  public function getBamboraRecurringPaymentData($data) {
    $epayParams = array();
    $epayParams['merchantnumber'] = $this->merchantNumber;
    $epayParams['subscriptionid'] = $data['recurringID'];
    $epayParams['orderid'] = $data['orderid'];
    $epayParams['amount'] = intval($data['amount'])*100;
    $epayParams['currency'] = "208";
    $epayParams['instantcapture'] = "1";
    $epayParams['fraud'] = "0";
    $epayParams['transactionid'] = "-1";
    $epayParams['pbsresponse'] = "-1";
    $epayParams['epayresponse'] = "-1";
    $epayParams['description'] = $data['memberno'];
    return $epayParams;
  }

}
