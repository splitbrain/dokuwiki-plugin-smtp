<?php

namespace dokuwiki\plugin\smtp\test;

use DokuWikiTest;

/**
 * Admin page rendering tests for the smtp plugin
 *
 * @group plugin_smtp
 * @group plugins
 */
class AdminTest extends DokuWikiTest
{
    protected $pluginsEnabled = ['smtp'];

    /**
     * Render the admin page and return its HTML
     *
     * @return string
     */
    protected function renderAdmin(): string
    {
        /** @var \admin_plugin_smtp $admin */
        $admin = plugin_load('admin', 'smtp');
        ob_start();
        $admin->html();
        return ob_get_clean();
    }

    /**
     * With the default (login) auth type there is no OAuth UI
     */
    public function testNoOAuthPanelForLogin(): void
    {
        global $conf;
        $conf['plugin']['smtp']['auth_type'] = 'login';

        $html = $this->renderAdmin();
        $this->assertStringNotContainsString('plugin_smtp_oauth', $html);
        $this->assertStringContainsString('Testmail', $html); // the test mail form is always there
    }

    /**
     * With oauth selected but no client configured, we show a hint, not a button
     */
    public function testOAuthPanelNeedsClient(): void
    {
        global $conf;
        $conf['plugin']['smtp']['auth_type'] = 'oauth';
        $conf['plugin']['smtp']['oauth_client_id'] = '';
        $conf['plugin']['smtp']['oauth_client_secret'] = '';

        $html = $this->renderAdmin();
        $this->assertStringContainsString('plugin_smtp_oauth', $html);
        // the redirect URI to register is always shown
        $this->assertStringContainsString(DOKU_URL . 'doku.php', $html);
        // but no connect button yet
        $this->assertStringNotContainsString('value="authorize"', $html);
    }

    /**
     * With oauth selected and a client configured, the Connect button appears
     */
    public function testOAuthPanelShowsConnectButton(): void
    {
        global $conf;
        $conf['plugin']['smtp']['auth_type'] = 'oauth';
        $conf['plugin']['smtp']['oauth_provider'] = 'google';
        $conf['plugin']['smtp']['oauth_client_id'] = 'CID';
        $conf['plugin']['smtp']['oauth_client_secret'] = 'SECRET';

        $html = $this->renderAdmin();
        $this->assertStringContainsString('plugin_smtp_oauth', $html);
        $this->assertStringContainsString('name="oauth"', $html);
        $this->assertStringContainsString('value="authorize"', $html);
        $this->assertStringContainsString(DOKU_URL . 'doku.php', $html);
    }
}
