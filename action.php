<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Extension\Event;
use splitbrain\dokuwiki\plugin\smtp\Message;
use splitbrain\dokuwiki\plugin\smtp\Logger;
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
    }

    /**
     * [Custom event handler which performs action]
     *
     * @param Event $event event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
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
        if ($this->getConf('auth_user')) {
            $smtp->setAuth(
                $this->getConf('auth_user'),
                $this->getConf('auth_pass')
            );
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
}
