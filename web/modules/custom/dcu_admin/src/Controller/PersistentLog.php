<?php

namespace Drupal\dcu_admin\Controller;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Class PersistentLog.
 */
class PersistentLog extends ControllerBase {

  /**
   * List a specific persistent log id together with nearest loglines.
   * Nearest is default defined as -10 +10 loglines from table id.
   *
   * @param $wid
   *
   * @return array
   */
  public function viewDbLogProximity($wid) {

    $header = [t('Log ID'), t('User ID'), t('Dato'), t('Type'), t('Channel'), t('Data')];
    $database = \Drupal::database();
    $query = $database->select('dblog_persistent', 'log');
    $query->fields('log');
    $query->condition('log.wid', $wid+10 , '<=');
    $query->condition('log.wid', $wid-10 , '>');
    $results = $query->execute();
    $rows = [];
    foreach($results as $row) {
      $message = $this->formatMessage($row);
      $class = ($row->wid == $wid) ? 'color-warning' : '';
      $rows[] = [
        'class' => $class,
        'data' => [$row->wid, $row->uid, date('m-d-Y H:i:s', $row->timestamp), $row->type, $row->channel, $message]
      ];
    }
    return [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
    ];
  }

  public function formatMessage($row) {
    // Check for required properties.
    if (isset($row->message, $row->variables)) {
      $variables = @unserialize($row->variables);
      // Messages without variables or user specified text.
      if ($variables === NULL) {
        $message = Xss::filterAdmin($row->message);
      }
      elseif (!is_array($variables)) {
        $message = $this->t('Log data is corrupted and cannot be unserialized: @message', ['@message' => Xss::filterAdmin($row->message)]);
      }
      // Message to translate with injected variables.
      else {
        // Ensure backtrace strings are properly formatted.
        if (isset($variables['@backtrace_string'])) {
          $variables['@backtrace_string'] = new FormattableMarkup(
            '<pre class="backtrace">@backtrace_string</pre>', $variables
          );
        }
        $message = $this->t(Xss::filterAdmin($row->message), $variables);
      }
    }
    else {
      $message = FALSE;
    }
    return $message;
  }

}
