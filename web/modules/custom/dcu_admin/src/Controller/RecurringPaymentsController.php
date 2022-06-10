<?php

namespace Drupal\dcu_admin\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dcu_navision\Client\NavisionRestClient;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class RecurringPaymentsController.
 */
class RecurringPaymentsController extends ControllerBase {
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
   * Content.
   *
   * @return array
   *   Return Recurring payments administration Forms.
   */
  public function content() {
    $recurringMembers = $this->navisionClient->getRecurring();
    if (!$recurringMembers) {
      \Drupal::messenger()->addMessage('There was an error connecting to Recurring member endpoint on Navision', 'error');
      $numOfRecMembers = 0;
    }
    else {
      $numOfRecMembers = count($recurringMembers);
    }
    $recurringInfo = t('There are currently @membercnt members subscribed to Recurring payment.', ['@membercnt' => $numOfRecMembers]);
    $elements = [];
    $elements['header'] = [
      '#type' => 'markup',
      '#markup' => '<h2>' . t('Administer and process recurring payments' ) . '</h2>' . t('Members subscribed via recurring payments are fetched from Navision.')  . $recurringInfo,
    ];
    $recurringMailForm = \Drupal::formBuilder()->getForm('Drupal\dcu_admin\Form\RecurringMail');
    $recurringSettingForm = \Drupal::formBuilder()->getForm('Drupal\dcu_admin\Form\RecurringProcessSetting');
    $recurringProcessForm = \Drupal::formBuilder()->getForm('Drupal\dcu_admin\Form\RecurringProcessPayment');

    $elements['form_mail'] = $recurringMailForm;
    $elements['form_mail']['#weight'] = 0;
    $elements['form_process'] = $recurringProcessForm;
    $elements['form_process']['#weight'] = 1;
    $elements['form_setting'] = $recurringSettingForm;
    $elements['form_setting']['#weight'] = 2;
    return $elements;
  }

}
