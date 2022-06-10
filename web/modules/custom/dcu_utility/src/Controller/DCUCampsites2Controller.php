<?php

namespace Drupal\dcu_utility\Controller;

use Drupal\Core\Controller\ControllerBase;

class DCUCampsites2Controller extends ControllerBase {
  public function content() {
    $nids = [37601,37867,37876,37626,37625,37970,74226,37968,37630];
    $view_mode = 'full';
    $nodes = \Drupal\node\Entity\Node::loadMultiple($nids);
    // And then you can view/build them all together:
    $build = \Drupal::entityTypeManager()->getViewBuilder('node')->viewMultiple($nodes, $view_mode);
    return $build;
  }
}
