<?php

namespace Drupal\dcu_navision\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Navision controller.
 */
class NavisionController extends ControllerBase {

  /**
   * Returns a render-able array for a test page.
   */
  public function request() {
    $build = [
      '#markup' => 'Various dcu nav request forms could be rendered here.',
    ];
    return $build;
  }


}
