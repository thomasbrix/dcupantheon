<?php

namespace Drupal\dcu_admin\Form;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;


/**
 * Class bookExport.
 */
class bookExport extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'book_export';
  }

  /**
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
      '#markup' => '<p>' . $this->t('Download campsite data as csv for Book.<br/> There are currently @count campsites marked with Ready for book.', ['@count' => $num_ready]) . '</p>',
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generer og download csv fil'),
    ];
    $files = \Drupal::service('file_system')->scanDirectory('private://book_export', '/.*.csv/');
    rsort($files);

    if (!empty($files)) {
      foreach ($files as $file) {
        $fileRows[] =  [
          'filename' => $file->filename,
          'url' => [
            'data' => new FormattableMarkup('<a href=":link">@name</a>', [':link' => Url::fromUri(file_create_url($file->uri))->toString(), '@name' => $file->filename])
          ],
        ];
      }

      $form['existingfiles'] = [
        '#markup' => '<p><h2>' . $this->t('Previously generated Book export files') . '</h2></p>',
      ];
      $form['files'] = [
        '#prefix' => '<p>',
        '#type' => 'table',
        '#header' => [$this->t('Filename'), $this->t('Download')],
        '#rows' => $fileRows,
        '#suffix' => '</p>',
      ];
    }


    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    ini_set('memory_limit', '1048M');
    ini_set('max_execution_time', 600);
    $first = TRUE;
    $entity = \Drupal::entityTypeManager()->getStorage('node');
    $query = $entity->getQuery();
    $query->condition('status', 1);
    $query->condition('type', ['dcu_campsite', 'campsites'], 'in');
    $query->condition('field_ready_for_book', 1, '=');
    $query->sort('changed', 'DESC');
    $ids = $query->execute();
    $campsiteNodes = $entity->loadMultiple($ids);
    $campsites = [];
    $filename = 'book_campsites_export_' . date('Y-m-d_His') . '.csv';
    $directory = \Drupal::service('file_system')->realpath('private://book_export');
    $file = $directory . '/' . $filename;

    if (!$fh = fopen($file, 'w')) {
      \Drupal::messenger()->addMessage('There was an error opening cvs file for writing', 'error');
      return;
    }
    $cardTypesdefinition = [];
    $allowedValues = [];
    $facilityKeys = [];
    foreach ($campsiteNodes as $node) {
      if ($node->hasField('field_acceptable_card_types')) {
        $cardTypesdefinition = $node->field_acceptable_card_types->getFieldDefinition();
        $allowedValues = $cardTypesdefinition->getSetting('allowed_values');
        $facilityTerms =\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'facilities']);
        foreach ($facilityTerms as $term) {
          $facilityKeys[] = $term->get('field_icon')->getString();
        }
        break;
      }
    }

    foreach ($campsiteNodes as $node) {
      $camp = [];
      $camp['title'] = $node->getTitle();
      $address = $node->get('field_address')->first();
      $camp['city'] = $address->locality;
      $camp['address'] = $this->bookExportFormatAddress($address);
      $camp['country'] = $address->country_code;
      $regions = $node->get('field_country_region');
      $regionLevel = [
        1 => '',
        2 => '',
        3 => '',
      ];
      foreach ($regions->referencedEntities() as $region) {
        $parents = \Drupal::service('entity_type.manager')->getStorage("taxonomy_term")->loadAllParents($region->id());
        $parentCount = count($parents);
        if ($parentCount == 1) {
          $regionLevel[1] = $region->getName();
        }
        elseif($parentCount == 2) {
          $regionLevel[2] = $region->getName();
        }
        elseif($parentCount == 3) {
          $regionLevel[3] = $region->getName();
        }
      }

      $camp['region_level_3'] = $regionLevel[3];
      $camp['region_level_2'] = $regionLevel[2];
      $camp['rating'] = !empty($node->get('field_number_of_stars')->first()) ? $node->get('field_number_of_stars')->first()->value : '';
      $website = !empty($node->get('field_www')->first()) ? $node->get('field_www')->first() : '';
      $camp['web'] = !empty($website) ? $website->uri : '';
      $camp['benefitcamp'] = !empty($node->get('field_advantage_campsite')->first()) ? "Ja" : "Nej";
      $camp['partnercamp'] = $node->bundle() == 'dcu_campsite' ? "Ja" : "Nej";

      $startdate = $node->hasField('field_season_period') && !empty($node->get('field_season_period')->value) ? \Drupal::service('date.formatter')->format(strtotime($node->get('field_season_period')->value), 'custom', 'F', NULL, 'da' ) : '';
      $enddate = $node->hasField('field_season_period') && !empty($node->get('field_season_period')->end_value) ? \Drupal::service('date.formatter')->format(strtotime($node->get('field_season_period')->end_value), 'custom', 'F', NULL, 'da' ) : '';
      $camp['season'] = $startdate . ' - ' . $enddate;

      $camp['children_from'] = $node->hasField('field_children_age_from') ? $node->get('field_children_age_from')->getString() : '-';
      $camp['children_to'] = $node->hasField('field_children_age_to') ? $node->get('field_children_age_to')->getString() : '-';

      $camp['youngsters_from'] = $node->hasField('field_youngster_age_from') ? $node->get('field_youngster_age_from')->getString() : '-';
      $camp['youngsters_to'] = $node->hasField('field_youngster_age_to') ? $node->get('field_youngster_age_to')->getString() : '-';

      $camp['discount'] = $node->hasField('field_discount_description') ? strip_tags($node->get('field_discount_description')->getString()) : '-';

      $geoLocation = $node->get('field_geo_location')->first();
      $camp['geolocation_lat'] = !empty($geoLocation->lat) ? $geoLocation->lat : NULL;
      $camp['geolocation_lng'] = !empty($geoLocation->lng) ? $geoLocation->lng : NULL;
      $camp['email'] = !$node->get('field_email')->isEmpty() ? $node->get('field_email')->first()->getString() : NULL;
      $desc = $node->get('field_description')->first();
      $camp['summary'] = !empty($desc) ? strip_tags($desc->value) : '';

      $camp['købt annonce i bog'] = $node->hasField('field_purchased_advertisment') && empty($node->get('field_purchased_advertisment')->first()->getString()) ? 'Nej' : 'Ja';

      $camp['klarmeldt til bog'] = empty($node->get('field_ready_for_book')->first()->getString()) ? 'Nej' : 'Ja';
      $camp['directions'] = '';

      $camp['field_price_list_year'] = $node->hasField('field_price_list_year') && !empty($node->get('field_price_list_year')->getString()) ? $node->get('field_price_list_year')->getString() : '';

      $currencyTerm = $node->hasField('field_price_currency') ? $node->get('field_price_currency')->entity : NULL;

      $camp['field_price_currency'] = !empty($currencyTerm) ? $currencyTerm->getName() : '';
      $camp['field_price_pitch_fee'] = $node->hasField('field_price_pitch_fee') && !empty($node->get('field_price_pitch_fee')->getString()) ? number_format($node->get('field_price_pitch_fee')->getString(), 2, ',', '.') : '';
      $camp['field_price_motorcaravan'] = $node->hasField('field_price_motorcaravan') && !empty($node->get('field_price_motorcaravan')->getString()) ? number_format($node->get('field_price_motorcaravan')->getString(), 2, ',', '.') : '';
      $camp['field_price_car'] = $node->hasField('field_price_car') && !empty($node->get('field_price_car')->getString()) ?  number_format($node->get('field_price_car')->getString(), 2, ',', '.') : '';
      $camp['field_price_child'] = $node->hasField('field_price_child') && !empty($node->get('field_price_child')->getString()) ? number_format($node->get('field_price_child')->getString(), 2, ',', '.') : '';
      $camp['field_price_caravan'] = $node->hasField('field_price_caravan') && !empty($node->get('field_price_caravan')->getString()) ? number_format($node->get('field_price_caravan')->getString(), 2, ',', '.') : '';
      $camp['field_price_power_outlet'] = $node->hasField('field_price_power_outlet') && !empty($node->get('field_price_power_outlet')->getString()) ? number_format($node->get('field_price_power_outlet')->getString(), 2, ',', '.') : '';
      $camp['field_price_horse'] = $node->hasField('field_price_horse') && !empty($node->get('field_price_horse')->getString()) ? number_format($node->get('field_price_horse')->getString(), 2, ',', '.') : '';
      $camp['field_price_dog'] = $node->hasField('field_price_dog') && !empty($node->get('field_price_dog')->getString()) ? number_format($node->get('field_price_dog')->getString(), 2, ',', '.') : '';
      $camp['field_price_environmental_fee'] = $node->hasField('field_price_environmental_fee') && !empty($node->get('field_price_environmental_fee')->getString()) ? number_format($node->get('field_price_environmental_fee')->getString(), 2, ',', '.') : '';
      $camp['field_price_motorcycle'] = $node->hasField('field_price_motorcycle') && !empty($node->get('field_price_motorcycle')->getString()) ? number_format($node->get('field_price_motorcycle')->getString(), 2, ',', '.') : '';
      $camp['field_price_tent'] = $node->hasField('field_price_tent') && !empty($node->get('field_price_tent')->getString()) ? number_format($node->get('field_price_tent')->getString(), 2, ',', '.') : '';
      $camp['field_price_young'] = $node->hasField('field_price_young') && !empty($node->get('field_price_young')->getString()) ? number_format($node->get('field_price_young')->getString(), 2, ',', '.') : '';
      $camp['field_price_adult'] = $node->hasField('field_price_adult') && !empty($node->get('field_price_adult')->getString()) ? number_format($node->get('field_price_adult')->getString(), 2, ',', '.') : '';
      $camp['field_price_comments'] = $node->hasField('field_price_comments') && !empty($node->get('field_price_comments')->getString()) ? $node->get('field_price_comments')->getString() : '';

      $camp['phone'] = !$node->get('field_phone')->isEmpty() ? $node->get('field_phone')->first()->getString() : NULL;
      $images = $this->bookExportGalleryImages($node);
      $camp['slideshow_urls'] = implode(',', $images);
      foreach ($allowedValues as $value) {
        $camp['credit_card_' . strtolower($value)] = 0;
      }
      if ($node->hasField('field_acceptable_card_types')) {
        foreach ($node->get('field_acceptable_card_types')->getValue() as $selectedCreditCard) {
          $camp['credit_card_' . strtolower($selectedCreditCard['value'])] = 1;
        }
      }
      foreach ($facilityKeys as $key) {
        $camp[$key] = 0;
      }
      $facilities = $node->hasField('field_facilities') ? $node->get('field_facilities') : NULL;
      if (!empty($facilities)) {
        foreach ($facilities as $facility) {
          $facilityTerm = $facility->entity;
          $camp[$facilityTerm->get('field_icon')->getString()] = 1;
        }
      }
      $camp['url_alias'] = $node->toUrl()->setAbsolute()->toString();
      $camp['field_google_rating'] = !empty($node->get('field_google_rating')->getString()) ? number_format($node->get('field_google_rating')->getString(), 2, ',', '.') : '';
      $camp['field_google_ratings_total'] = !empty($node->get('field_google_ratings_total')->getString()) ? $node->get('field_google_ratings_total')->getString() : '';
      $campsites[] = $camp;
      if ($first) {
        fputcsv($fh, array_keys($camp));
        $first = FALSE;
      }
      fputcsv($fh, $camp);
    }
    fclose($fh);

    $response = new BinaryFileResponse($file);
    $response->headers->set('Content-type', mime_content_type($file));
    $disposition = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);
    $response->headers->set('Content-Disposition', $disposition);
    $response->headers->set('Content-Transfer-Encoding', 'binary');
    $response->headers->set('Content-length', filesize($file));
    $form_state->setResponse($response);
    \Drupal::messenger()->addMessage('Book csv file generated and should download automatically', 'status');
  }


  function bookExportGalleryImages($node) {
    $images = [];
    $fieldName = $node->bundle() == 'campsites' ? 'field_gallery_images' : 'field_gallery';
    if (!$node->hasField($fieldName)) {
      return [];
    }
    $mediaList = $node->get($fieldName);
    foreach ($mediaList->referencedEntities() as $media) {
      if ($media->bundle() == 'image') {
        $mediaEntity = !empty($media->field_media_image) ? $media->field_media_image->entity : NULL;
      }
      elseif ($media->bundle() == 'file') {
        $mediaEntity = $media;
      }
      $mediaImageUrl = !empty($mediaEntity) ? $mediaEntity->getFileUri() : NULL;
      $images[] = file_create_url($mediaImageUrl);
    }
    return $images;
  }


  function bookExportFormatAddress($address) {
    $str = '';
    $str .= !empty($address->address_line1) ? $address->address_line1 . ', ' : '' ;
    $str .= !empty($address->address_line2) ? $address->address_line1 . ', ' : '' ;
    $str .= !empty($address->postal_code) ? $address->postal_code : '' ;
    $str .= !empty($address->locality) ? ' ' . $address->locality : '' ;
    return $str;
  }

}
