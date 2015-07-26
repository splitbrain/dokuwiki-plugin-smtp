<?php

namespace splitbrain\dokuwiki\plugin\smtp;

/**
 * Class Message
 *
 * Overrides the Message class with what we need to reuse the SMTP mailer without using
 * their message composer
 *
 * @package splitbrain\dokuwiki\plugin\smtp
 */
class Message extends \Tx\Mailer\Message {

    protected $from;
    protected $to;
    protected $body;

    /**
     * @param $from
     * @param $to
     * @param $body
     */
    public function __construct($from, $to, $body) {
        $this->from = $from;
        $this->to = $to;
        $this->body = $body;
    }

    /**
     * @return string
     */
    public function getFromEmail() {
        return $this->from;
    }

    /**
     * @return array
     */
    public function getTo() {
        $rcpt = explode(',', $this->to); //FIXME this needs to be improved
        $rcpt = array_filter($rcpt);
        $rcpt = array_unique($rcpt);
        return $rcpt;
    }

    /**
     * @return string
     */
    public function toString() {
        // FIXME we need to remove the BCC fields here

        return $this->body . $this->CRLF . $this->CRLF . "." . $this->CRLF;
    }

}
