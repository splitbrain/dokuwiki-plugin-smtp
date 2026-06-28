<?php

namespace splitbrain\dokuwiki\plugin\smtp;

use dokuwiki\HTTP\DokuHTTPClient;

/**
 * Class OAuth
 *
 * Implements the OAuth 2.0 authorization-code flow with offline access that is
 * needed to authenticate against modern SMTP servers (Gmail, Microsoft 365, ...)
 * which no longer accept plain passwords.
 *
 * The flow has two parts:
 *
 *  1. A one time, interactive consent (handled by the admin component) that
 *     yields a long lived refresh token.
 *  2. A non interactive exchange of that refresh token for short lived access
 *     tokens that are used as XOAUTH2 credentials on every mail send.
 *
 * Only DokuWiki's bundled HTTP client is used, so no heavy third party OAuth
 * library is required.
 *
 * @package splitbrain\dokuwiki\plugin\smtp
 */
class OAuth
{
    /**
     * Known provider presets
     *
     * Each preset defines the endpoints, the required SMTP scope and any extra
     * parameters that need to be added to the authorization request to obtain a
     * refresh token.
     *
     * @var array<string,array>
     */
    protected const PROVIDERS = array(
        'google' => array(
            'authurl'    => 'https://accounts.google.com/o/oauth2/v2/auth',
            'tokenurl'   => 'https://oauth2.googleapis.com/token',
            'scope'      => 'https://mail.google.com/',
            'authparams' => array('access_type' => 'offline', 'prompt' => 'consent'),
        ),
        'microsoft' => array(
            'authurl'    => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
            'tokenurl'   => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            'scope'      => 'https://outlook.office.com/SMTP.Send offline_access',
            'authparams' => array('prompt' => 'consent'),
        ),
    );

    /** @var array plugin configuration */
    protected $conf;

    /** @var TokenStore token storage */
    protected $store;

    /** @var string the redirect URI registered with the provider */
    protected $redirectUri;

    /**
     * @param array $conf the plugin configuration (getConf values)
     * @param TokenStore $store the token storage
     * @param string $redirectUri the redirect URI registered with the provider
     */
    public function __construct(array $conf, TokenStore $store, $redirectUri)
    {
        $this->conf = $conf;
        $this->store = $store;
        $this->redirectUri = $redirectUri;
    }

    /**
     * Resolve the effective provider configuration
     *
     * Uses one of the presets or, for the "custom" provider, the values
     * configured by the admin. A configured scope always overrides the preset.
     *
     * @return array with keys authurl, tokenurl, scope, authparams
     */
    public function getProvider()
    {
        $name = $this->conf['oauth_provider'] ?? 'google';
        if (isset(self::PROVIDERS[$name])) {
            $provider = self::PROVIDERS[$name];
        } else {
            $provider = array(
                'authurl'    => $this->conf['oauth_authurl'] ?? '',
                'tokenurl'   => $this->conf['oauth_tokenurl'] ?? '',
                'scope'      => '',
                'authparams' => array(),
            );
        }
        if (!empty($this->conf['oauth_scope'])) {
            $provider['scope'] = $this->conf['oauth_scope'];
        }
        return $provider;
    }

    /**
     * Build the URL the admin's browser is sent to in order to grant consent
     *
     * @param string $state opaque anti-CSRF value echoed back by the provider
     * @return string
     */
    public function getAuthorizationUrl($state)
    {
        $provider = $this->getProvider();
        $params = array_merge(
            array(
                'client_id'     => $this->conf['oauth_client_id'] ?? '',
                'redirect_uri'  => $this->redirectUri,
                'response_type' => 'code',
                'scope'         => $provider['scope'],
                'state'         => $state,
            ),
            $provider['authparams']
        );
        // force a literal "&" separator and RFC3986 encoding; DokuWiki sets
        // arg_separator.output to "&amp;" which would corrupt the URL
        return $provider['authurl'] . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Exchange an authorization code for tokens and store them
     *
     * Called from the OAuth callback after the user granted consent.
     *
     * @param string $code the authorization code returned by the provider
     * @return void
     * @throws \Exception when the exchange fails or no refresh token is returned
     */
    public function handleCallback($code)
    {
        $provider = $this->getProvider();
        $resp = $this->request($provider['tokenurl'], array(
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => $this->redirectUri,
            'client_id'     => $this->conf['oauth_client_id'] ?? '',
            'client_secret' => $this->conf['oauth_client_secret'] ?? '',
            'scope'         => $provider['scope'],
        ));

        if (empty($resp['refresh_token'])) {
            throw new \Exception(
                'The provider did not return a refresh token. ' .
                'Make sure offline access is granted and try again.'
            );
        }

        $this->store->set(array(
            'refresh_token' => $resp['refresh_token'],
            'access_token'  => $resp['access_token'] ?? '',
            'expires_at'    => time() + (int) ($resp['expires_in'] ?? 0),
        ));
    }

    /**
     * Return a valid access token, refreshing it if necessary
     *
     * @return string
     * @throws \Exception when not authorized or the refresh fails
     */
    public function getAccessToken()
    {
        if (!$this->store->isAuthorized()) {
            throw new \Exception('No account has been connected yet. Authorize access in the SMTP admin.');
        }

        $token = $this->store->get('access_token');
        $expires = (int) $this->store->get('expires_at', 0);

        // refresh shortly before the token actually expires to avoid races
        if (!$token || $expires - 60 <= time()) {
            $token = $this->refresh();
        }

        return $token;
    }

    /**
     * Use the stored refresh token to obtain a new access token
     *
     * @return string the fresh access token
     * @throws \Exception when the refresh fails
     */
    protected function refresh()
    {
        $provider = $this->getProvider();
        $resp = $this->request($provider['tokenurl'], array(
            'grant_type'    => 'refresh_token',
            'refresh_token' => $this->store->get('refresh_token'),
            'client_id'     => $this->conf['oauth_client_id'] ?? '',
            'client_secret' => $this->conf['oauth_client_secret'] ?? '',
            'scope'         => $provider['scope'],
        ));

        $update = array(
            'access_token' => $resp['access_token'] ?? '',
            'expires_at'   => time() + (int) ($resp['expires_in'] ?? 0),
        );
        // some providers rotate the refresh token on use
        if (!empty($resp['refresh_token'])) {
            $update['refresh_token'] = $resp['refresh_token'];
        }
        $this->store->set($update);

        if (empty($update['access_token'])) {
            throw new \Exception('The token refresh did not return an access token.');
        }
        return $update['access_token'];
    }

    /**
     * POST to a token endpoint and decode the JSON response
     *
     * @param string $url
     * @param array $params form parameters
     * @return array the decoded response
     * @throws \Exception on transport errors or error responses
     */
    protected function request($url, array $params)
    {
        if (!$url) {
            throw new \Exception('No OAuth token endpoint configured.');
        }

        $http = new DokuHTTPClient();
        $http->keep_alive = false;
        $http->headers['Accept'] = 'application/json';

        $body = $http->post($url, $params);
        if ($body === false) {
            // DokuHTTPClient returns false on error codes but still keeps the body
            $body = $http->resp_body;
        }

        $data = json_decode((string) $body, true);
        if (!is_array($data)) {
            throw new \Exception('Unexpected response from token endpoint (HTTP ' . $http->status . ').');
        }
        if (isset($data['error'])) {
            $msg = $data['error'];
            if (!empty($data['error_description'])) {
                $msg .= ': ' . $data['error_description'];
            }
            throw new \Exception('OAuth error: ' . $msg);
        }

        return $data;
    }
}
