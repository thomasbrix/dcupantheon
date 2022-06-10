<?php

namespace Drupal\dcu_feed_import\EventSubscriber;

use Drupal\feeds\Event\EntityEvent;
use Drupal\feeds\Event\FeedsEvents;
use Drupal\paragraphs\Entity\Paragraph;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Component\Serialization\Json;

class DcuFeedImportSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[FeedsEvents::PROCESS_ENTITY_PRESAVE][] = 'presave';
    $events[FeedsEvents::PROCESS_ENTITY_POSTSAVE][] = 'postsave';
    return $events;
  }

  /**
   * Acts on presaving an entity.
   * @param Drupal\feeds\Event\EntityEvent $event
   *
   * @return Drupal\feeds\Event\EntityEvent $event
   */
  public function presave(EntityEvent $event) {
    $entity = $event->getEntity();
    if ($entity->bundle() == 'user') {
      return;
    }
    $mapFields = [
      'field_legacy_body' => 'field_body',
      'field_legacy_body_locked' => 'field_body_locked_content',
    ];
    foreach ($mapFields as $legacyFieldName => $fieldName) {
      if (!$entity->hasField($legacyFieldName)) {
        continue;
      }
      $body = $entity->get($legacyFieldName)->value;
      if (empty($body)) {
        continue;
      }
      // Find embedded image.
      preg_match_all('/\[\[(.*?)\]\]/', $body, $matches);
      if (!empty($matches)) {
        foreach ($matches[0] as $key => $match) {
          $embedStr = '';
          // Find fid of file. This maps to legacy_fid to find imported media in d8.
          $embedData = Json::decode($match);
          $embedData = $embedData[0][0];
          $fid = !empty($embedData['fid']) ?$embedData['fid'] : '';
          if (empty($fid)) {
            $body = str_replace($match, '', $body);
            continue;
          }
          $media = \Drupal::entityTypeManager()->getStorage('media')->loadByProperties(['field_legacy_fid' => $fid]);
          if (empty($media)) {
            // Legacy media does not exist. Remove nonexisting media from body with generated html.
            $body = str_replace($match, '', $body);
            continue;
          }
          $media = reset($media);
          // TODO: Implement embedded videos.
          if ($media->bundle() == 'image') {
            $alt = !empty($media->field_media_image->alt) ? $media->field_media_image->alt : '' ;
            //$dataCaption = !empty($alt) ? 'data-caption="' . $alt . '"' : '';
            $align = !empty($embedData["fields"]["align"]) ? 'data-align="' . $embedData["fields"]["align"] . '"' : '';
            $viewMode = $embedData['view_mode'] == 'wysiwyg_big' ? 'data-view-mode="embed_large"' : 'data-view-mode="full"';
            // Drupal 8 wysiwyg html.
            //$mediaProperties = 'data-entity-type="media" data-entity-uuid="' . $media->uuid() . '" ' . $viewMode . ' ' . $dataCaption .  ' ' . $align . ' alt="' . $alt . '"';
            $mediaProperties = 'data-entity-type="media" data-entity-uuid="' . $media->uuid() . '" ' . $viewMode . ' ' . $align . ' alt="' . $alt . '"';
            $embedStr = '<drupal-media ' . $mediaProperties . '></drupal-media>';
          }
          // Replace $match in body with generated html.
          $body = str_replace($match, $embedStr, $body);
        }
      }
      $entity->set($fieldName, array('value' => $body, 'format'=>'full_html'));
      $entity->save();
    }
  }

  /**
   * Acts on postsaving an entity.
   */
  public function postsave(EntityEvent $event) {
    $entity = $event->getEntity();
    if ($entity->bundle() == 'user') {
      $this->postsaveUser($entity, $event);
      return;
    }
  }


  protected function postsaveUser($user, $event) {
    $favorites_data = $event->getItem()->get('favorites');
    if (empty($favorites_data)) {
      return;
    }
    $flag_service = \Drupal::service('flag');
    $flag = $flag_service->getFlagById('favorite');
    $favorites = explode(',', $favorites_data);
    foreach ($favorites as $favorite) {
      $node = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['field_legacy_nid' => $favorite]);
      if (empty($node)) {
        continue;
      }
      $node = reset($node);
      $flag_service->flag($flag, $node, $user);
    }
  }

}
