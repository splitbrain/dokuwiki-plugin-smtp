<?php
/**
 * DokuWiki Plugin smtp (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <andi@splitbrain.org>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class action_plugin_smtp extends DokuWiki_Action_Plugin {

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     * @return void
     */
    public function register(Doku_Event_Handler $controller) {

       $controller->register_hook('MAIL_MESSAGE_SEND', 'BEFORE', $this, 'handle_mail_message_send');

    }

    /**
     * [Custom event handler which performs action]
     *
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return void
     */

    public function handle_mail_message_send(Doku_Event &$event, $param) {
        require_once __DIR__ . '/loader.php';

        /** @var Mailer $mailer Our Mailer with all the data */
        $mailer = $event->data['mail'];
        $to     = $event->data['to'].','.$event->data['cc'].','.$event->data['bcc'];
        $from   = $event->data['from'];

        $message = new \splitbrain\dokuwiki\plugin\smtp\Message(
            $from,
            $to,
            $mailer->dump()
        );

        $smtp = new \Tx\Mailer\SMTP();
        $smtp->setServer('localhost', 2525, null);

        $smtp->send($message);
    }

}

// vim:ts=4:sw=4:et:
