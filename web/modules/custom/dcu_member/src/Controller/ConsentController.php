<?php

namespace Drupal\dcu_member\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\user\Entity\User;


class ConsentController extends ControllerBase {

  /**
   * Callback for the API.
   */
  public function modalContent() {
    $show = FALSE;
    $data = [];
    if ($this->userConsentInquire()) {
      $show = TRUE;
      $data = $this->consentMarkUp();
    }
    $cacheData = new CacheableMetadata();
    $cacheData->setCacheMaxAge(0);
    //$cacheData->addCacheContexts(['user']);
    $response = new CacheableJsonResponse(['data' => $data, 'show' => $show], 200);
    $response->addCacheableDependency($cacheData);
    return $response;
  }

  public function userConsentInquire() {
    if (\Drupal::currentUser()->isAnonymous() ||
      strpos(\Drupal::request()->getRequestUri(), 'samtykke')) {
      return FALSE;
    }
    $config = \Drupal::config('dcu_admin.sitesettings');
    if ($config->get('block_access_to_user_data')) {
      return FALSE;
    }
    if (is_numeric(\Drupal::currentUser()->getAccountName())) {
      $consent = \Drupal::database()->query(
        'SELECT * FROM {user__field_contact_consent} WHERE field_contact_consent_value IS NOT NULL AND entity_id = :uid',
        [':uid' => \Drupal::currentUser()->id()]
      );
      return empty($consent->fetchAll());
    }
    $user = User::load(\Drupal::currentUser()->id());
    if (is_numeric($user->get('field_memberid')->getString())) {
        $consent = \Drupal::database()->query(
          'SELECT * FROM {user__field_contact_consent} WHERE field_contact_consent_value IS NOT NULL AND entity_id = :uid',
          [':uid' => \Drupal::currentUser()->id()]
        );
        return empty($consent->fetchAll());
    }
    return FALSE;
  }

  public function confirmConsent() {
    $user = User::load(\Drupal::currentUser()->id());
    $today = date("Y-m-d", time());
    $user->set('field_contact_consent', $today);
    try {
      $user->save();
    } catch (EntityStorageException $e) {
      \Drupal::messenger()->addMessage($this->t('There was an error updating your information'), 'error');
      \Drupal::logger('dcu_member')->error('Failed to save user consent. Failed with the following message: @error', ['@error' => $e->getMessage()]);
    }
    $result = TRUE;
    $status = 200;
    if (!$navresult = dcu_member_send_userdata_to_nav($user->id())) {
      \Drupal::messenger()->addMessage($this->t('There was an error updating your information'), 'error');
      $result = FALSE;
      $status = 500;
    }
    $cacheData = new CacheableMetadata();
    $cacheData->setCacheMaxAge(0);
    //$cacheData->addCacheContexts(['user']);
    $response = new CacheableJsonResponse(['result' => $result], $status);
    $response->addCacheableDependency($cacheData);
    return $response;
  }

  private function consentMarkUp() {
    return '
    <div class="modal fade" data-backdrop="static" id="consentmodal" tabindex="-1" role="dialog" aria-labelledby="consentTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered consent modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="consentTitle">Vi har brug for din tilladelse</h5>
      </div>
      <div class="modal-body">
        <p>
          I DCU vil vi gerne kunne give alle medlemmer den bedste service, informere om de mange medlemsfordele og gode tilbud – men vi har brug for din tilladelse for at kunne gøre det. Det kan f.eks. dreje sig om:<br/>
        <ul>
          <li>Viden om nye medlemsfordele</li>
          <li>Særlige tilbud om produkter fra DCU eller DCUs samarbejdspartnere, f.eks. campingforsikringer
          </li>
        </ul>
        Du kan læse mere <a href="/samtykke" target="_blank">her </a>
        </p>
        <p>
          <strong>Samtykke:</strong><br/> jeg vil gerne modtage information og høre om relevante fordele og tilbud fra DCU. Jeg kan til enhver tid framelde mig igen.
        </p>
        <p>
          <button type="button" class="btn btn-success" id="usrconfirmconsent">Ja tak</button>
          <button type="button" class="btn btn-light" id="consentModalClose"">Måske senere</button>
        </p>
      </div>
      <div class="modal-footer" style="display: block;">
        <h6>Det giver du DCU tilladelse til</h6>
        <ul >
          <li>E-mail, telefon og SMS: Vi ringer eller skriver, når vi har relevante budskaber eller tilbud til dig..</li>
          <li>Sociale medier: På f.eks. Facebook og Instagram kan du blive vist særlige annoncer, tilbud og beskeder fra os eller vores samarbejdspartnere.</li>
          <li>Når du logger ind på Mit DCU, kan der poppe en boks op med relevante beskeder, tilbud o.l.</li>
        </ul>
      </div>
    </div>
  </div>
</div>
    ';
  }

}
