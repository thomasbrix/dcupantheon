<?php

namespace Drupal\dcu_utility\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\node\NodeInterface;

/**
 * Provides a 'RelatedArticles' block.
 *
 * @Block(
 *  id = "related_articles",
 *  admin_label = @Translation("Related articles"),
 * )
 */
class RelatedArticles extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $build['#theme'] = 'related_articles';
    $build['#content']['related'] = FALSE;
    $node = \Drupal::routeMatch()->getParameter('node');

    //If object is not of type node.
    if (!$node instanceof NodeInterface) {
      return $build;
    }

    $current_nid = $node->id();
    $district = $node->get('field_district')->getValue();

    //Only articles and articles without any districts set.
    if ($node->bundle() != 'article' || !empty($district)) {
      return $build;
    }

    //Check if any custom related articles set on the node.
    $related_nodes_is_set = $node->get('field_related_content')->getValue();
    if (empty($related_nodes_is_set)) {
      //User didnt add any related articles - get 4 latest articles.
      $build['#content']['related'] = $this->getRelatedArticles($current_nid, 4);
      return $build;
    }

    //Build array of custom related articles. Related articles added by user.
    $related_custom = [];
    foreach ($related_nodes_is_set as $target_id) {
      $related_custom[] = $target_id['target_id'];
    }

    //Only show 4 articles.
    if (count($related_nodes_is_set) == 4) {
      //User added 4 related articles.
      $build['#content']['related'] = $this->getRelatedArticles($current_nid, 0, $related_custom);
    }
    else {
      //User added some related articles - fill up with latest to get 4 articles.
      $number_of_articles_to_get = 4 - count($related_nodes_is_set);
      $build['#content']['related'] = $this->getRelatedArticles($current_nid, $number_of_articles_to_get, $related_custom);
    }
    return $build;
  }



  /**
   * @param $current_nid //Article being viewed.
   * @param $related_latest //Latest articles - set automatically.
   * @param null $related_custom //Related set by user.
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function getRelatedArticles($current_nid, $related_latest, $related_custom = NULL) {
    $entity_type = 'node';
    $view_mode = 'list_4_in_row_infinite';
    $view_builder = \Drupal::entityTypeManager()->getViewBuilder($entity_type);
    $storage = \Drupal::entityTypeManager()->getStorage($entity_type);

    $related = [];
    if ($related_custom) {
      //Get related articles set by user.
      foreach ($related_custom as $nid) {
        if ($node = $storage->load($nid)) {
          $related[] = $view_builder->view($node, $view_mode);
        }
        else {
          \Drupal::logger('dcu_utility')->error('Node references non existing node in related articles. Article Nid:@anid - Nid:@nid', ['@anid' => $current_nid, '@nid' => $nid]);
        }
      }
    }

    if ($related_latest) {
      $related_custom[] = $current_nid;
      $exclude =  implode($related_custom,',' );

      $database = \Drupal::database();
      $query = $database->query("select n.nid from
        node_field_data n
        where
        n.type = 'article' and n.status = 1 and
        n.nid not in (select entity_id from node__field_district) and
        n.nid not in (" . $exclude . ")
        order by n.created desc limit " . $related_latest
      );
      $result = $query->fetchAll();

      foreach ($result as $node) {
        $node = $storage->load($node->nid);
        $related[] = $view_builder->view($node, $view_mode);
      }
    }
    return $related;
  }



}

