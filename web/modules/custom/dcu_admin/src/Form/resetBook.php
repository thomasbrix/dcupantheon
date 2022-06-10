<?php

namespace Drupal\dcu_admin\Form;

use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class resetBook.
 */
class resetBook extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'reset_book';
  }

  /**
   * Form for dcu admins to reset ready for book and marketing packages on campsites.
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $conn = Database::getConnection();
    $num_ready = $conn->select('node__field_ready_for_book', 'r')
      ->fields('r')
      ->condition('field_ready_for_book_value', 1, '=')
      ->countQuery()
      ->execute()
      ->fetchField();

    $form['desccription'] = [
      '#markup' => '<p>' . $this->t('
This will reset fields on ALL campsites - and CANT BE UNDONE<br/><br/>
The following fields will be reset:<br/>
- Ready for book<br/>
- Marketing products on campsites.<br/>
- Put on top search<br/>
- Bought ad in the book<br/>
<br/>
There are currently @count marked with Ready for book', ['@count' => $num_ready]) . '</p>',
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reset now - can not be undone!'),
    ];
    return $form;
  }


  /**
   * Unsets values for ready for book and marketing products on campsites.
   *
   * Field value for readu for book is simple int value 0 or 1 so for performance
   * reasons values are being set directly in the tables.
   *
   * Clears all caches.
   *
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    ini_set('memory_limit', '1048M');
    ini_set('max_execution_time', 600);
    $conn = Database::getConnection();
    // Truncate ready for book, so all campsites are being reset.
    $numBook = $conn->update('node__field_ready_for_book')
      ->fields([
        'field_ready_for_book_value' => 0,
      ])
      ->condition('field_ready_for_book_value', 1, '=')
      ->execute();

    $conn->update('node_revision__field_ready_for_book')
      ->fields([
        'field_ready_for_book_value' => 0,
      ])
      ->condition('field_ready_for_book_value', 1, '=')
      ->execute();

    // Reset all "Marketing products", "Put on top search" and "Purchased advertisment"
    // entity reference field.
    $entity = \Drupal::entityTypeManager()->getStorage('node');
    $query = $entity->getQuery();
    $query->condition('status', 1);
    $query->condition('type', ['dcu_campsite', 'campsites'], 'in');
    $query->sort('changed', 'DESC');
    $ids = $query->execute();

    $campsiteNodes = $entity->loadMultiple($ids);
    foreach ($campsiteNodes as $campsiteNode) {
      $campsiteNode->set('field_marketing_products', NULL);
      $campsiteNode->set('field_purchased_advertisment', NULL);
      $campsiteNode->set('field_put_on_top', NULL);
      $campsiteNode->save();
    }

    if (!empty($numBook)) {
      \Drupal::messenger()
        ->addMessage($this->t('Field ready for book was reset on @count campsite nodes', ['@count' => $numBook]), 'status');
    }
    if (!empty($campsiteNodes)) {
      \Drupal::messenger()->addMessage($this->t('The fields have been reset on @count campsite nodes', ['@count' => count($campsiteNodes)]), 'status');
    }
    if (!empty($numBook) || !empty($campsiteNodes)) {
      drupal_flush_all_caches();
      \Drupal::messenger()->addMessage($this->t('All caches cleared'), 'status');
    }
  }

}
