<?php

use dokuwiki\Extension\AdminPlugin;
use dokuwiki\Form\Form;
use splitbrain\dokuwiki\plugin\smtp\TokenStore;

/**
 * SMTP Plugin - admin component
 *
 * Lets the admin send a test mail and, when OAuth authentication is configured,
 * connect the sending account to the OAuth provider.
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 */

class admin_plugin_smtp extends AdminPlugin
{
    /**
     * return sort order for position in admin menu
     */
    public function getMenuSort()
    {
        return 200;
    }

    /**
     * handle user request
     */
    public function handle()
    {
        global $INPUT;
        global $conf;

        // OAuth connect/disconnect actions
        $oauthAction = $INPUT->str('oauth');
        if ($oauthAction && checkSecurityToken()) {
            $this->handleOAuth($oauthAction);
            return;
        }

        if (!$INPUT->bool('send')) return;

        // make sure debugging is on;
        $conf['plugin']['smtp']['debug'] = 1;

        // send a mail
        $mail = new Mailer();
        if ($INPUT->str('to')) $mail->to($INPUT->str('to'));
        if ($INPUT->str('cc')) $mail->cc($INPUT->str('cc'));
        if ($INPUT->str('bcc')) $mail->bcc($INPUT->str('bcc'));
        $mail->subject('DokuWiki says hello');
        $mail->setBody("Hi @USER@\n\nThis is a test from @DOKUWIKIURL@");

        $ok = $mail->send();

        // check result
        if ($ok) {
            msg('Message was sent. SMTP seems to work.', 1);
        } else {
            msg('Message wasn\'t sent. SMTP seems not to work properly.', -1);
        }
    }

    /**
     * Start or undo the OAuth consent flow
     *
     * @param string $action either "authorize" or "disconnect"
     * @return void
     */
    protected function handleOAuth($action)
    {
        if ($action === 'disconnect') {
            (new TokenStore())->clear();
            msg($this->getLang('oauth_disconnected'), 1);
            return;
        }

        if ($action === 'authorize') {
            $state = bin2hex(random_bytes(16));
            if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
            $_SESSION[DOKU_COOKIE]['smtp_oauth_state'] = $state;

            /** @var action_plugin_smtp $smtp */
            $smtp = plugin_load('action', 'smtp');
            send_redirect($smtp->getOAuth()->getAuthorizationUrl($state));
        }
    }

    /**
     * Output HTML form
     */
    public function html()
    {
        global $conf;

        echo $this->locale_xhtml('intro');

        if (!$conf['mailfrom']) msg($this->getLang('nofrom'), -1);

        if ($this->getConf('auth_type') === 'oauth') {
            $this->htmlOAuth();
        }

        $this->htmlTestMail();
    }

    /**
     * Output the OAuth connection status and connect/disconnect controls
     *
     * @return void
     */
    protected function htmlOAuth()
    {
        /** @var action_plugin_smtp $smtp */
        $smtp = plugin_load('action', 'smtp');
        $store = new TokenStore();

        echo '<h2>' . hsc($this->getLang('oauth_heading')) . '</h2>';
        echo '<div class="plugin_smtp_oauth">';

        // the redirect URI the admin needs to register with the provider
        echo '<p>' . hsc($this->getLang('oauth_redirect_hint')) . '</p>';
        echo '<pre class="plugin_smtp_redirect">' . hsc($smtp::getRedirectUri()) . '</pre>';

        if (!$this->getConf('oauth_client_id') || !$this->getConf('oauth_client_secret')) {
            msg($this->getLang('oauth_noclient'), -1);
        } elseif ($store->isAuthorized()) {
            $expires = (int) $store->get('expires_at', 0);
            $info = $expires
                ? sprintf($this->getLang('oauth_valid_until'), dformat($expires))
                : '';
            echo '<p class="plugin_smtp_connected">' .
                hsc($this->getLang('oauth_connected_info')) . ' ' . hsc($info) . '</p>';

            $form = new Form();
            $form->setHiddenField('do', 'admin');
            $form->setHiddenField('page', 'smtp');
            $form->setHiddenField('oauth', 'disconnect');
            $form->addButton('submit', $this->getLang('oauth_disconnect'))->attr('type', 'submit');
            echo $form->toHTML();
        } else {
            echo '<p class="plugin_smtp_disconnected">' . hsc($this->getLang('oauth_notconnected')) . '</p>';

            $form = new Form();
            $form->setHiddenField('do', 'admin');
            $form->setHiddenField('page', 'smtp');
            $form->setHiddenField('oauth', 'authorize');
            $form->addButton('submit', $this->getLang('oauth_authorize'))->attr('type', 'submit');
            echo $form->toHTML();
        }

        echo '</div>';
    }

    /**
     * Output the test mail form
     *
     * @return void
     */
    protected function htmlTestMail()
    {
        global $INPUT;

        $form = new Form();
        $form->addClass('plugin_smtp_admin');
        $form->addFieldsetOpen('Testmail');
        $form->setHiddenField('send', 1);
        $form->addTextInput('to', 'To:')->val($INPUT->str('to'));
        $form->addTextInput('cc', 'Cc:')->val($INPUT->str('cc'));
        $form->addTextInput('bcc', 'Bcc:')->val($INPUT->str('bcc'));
        $form->addButton('submit', 'Send Email')->attr('type', 'submit');
        $form->addFieldsetClose();

        echo $form->toHTML();
    }
}
