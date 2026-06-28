<?php

namespace dokuwiki\plugin\smtp\test;

use DokuWikiTest;
use splitbrain\dokuwiki\plugin\smtp\OAuth;
use splitbrain\dokuwiki\plugin\smtp\TokenStore;

/**
 * OAuth helper tests for the smtp plugin
 *
 * @group plugin_smtp
 * @group plugins
 */
class OAuthTest extends DokuWikiTest
{
    protected $pluginsEnabled = ['smtp'];

    /** @var string */
    protected $file;

    public function setUp(): void
    {
        parent::setUp();
        $this->file = tempnam(sys_get_temp_dir(), 'smtp_oauth_');
        @unlink($this->file);
    }

    public function tearDown(): void
    {
        @unlink($this->file);
        parent::tearDown();
    }

    protected function conf(array $override = []): array
    {
        return array_merge([
            'oauth_provider'      => 'google',
            'oauth_client_id'     => 'CID',
            'oauth_client_secret' => 'SECRET',
            'oauth_authurl'       => '',
            'oauth_tokenurl'      => '',
            'oauth_scope'         => '',
        ], $override);
    }

    /**
     * Build an OAuth instance whose token endpoint is faked
     *
     * Defined as an anonymous class so the parent OAuth class is only resolved
     * at runtime (after the plugin autoloader has been registered).
     *
     * @param array $conf
     * @param TokenStore $store
     * @return OAuth a subclass exposing a public $calls array of request params
     */
    protected function makeOAuth(array $conf, TokenStore $store): OAuth
    {
        return new class($conf, $store, 'https://w/doku.php') extends OAuth {
            /** @var array all parameter sets passed to request() */
            public $calls = [];
            /** @var int counter to vary the returned access token */
            protected $counter = 0;

            protected function request($url, array $params)
            {
                $this->calls[] = $params;
                if (($params['grant_type'] ?? '') === 'refresh_token') {
                    return ['access_token' => 'ACCESS' . (++$this->counter), 'expires_in' => 3600];
                }
                return ['access_token' => 'ACCESS0', 'refresh_token' => 'REFRESH', 'expires_in' => 3600];
            }
        };
    }

    /**
     * The Google preset must produce a complete consent URL including the
     * parameters needed to obtain a refresh token.
     */
    public function testGoogleAuthorizationUrl(): void
    {
        $oauth = new OAuth($this->conf(), new TokenStore($this->file), 'https://wiki.example.com/doku.php');
        $url = $oauth->getAuthorizationUrl('STATE123');

        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $url);

        parse_str(parse_url($url, PHP_URL_QUERY), $q);
        $this->assertSame('CID', $q['client_id']);
        $this->assertSame('https://wiki.example.com/doku.php', $q['redirect_uri']);
        $this->assertSame('code', $q['response_type']);
        $this->assertSame('https://mail.google.com/', $q['scope']);
        $this->assertSame('STATE123', $q['state']);
        $this->assertSame('offline', $q['access_type']);
        $this->assertSame('consent', $q['prompt']);
    }

    /**
     * The Microsoft preset must request the offline_access scope.
     */
    public function testMicrosoftScope(): void
    {
        $oauth = new OAuth($this->conf(['oauth_provider' => 'microsoft']), new TokenStore($this->file), 'https://w/doku.php');
        $url = $oauth->getAuthorizationUrl('S');
        parse_str(parse_url($url, PHP_URL_QUERY), $q);
        $this->assertStringContainsString('SMTP.Send', $q['scope']);
        $this->assertStringContainsString('offline_access', $q['scope']);
    }

    /**
     * A custom provider uses the configured endpoints and scope.
     */
    public function testCustomProvider(): void
    {
        $oauth = new OAuth($this->conf([
            'oauth_provider' => 'custom',
            'oauth_authurl'  => 'https://id.example.com/auth',
            'oauth_tokenurl' => 'https://id.example.com/token',
            'oauth_scope'    => 'mail.send',
        ]), new TokenStore($this->file), 'https://w/doku.php');

        $url = $oauth->getAuthorizationUrl('S');
        $this->assertStringStartsWith('https://id.example.com/auth?', $url);
        parse_str(parse_url($url, PHP_URL_QUERY), $q);
        $this->assertSame('mail.send', $q['scope']);

        $provider = $oauth->getProvider();
        $this->assertSame('https://id.example.com/token', $provider['tokenurl']);
    }

    /**
     * A configured scope overrides the preset scope.
     */
    public function testScopeOverride(): void
    {
        $oauth = new OAuth($this->conf(['oauth_scope' => 'custom-scope']), new TokenStore($this->file), 'https://w/doku.php');
        $this->assertSame('custom-scope', $oauth->getProvider()['scope']);
    }

    /**
     * Without a stored refresh token an access token cannot be produced.
     */
    public function testAccessTokenRequiresAuthorization(): void
    {
        $oauth = $this->makeOAuth($this->conf(), new TokenStore($this->file));
        $this->expectException(\Exception::class);
        $oauth->getAccessToken();
    }

    /**
     * Full lifecycle: exchange code, reuse a valid token, refresh an expired one.
     */
    public function testTokenLifecycle(): void
    {
        $store = new TokenStore($this->file);
        $oauth = $this->makeOAuth($this->conf(), $store);

        // exchange the authorization code
        $oauth->handleCallback('CODE');
        $this->assertTrue($store->isAuthorized());
        $this->assertSame('REFRESH', $store->get('refresh_token'));
        $this->assertSame('ACCESS0', $store->get('access_token'));
        $this->assertCount(1, $oauth->calls);
        $this->assertSame('authorization_code', $oauth->calls[0]['grant_type']);

        // a still valid token is reused without hitting the endpoint
        $this->assertSame('ACCESS0', $oauth->getAccessToken());
        $this->assertCount(1, $oauth->calls);

        // an expired token triggers a refresh
        $store->set(['expires_at' => time() - 10]);
        $this->assertSame('ACCESS1', $oauth->getAccessToken());
        $this->assertCount(2, $oauth->calls);
        $this->assertSame('refresh_token', $oauth->calls[1]['grant_type']);
        $this->assertSame('REFRESH', $oauth->calls[1]['refresh_token']);
    }
}
