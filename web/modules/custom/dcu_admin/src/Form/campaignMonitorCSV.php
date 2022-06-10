<?php

namespace Drupal\dcu_admin\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class campaignMonitorCSV.
 */
class campaignMonitorCSV extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'campaign_monitor_csv';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['info'] = [
      '#markup' => '<h1>Generation of csv file of all campsites(Others)</h1><p>Hit generate and process will start and generate a file that will be downloaded.</p>'
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate'),
    ];

    return $form;
  }

//  /**
//   * {@inheritdoc}
//   */
//  public function validateForm(array &$form, FormStateInterface $form_state) {
//    foreach ($form_state->getValues() as $key => $value) {
//      // @TODO: Validate fields.
//    }
//    parent::validateForm($form, $form_state);
//  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    //Get array of countries.
    $countries = \Drupal::service('country_manager')->getList();

    $database = \Drupal::database();
    $query = $database->query("select
    n.nid,
    n.title as campsitename,
    adr.field_address_postal_code as zip,
    adr.field_address_country_code as country_code,
    adv.field_advantage_campsite_value as advantage,
    alias.alias as www,
    from_unixtime(n.changed, '%Y-%m-%d') as changed,
    n.uid,
    u.name,
    u.mail,
    COALESCE(p.field_price_list_year_value,'') as price_year,
	  COALESCE(sd.field_season_period_value,'') as season_start_date
    from
    node_field_data n
    left join node__field_address adr on adr.entity_id = n.nid
    left join node__field_advantage_campsite adv on adv.entity_id = n.nid
    left join path_alias as alias on CONCAT('/node/', n.nid ) = alias.path
    left join users_field_data u on u.uid = n.uid
    left join node__field_price_list_year p on p.entity_id = n.nid
    left join node__field_season_period sd on sd.entity_id = n.nid
    where
    n.type = 'campsites' and
    n.status = 1
    order by n.title asc"
    );
    $result = $query->fetchAll();

    $file = "campaign-monitor-file.csv";
    $fh = fopen($file, 'w');
    $fields = [
      'Campsiteurl',
      'Land',
      'Campingplads navn',
      'Fordelsplads',
      'Update date',
      'Postnr',
      'Username',
      'Drupal user emal adresse',
      'Price year',
      'Season start date'
    ];
    fputcsv($fh, $fields);

    if (!empty($result)) {
      foreach ($result as $campsite) {
        $csv_data = [
          'https://dcu.dk' . $campsite->www,
          $countries[$campsite->country_code],
          ltrim($campsite->campsitename),
          $campsite->advantage,
          $campsite->changed,
          $campsite->zip,
          $campsite->name,
          $campsite->mail,
          $campsite->price_year,
          $campsite->season_start_date
        ];
        fputcsv($fh, $csv_data);
      }
    }
    fclose($fh);
    header('Content-Type: ' . mime_content_type($file));
    header('Content-Length: ' . filesize($file));
    header("Content-Transfer-Encoding: Binary");
    header("Content-disposition: attachment; filename=\"" . basename($file) . "\"");
    readfile($file);
    die();
  }
}
