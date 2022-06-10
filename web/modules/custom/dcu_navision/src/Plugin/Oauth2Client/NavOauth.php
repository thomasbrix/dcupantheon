<?php

namespace Drupal\dcu_navision\Plugin\Oauth2Client;

use Drupal\oauth2_client\Plugin\Oauth2Client\Oauth2ClientPluginBase;
use League\OAuth2\Client\Token\AccessToken;

/**
 * Resource Owner example plugin.
 *
 * @Oauth2Client(
 *   id = "nav_oauth",
 *   name = @Translation("Oauth client for Navision"),
 *   grant_type = "client_credentials",
 *   authorization_uri = "https://login.microsoftonline.com/6dfe4e41-1619-4c3d-971d-3d7be24086cb/oauth2/v2.0/token",
 *   token_uri = "https://login.microsoftonline.com/6dfe4e41-1619-4c3d-971d-3d7be24086cb/oauth2/v2.0/token",
 *   resource_owner_uri = "",
 *   scopes = {"https://api.businesscentral.dynamics.com/.default"},
 *   scope_separator = ",",
 *   request_options = {
 *     "scope" = "https://api.businesscentral.dynamics.com/.default",
 *   },
 * )
 */
class NavOauth extends Oauth2ClientPluginBase {


  /**
   * {@inheritdoc}
   */
  public function storeAccessToken(AccessToken $accessToken) {
    $this->state->set('oauth2_client_access_token-' . $this->getId(), $accessToken);
  }

  /**
   * {@inheritdoc}
   */
  public function retrieveAccessToken() {
    return $this->state->get('oauth2_client_access_token-' . $this->getId());
  }

  /**
   * {@inheritdoc}
   */
  public function clearAccessToken() {
    $this->state->delete('oauth2_client_access_token-' . $this->getId());
  }

}
