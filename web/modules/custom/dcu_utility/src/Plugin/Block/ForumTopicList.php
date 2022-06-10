<?php

namespace Drupal\dcu_utility\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\Entity\Term;

/**
 * Provides a 'ForumTopicList' block.
 *
 * @Block(
 *  id = "forum_topic_list",
 *  admin_label = @Translation("Forum topic list"),
 * )
 */
class ForumTopicList extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    //Build options for select.
    $forum_topics = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('forums',  0, NULL, TRUE);
    foreach ($forum_topics as $term) {
      $options[$term->id()] = $term->getName();
    }
    $form['forum_topics'] = [
      '#type' => 'select',
      '#title' => $this->t('Forum topics'),
      '#options' => $options,
      '#default_value' => !empty($this->configuration['forum_topics']) ? $this->configuration['forum_topics'] : 8,
      '#weight' => '0',
      '#required' => TRUE,
      '#description' => 'Choose the desired forum topic.'
    ];
    $form['forum_topics_to_show'] = [
      '#type' => 'number',
      '#title' => $this->t('How many topics to show'),
      '#default_value' => !empty($this->configuration['forum_topics_to_show']) ? $this->configuration['forum_topics_to_show'] : 3,
      '#weight' => '0',
      '#description' => 'Default is 3',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['forum_topics'] = $form_state->getValue('forum_topics');
    $this->configuration['forum_topics_to_show'] = $form_state->getValue('forum_topics_to_show');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $build['#theme'] = 'forum_topic_list';
    $term = Term::load($this->configuration['forum_topics']);
    $build['#content']['forum_topic_name'] = $term->getName();

    $database = \Drupal::database();

    //Get number of topics for specific tid.
    $build['#content']['topic_count'] = $database->query("select count(1) from forum_index where tid = :tid", [':tid' => $this->configuration['forum_topics']])->fetchField();

    //Get 3 latest topics.
    $query = $database->query("select f.nid, f.title, nb.body_value as body,
       CONCAT(SUBSTRING(nb.body_value,1, 300),'...Læs mere') as body2,
       from_unixtime(f.created, '%d-%m-%Y') as created,
      CONCAT(fn.field_first_name_value, ' ', ln.field_last_name_value) as name,
      f.last_comment_timestamp, from_unixtime(f.last_comment_timestamp, '%d-%m-%Y') as lcd, f.comment_count
      from forum_index f
      left join node_field_data n on n.nid = f.nid
      left join user__field_first_name fn on fn.entity_id = n.uid
      left join user__field_last_name ln on ln.entity_id = n.uid
      left join node__body nb on nb.entity_id = n.nid
      where tid = :tid
      order by f.created desc limit " . $this->configuration['forum_topics_to_show'], [':tid' => $this->configuration['forum_topics']]
    );

    $result = $query->fetchAll();
    foreach ($result as $key => $value) {
      $body = strip_tags($value->body);
      if (strlen($body) > 175) {
        $body = substr($body,0,175) . '...';
      }
      $result[$key]->body = $body;
      $result[$key]->link = \Drupal::service('path_alias.manager')->getAliasByPath('/node/'. $value->nid, 'da');
    }
    $build['#content']['topics'] = $result;
    return $build;
  }
}
