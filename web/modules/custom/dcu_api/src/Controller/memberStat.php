<?php


namespace Drupal\dcu_api\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\dcu_navision\Client\NavisionRestClient;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;

class memberStat extends ControllerBase {

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
   * Returns stat data
   */
  public function content() {
    if (\Drupal::request()->query->get('key') != 'dcustats') {
      $url = Url::fromRoute('<front>')->toString();
      return new RedirectResponse($url);
    }
    $cacheObj = \Drupal::cache()->get('dcu_memberstat:dcustats');
    if (!empty($cacheObj)) {
      return $cacheObj->data;
    }
    else {
      ini_set('memory_limit', '1048M');
      ini_set('max_execution_time', 1200);
      $numberOfCallsToNav = 0;//Due to timeout - extra calls might be needed.
      $navActiveMembers = $this->navisionClient->getActiveMembers();
      if ($navActiveMembers) {
        $numberOfCallsToNav = 1;
      }
      //Call Nav again if timeout.
      if (empty($navActiveMembers) && $numberOfCallsToNav == 0) {
        $navActiveMembers = $this->navisionClient->getActiveMembers();
      }
      if (empty($navActiveMembers)) {
        return [];
      }

      $activeMembers = [];
      foreach ($navActiveMembers as $member) {
        $activeMembers[$member->membertype][] = $member->Line_no;
      }
      $result = [];
      foreach ($activeMembers as $type => $memberTypes) {
        $result[$type] = count($memberTypes);
      }
      $data = new JsonResponse($result, 200);
      \Drupal::cache()
        ->set('dcu_memberstat:dcustats', $data, time() + 60 * 60 * 3);
      return $data;
    }
  }
}
