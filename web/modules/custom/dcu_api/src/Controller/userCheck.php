<?php


namespace Drupal\dcu_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dcu_navision\Client\NavisionRestClient;
use Symfony\Component\DependencyInjection\ContainerInterface;

class userCheck extends ControllerBase {

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
   * Returns a render-able array for a test page.
   */
  public function content($memberno) {
    $result = t("Dette medlem er IKKE AKTIV<br>This member is NOT ACTIVE<br>Dieses Mitglied ist NICHT AKTIV");
    if (!empty($memberno) && is_numeric($memberno)) {
      $memberData = $this->navisionClient->getMemberData($memberno);
      if (!empty($memberData)) {
        if ($memberData->memberstatus == 'AKTIV') {
          $result = t("Dette medlem er AKTIV<br>This member is ACTIVE<br>Dieses Mitglied ist AKTIV");
        }
      }
    }
    $build = [
      '#markup' => '<div class="container"><div class="row"><div class="col-md-12 user_check_info">' . $result . '</div></div></div>',
    ];
    return $build;
  }
}
