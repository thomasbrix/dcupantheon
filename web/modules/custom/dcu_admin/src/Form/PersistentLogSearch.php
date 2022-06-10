<?php

namespace Drupal\dcu_admin\Form;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\dblog\Controller\DbLogController;
use Drupal\dcu_admin\Controller\PersistentLog;

/**
 * Class PersistentLogSearch.
 */
class PersistentLogSearch extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'persistent_log_search';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form_state->setMethod('get');
    $form_state->setAlwaysProcess(TRUE);
    $form['#cache'] = [
      'max-age' => 0,
    ];
    $searchString = $this->getRequest()->query->get('searchstring') ? $this->getRequest()->query->get('searchstring') : '';
    $channel = $this->getRequest()->query->get('channel') ? $this->getRequest()->query->get('channel') : '';
    $form['searchstring'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Searchstring'),
      '#maxlength' => 64,
      '#size' => 64,
      '#weight' => '0',
      '#default_value' => $searchString, //$form_state->getValue('searchstring')
    ];
    $form['channel'] = [
      '#type' => 'select',
      '#title' => t('Filter on channel'),
      '#options' => ['All', 'dcu_member_log' => 'Member log', 'recurring_payment' => 'Recurring payment'],
      '#default_value' => $channel,
    ];
    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Søg i log'),
    ];

    $form['search_results'] = [
      '#weight' => 100,
    ];

    if ($form_state->getTriggeringElement()) {
      $header = [
        ['data' => t('Log ID'), 'field' => 'wid'],
        ['data' => t('User ID'), 'field' => 'uid'],
        ['data' => t('Dato'), 'field' => 'timestamp','sort' => 'desc'],
        ['data' => t('Type'), 'field' => 'type'],
        ['data' => t('Channel'), 'field' => 'channel'],
        ['data' => t('Data')],
      ];

      $database = \Drupal::database();
      $query = $database->select('dblog_persistent', 'log');
      $table_sort = $query->extend('Drupal\Core\Database\Query\TableSortExtender')->orderByHeader($header);
      $table_sort->fields('log');
      if ($searchString) {
        $table_sort->condition('log.variables', "%" . $searchString . "%", 'LIKE');
      }
      if ($channel) {
        $table_sort->condition('log.channel', $channel);
      }
      $pager = $table_sort->extend('Drupal\Core\Database\Query\PagerSelectExtender')->limit(50);
      $results = $pager->execute();
      $rows = [];
      $persistentLog = new PersistentLog();

      foreach($results as $row) {
        $message = $persistentLog->formatMessage($row);
        $link_url = Url::fromRoute('dcu_admin.persistent_log_proximity', ['wid' => $row->wid]);
        $link_url->setOptions([
          'attributes' => [
            'class' => ['use-ajax'],
            'data-dialog-type' => 'modal',
            'data-dialog-options' => Json::encode(['width' => '80%']),
          ]
        ]);
        $rows[] = [Link::fromTextAndUrl(t('View ' . $row->wid), $link_url)->toString(), $row->uid, date('m-d-Y H:i:s', $row->timestamp), $row->type, $row->channel, $message];
      }

      $form['search_results']['table'] = [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => t('No log lines found'),
      ];
      $form['pager'] = array(
        '#type' => 'pager',
        '#weight' => '110',
      );
      $form['form_build_id']['#access'] = FALSE;
      $form['form_token']['#access'] = FALSE;
      $form['form_id']['#access'] = FALSE;
    }
    return $form;
  }


  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild(TRUE);
  }

}
