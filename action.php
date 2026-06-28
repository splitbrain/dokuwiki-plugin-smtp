<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Extension\Event;
use splitbrain\dokuwiki\plugin\smtp\Message;
use splitbrain\dokuwiki\plugin\smtp\Logger;
use splitbrain\dokuwiki\plugin\smtp\OAuth;
use splitbrain\dokuwiki\plugin\smtp\TokenStore;
use Tx\Mailer\SMTP;

/**
 * DokuWiki Plugin smtp (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <andi@splitbrain.org>
 */
class action_plugin_smtp extends ActionPlugin
{
    /**
     * Registers a callback function for a given event
     *
     * @param EventHandler $controller DokuWiki's event controller object
     * @return void
     */
    public function register(EventHandler $controller)
    {
        $controller->register_hook('MAIL_MESSAGE_SEND', 'BEFORE', $this, 'handleMailMessageSend');
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'handleOAuthCallback');
    }

    /**
     * Sends a mail through the configured SMTP server
     *
     * @param Event $event event object by reference
     * @param mixed $param  [the parameters passed as fifth argument to register_hook() when this
     *                      handler was registered]
     * @return void
     */
    public function handleMailMessageSend(Event $event, $param)
    {
        // prepare the message
        /** @var Mailer $mailer Our Mailer with all the data */
        $mailer = $event->data['mail'];
        $body = $mailer->dump();  // this also prepares all internal variables of the mailer
        $rcpt   = $event->data['to'] . ',' .
                  $event->data['cc'] . ',' .
                  $event->data['bcc'];
        $from   = $event->data['from'];
        $message = new Message(
            $from,
            $rcpt,
            $body
        );

        // prepare the SMTP communication lib
        $logger = new Logger();
        $smtp = new SMTP($logger);
        $smtp->setServer(
            $this->getConf('smtp_host'),
            $this->getConf('smtp_port'),
            $this->getConf('smtp_ssl'),
            $this->getConf('smtp_allow_insecure')
        );

        // set up authentication
        if (!$this->setupAuth($smtp)) {
            $event->preventDefault();
            $event->stopPropagation();
            $event->result = false;
            $event->data['success'] = false;
            return;
        }

        $smtp->setEhlo(
            helper_plugin_smtp::getEHLO($this->getConf('localdomain'))
        );


        // send the message
        try {
            $smtp->send($message);
            $ok = true;
        } catch (Exception $e) {
            msg('There was an unexpected problem communicating with SMTP: ' . $e->getMessage(), -1);
            $ok = false;
        }

        // give debugging help on error
        if (!$ok && $this->getConf('debug')) {
            $log = [];
            foreach ($logger->getLog() as $line) {
                $log[] = trim($line[1]);
            }
            $log = trim(implode("\n", $log));
            msg(
                'SMTP log:<br /><pre>' . hsc($log) .
                '</pre><b>Above may contain passwords - do not post online!</b>',
                -1
            );
        }

        // finish event handling
        $event->preventDefault();
        $event->stopPropagation();
        $event->result = $ok;
        $event->data['success'] = $ok;
    }

    /**
     * Configure authentication on the SMTP connection
     *
     * Depending on the configured authentication type this either uses
     * username/password (AUTH LOGIN) or an OAuth 2.0 access token (XOAUTH2).
     *
     * @param SMTP $smtp
     * @return bool false when authentication could not be set up and sending must abort
     */
    protected function setupAuth(SMTP $smtp)
    {
        if ($this->getConf('auth_type') === 'oauth') {
            try {
                $token = $this->getOAuth()->getAccessToken();
            } catch (\Exception $e) {
                msg('SMTP OAuth error: ' . hsc($e->getMessage()), -1);
                return false;
            }
            $smtp->setOAuth($token, 'XOAUTH2');
            return true;
        }

        if ($this->getConf('auth_user')) {
            $smtp->setAuth(
                $this->getConf('auth_user'),
                $this->getConf('auth_pass')
            );
        }
        return true;
    }

    /**
     * Intercept the OAuth provider's redirect back to the wiki
     *
     * The provider redirects to the (query-less) wiki base URL, so the callback
     * can land on any action. We recognize our own callback by the state value
     * stored in the session before the consent redirect and then exchange the
     * returned authorization code for tokens.
     *
     * @param Event $event
     * @param mixed $param
     * @return void
     */
    public function handleOAuthCallback(Event $event, $param)
    {
        global $INPUT;

        // is this potentially our callback?
        if (!$INPUT->has('code') && !$INPUT->has('error')) return;

        $this->startSession();
        $expected = $_SESSION[DOKU_COOKIE]['smtp_oauth_state'] ?? '';
        if (!$expected || $INPUT->str('state') !== $expected) return;

        // it is ours - consume the state so it cannot be replayed
        unset($_SESSION[DOKU_COOKIE]['smtp_oauth_state']);

        $adminUrl = wl('', array('do' => 'admin', 'page' => 'smtp'), true, '&');

        if ($INPUT->has('error')) {
            msg(
                $this->getLang('oauth_failed') . ' ' .
                hsc($INPUT->str('error_description', $INPUT->str('error'), true)),
                -1
            );
            send_redirect($adminUrl);
        }

        try {
            $this->getOAuth()->handleCallback($INPUT->str('code'));
            msg($this->getLang('oauth_connected'), 1);
        } catch (\Exception $e) {
            msg($this->getLang('oauth_failed') . ' ' . hsc($e->getMessage()), -1);
        }
        send_redirect($adminUrl);
    }

    /**
     * Build a configured OAuth helper
     *
     * @return OAuth
     */
    public function getOAuth()
    {
        $conf = array(
            'oauth_provider'      => $this->getConf('oauth_provider'),
            'oauth_client_id'     => $this->getConf('oauth_client_id'),
            'oauth_client_secret' => $this->getConf('oauth_client_secret'),
            'oauth_authurl'       => $this->getConf('oauth_authurl'),
            'oauth_tokenurl'      => $this->getConf('oauth_tokenurl'),
            'oauth_scope'         => $this->getConf('oauth_scope'),
        );
        return new OAuth($conf, new TokenStore(), self::getRedirectUri());
    }

    /**
     * The redirect URI the user has to register with the OAuth provider
     *
     * Must not contain a query string, as several providers (Google) reject
     * such redirect URIs. It therefore points at the bare wiki entry point.
     *
     * @return string
     */
    public static function getRedirectUri()
    {
        return DOKU_URL . 'doku.php';
    }

    /**
     * Make sure a session is available for reading/writing
     *
     * @return void
     */
    protected function startSession()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
    }
}
