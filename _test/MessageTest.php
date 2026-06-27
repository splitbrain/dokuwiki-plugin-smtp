<?php

namespace dokuwiki\plugin\smtp\test;

use DokuWikiTest;
use splitbrain\dokuwiki\plugin\smtp\Message;

/**
 * Message tests for the smtp plugin
 *
 * @group plugin_smtp
 * @group plugins
 */
class MessageTest extends DokuWikiTest
{
    /**
     * The Bcc header, including folded continuation lines, must be stripped from the body
     */
    public function testBody(): void
    {
        $input = implode("\r\n", [
            'X-Mailer: DokuWiki',
            'X-Dokuwiki-User: admin',
            'X-Dokuwiki-Title: Test Wiki',
            'X-Dokuwiki-Server: localhost.localhost',
            'From: a@example.com',
            'To: b@example.com',
            'Bcc: c@example.com, d@example.com,',
            '     d@example.com',
            'Subject: A test',
            '',
            'This is the body of the mail',
            'Bcc: this is not a header line',
            'end of message',
        ]);

        $expect = implode("\r\n", [
            'X-Mailer: DokuWiki',
            'X-Dokuwiki-User: admin',
            'X-Dokuwiki-Title: Test Wiki',
            'X-Dokuwiki-Server: localhost.localhost',
            'From: a@example.com',
            'To: b@example.com',
            'Subject: A test',
            '',
            'This is the body of the mail',
            'Bcc: this is not a header line',
            'end of message',
        ]);
        $expect .= "\r\n\r\n.\r\n";

        $message = new Message('', '', $input);

        $this->assertEquals($expect, $message->toString());
    }
}
