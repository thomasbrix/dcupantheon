<?php
namespace Drupal\dcu_utility\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;

use Guzzle\Http\Client;
use Guzzle\Http\Exception\RequestException;

/**
 * Form handler.
 *
 * @WebformHandler(
 *   id = "dcu_form_handler",
 *   label = @Translation("DCU form handler activity registration forms"),
 *   category = @Translation("DCU form handler"),
 *   description = @Translation("Handle all about forms for activity registration forms"),
 *   cardinality = Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 * )
 */

class DCUActivityRegistrationFormHandler extends WebformHandlerBase {

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    $current_path = \Drupal::service('path.current')->getPath();
    $path_args = explode('/', $current_path);

    if (isset($path_args[1]) && $path_args[1] == 'node' && is_numeric($path_args[2])) {
      $entity = \Drupal::entityTypeManager()->getStorage('node');
      $nid = $path_args[2];
      $node = $entity->load($nid);

      /* Maximum paticipants is set. */
      if (!$node->get('field_max_number_of_participants')->isEmpty()) {
        $tickets_max = $node->get('field_max_number_of_participants')->first()->value;

        $webform_id = $form["#webform_id"];
        $webform = \Drupal\webform\Entity\Webform::load($webform_id);

        $tickets_sold = dcu_utility_sold_tickets($webform, $nid);

        $tickets_left = $tickets_max - $tickets_sold;
        $tickets_wanted = $form_state->getValue('tickets');

        if ($tickets_wanted <= 0) {
          $msg = t('You need to specify the number of participants');
          $form_state->setErrorByName('tickets', $msg);
        }

        if ($tickets_max <= $tickets_sold ) {
          // Sold out - Check if there is a waitlist
          $tickets_waitlist_max = 0;
          if (!$node->get('field_max_participants_waitlist')->isEmpty()) {
            $tickets_waitlist_max = $node->get('field_max_participants_waitlist')
              ->first()->value;
          }
          if (!empty($tickets_waitlist_max) && ($tickets_max + $tickets_waitlist_max) > $tickets_sold) {
            $waitlist_confirmation = t('NB: There are no more tickets available. You have been added to the waitlist for tickets.');
            $form_state->setValue('hiddenmaxparticipantsconfirmation', $waitlist_confirmation);
          }
          else {
            $msg = t('Unfortunately there are no more tickets');
            $form_state->setErrorByName('tickets', $msg);
          }
        }
        else {
          if ($tickets_max < $tickets_sold + $tickets_wanted) {
            $msg = t('Sorry - you want @tickets_wanted tickets, but where are only @tickets_left tickets left', array('@tickets_wanted' => $tickets_wanted, '@tickets_left' => $tickets_left));
            $form_state->setErrorByName('tickets', $msg);
          }
          //Allocated seats.
          $sell = $tickets_sold + 1;
          if ($tickets_wanted == 1) {
            $seats = t('You have been allocated ticket: Ticket-@sell', array('@sell' => $sell));
          }
          else {
            $seats_no = [];
            $seats = t('You have been allocated the following tickets: ');
            for ($i = 1; $i <= $tickets_wanted; $i++) {
              $seats_no[] = t('Ticket-@sell', array('@sell' => $sell));
              $sell++;
            }
            $seats .= implode(', ', $seats_no);
          }
          $form_state->setValue('hiddenmaxparticipantsconfirmation', $seats);
        }
      }
    }
  }

  public function getSummary() {
    return [
      '#markup' => $this->t('When active calculates and handles remaining available tickets on the event'),
    ];
  }

}
