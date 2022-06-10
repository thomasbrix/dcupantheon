<?php

namespace Drupal\dcu_admin\Form;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\StreamWrapper\PrivateStream;
use Drupal\Core\Url;
use Drupal\dcu_navision\Client\NavisionRestClient;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;


/**
 * Class MemberMagazineExport.
 */
class MemberMagazineExport extends FormBase {

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
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'member_magazine_export';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['desccription'] = [
      '#markup' => '<p>' . $this->t('Download Member name and address data as csv for Magazine.') . '<br/></p>',
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generer og download csv fil'),
    ];
    $files = \Drupal::service('file_system')->scanDirectory('private://magazine_export', '/.*.csv/');
    rsort($files);
    foreach ($files as $file) {
      $fileRows[] =  [
        'filename' => $file->filename,
        'url' => [
          'data' => new FormattableMarkup('<a href=":link">@name</a>', [':link' => Url::fromUri(file_create_url($file->uri))->toString(), '@name' => $file->filename])
        ],
      ];
    }
    $form['existingfiles'] = [
      '#markup' => '<p><h2>' . $this->t('Previously generated member magazine files') . '</h2></p>',
    ];
    $form['files'] = [
      '#prefix' => '<p>',
      '#type' => 'table',
      '#header' => [$this->t('Filename'), $this->t('Download')],
      '#rows' => $fileRows,
      '#suffix' => '</p>',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    ini_set('memory_limit', '1048M');
    ini_set('max_execution_time', 600);
    $filename = 'member_magazine_export_' . date('Y-m-d_His') . '.csv';
    $directory = \Drupal::service('file_system')->realpath('private://magazine_export');
    $file = $directory . '/' . $filename;
    if (!$fh = fopen($file, 'w')) {
      \Drupal::messenger()->addMessage('There was an error opening cvs file for writing', 'error');
      return;
    }

    if (!$members = $this->navisionClient->getMagazineMembers()) {
      \Drupal::messenger()->addMessage('There was an error fetching data from Navision', 'error');
      return FALSE;
    }

    $first = TRUE;
    foreach ($members as $member) {
      $row = [
        'memberid' => $member->memberno,
        'name' => $member->name,
        'street' => $member->address,
        'city' => $member->postalcode . ' ' . $member->city,
        ];
      if ($first) {
        fputcsv($fh, array_keys($row));
        $first = FALSE;
      }
      fputcsv($fh, $row);
    }
    fclose($fh);

    $response = new BinaryFileResponse($file);
    $response->headers->set('Content-type', mime_content_type($file));
    $disposition = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);
    $response->headers->set('Content-Disposition', $disposition);
    $response->headers->set('Content-Transfer-Encoding', 'binary');
    $response->headers->set('Content-length', filesize($file));
    $form_state->setResponse($response);
    \Drupal::messenger()->addMessage('Magazine member csv file generated and should download automatically', 'status');
  }

}
