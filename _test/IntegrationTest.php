<?php

namespace dokuwiki\plugin\smtp\test;

use DokuWikiTest;
use dokuwiki\HTTP\DokuHTTPClient;
use Mailer;

/**
 * Full integration test for the smtp plugin
 *
 * This sends a real mail through DokuWiki's Mailer (which the plugin intercepts and
 * delivers over SMTP) to a running Mailpit server and then verifies through Mailpit's
 * HTTP API that the message was delivered correctly.
 *
 * The Mailpit server is configured through environment variables:
 *   MAILPIT_HOST       hostname of the Mailpit server (enables the test)
 *   MAILPIT_SMTP_PORT  the SMTP port to deliver to         (default: 1025)
 *   MAILPIT_HTTP_PORT  the HTTP API/web interface port     (default: 8025)
 *
 * When MAILPIT_HOST is not set the test is skipped, so it does not break the regular
 * test suite which runs without the Mailpit service. When it is set the server must be
 * reachable, otherwise the test fails.
 *
 * The STARTTLS test (smtp_allow_insecure) uses the same server, so that Mailpit must be
 * started with --smtp-tls-cert/--smtp-tls-key to offer STARTTLS (a self-signed
 * certificate is exactly what this test wants to exercise); otherwise it fails.
 *
 * @group plugin_smtp
 * @group plugins
 */
class IntegrationTest extends DokuWikiTest
{
    protected $pluginsEnabled = ['smtp'];

    /** @var string hostname of the Mailpit server */
    protected $host;

    /** @var int SMTP port to deliver to */
    protected $smtpPort;

    /** @var string base URL of the Mailpit HTTP API, without trailing slash */
    protected $apiBase;

    /** @inheritdoc */
    public function setUp(): void
    {
        parent::setUp();

        // the test only runs when a Mailpit server is configured via MAILPIT_HOST,
        // otherwise it is skipped (eg. regular CI/local runs without Mailpit)
        $this->host = getenv('MAILPIT_HOST');
        if ($this->host === false || $this->host === '') {
            $this->markTestSkipped('MAILPIT_HOST is not set, skipping the Mailpit integration test');
        }

        $this->smtpPort = (int)(getenv('MAILPIT_SMTP_PORT') ?: 1025);
        $httpPort = (int)(getenv('MAILPIT_HTTP_PORT') ?: 8025);
        $this->apiBase = 'http://' . $this->host . ':' . $httpPort;

        // a Mailpit server was requested, so it must actually be reachable
        $sock = @fsockopen($this->host, $this->smtpPort, $errno, $errstr, 2);
        if (!$sock) {
            $this->fail("No Mailpit SMTP server reachable at $this->host:$this->smtpPort ($errstr)");
        }
        fclose($sock);

        // point the plugin at our Mailpit server
        global $conf;
        $conf['plugin']['smtp']['smtp_host'] = $this->host;
        $conf['plugin']['smtp']['smtp_port'] = $this->smtpPort;
        $conf['plugin']['smtp']['smtp_ssl'] = '';
        $conf['plugin']['smtp']['auth_user'] = '';
        $conf['plugin']['smtp']['auth_pass'] = '';
        $conf['plugin']['smtp']['localdomain'] = 'test.localhost';
        $conf['plugin']['smtp']['debug'] = 0;

        // give DokuWiki's Mailer a sane default sender
        $conf['mailfrom'] = 'wiki@example.com';
    }

    /**
     * A mail sent through DokuWiki's Mailer must be delivered to Mailpit over SMTP.
     *
     * To and Cc come from the message headers, while the Bcc recipient is delivered
     * through the SMTP envelope even though the plugin strips the Bcc header from the
     * message body.
     */
    public function testSendMail(): void
    {
        $subject = 'SMTP plugin integration test ' . uniqid('', true);
        $bodyText = 'Hello from the DokuWiki smtp plugin integration test.';

        $mailer = new Mailer();
        $mailer->from('Wiki Admin <wiki@example.com>');
        $mailer->to('Jane Doe <jane@example.com>');
        $mailer->cc('carol@example.com');
        $mailer->bcc('secret@example.com');
        $mailer->subject($subject);
        $mailer->setBody($bodyText);

        $ok = $mailer->send();
        $this->assertTrue($ok, 'Mailer::send() should report success when talking to Mailpit');

        // the message should now be available through the Mailpit API
        $message = $this->findMessageBySubject($subject);
        $this->assertNotNull($message, 'The sent message should show up in Mailpit');

        // sender and header recipients
        $this->assertEquals('wiki@example.com', $message['From']['Address']);
        $this->assertEquals(['jane@example.com'], $this->addresses($message['To']));
        $this->assertEquals(['carol@example.com'], $this->addresses($message['Cc']));
        $this->assertEquals(['secret@example.com'], $this->addresses($message['Bcc']));

        // the body must have arrived intact
        $full = $this->apiGet('/api/v1/message/' . rawurlencode($message['ID']));
        $this->assertStringContainsString($bodyText, $full['Text']);
    }

    /**
     * The smtp_allow_insecure option must control whether an untrusted STARTTLS
     * certificate is accepted (issue #32).
     *
     * Against a Mailpit offering STARTTLS with a self-signed certificate, sending must
     * fail while certificate verification is on (smtp_allow_insecure off) and succeed
     * once verification is disabled (smtp_allow_insecure on).
     */
    public function testSendMailTlsAllowsInsecureCert(): void
    {
        // the same server and HTTP API as the plain test, just talked to over STARTTLS
        global $conf;
        $conf['plugin']['smtp']['smtp_ssl'] = 'tls';

        // with certificate verification enabled the self-signed cert is rejected
        $conf['plugin']['smtp']['smtp_allow_insecure'] = 0;
        $rejectSubject = 'SMTP plugin TLS reject test ' . uniqid('', true);
        $this->assertFalse(
            $this->sendProbe($rejectSubject),
            'Sending over STARTTLS with an untrusted certificate must fail when smtp_allow_insecure is off'
        );
        $this->assertNull(
            $this->findMessageBySubject($rejectSubject),
            'A message rejected during STARTTLS must not be delivered'
        );

        // disabling verification accepts the self-signed cert and delivers the mail
        $conf['plugin']['smtp']['smtp_allow_insecure'] = 1;
        $acceptSubject = 'SMTP plugin TLS insecure test ' . uniqid('', true);
        $this->assertTrue(
            $this->sendProbe($acceptSubject),
            'Sending over STARTTLS with a self-signed certificate must succeed when smtp_allow_insecure is on'
        );
        $this->assertNotNull(
            $this->findMessageBySubject($acceptSubject),
            'The mail sent over insecure STARTTLS should show up in Mailpit'
        );
    }

    /**
     * Send a minimal mail through DokuWiki's Mailer and report whether it succeeded
     *
     * An untrusted certificate makes the mailer library emit a PHP SSL warning deep in
     * the call stack; it is swallowed here so the test can observe the boolean send
     * result rather than abort on the warning.
     *
     * @param string $subject unique subject for the probe mail
     * @return bool whether the mail was sent successfully
     */
    protected function sendProbe(string $subject): bool
    {
        $mailer = new Mailer();
        $mailer->from('Wiki Admin <wiki@example.com>');
        $mailer->to('Jane Doe <jane@example.com>');
        $mailer->subject($subject);
        $mailer->setBody('STARTTLS integration probe.');

        set_error_handler(static fn() => true);
        try {
            return (bool)$mailer->send();
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Reduce a Mailpit address list to a plain list of email addresses
     *
     * @param array $list list of {Name, Address} entries as returned by Mailpit
     * @return string[]
     */
    protected function addresses(array $list): array
    {
        return array_map(static fn($addr) => $addr['Address'], $list);
    }

    /**
     * Find a message in Mailpit whose subject contains the given needle
     *
     * DokuWiki prefixes the subject with the wiki title (eg. "[My Wiki] ..."), so we
     * match on a unique substring rather than the full subject. Mailpit stores the
     * message before acknowledging the SMTP DATA command, so it is available as soon
     * as Mailer::send() returns - no polling needed.
     *
     * @param string $needle a unique substring of the subject
     * @return array|null the message stub from the listing or null if not found
     */
    protected function findMessageBySubject($needle)
    {
        $list = $this->apiGet('/api/v1/messages');
        foreach ($list['messages'] as $msg) {
            if (strpos($msg['Subject'], $needle) !== false) return $msg;
        }
        return null;
    }

    /**
     * Perform a GET request against the Mailpit API and return the decoded JSON
     *
     * @param string $path API path including the leading slash
     * @return array
     */
    protected function apiGet($path)
    {
        $client = new DokuHTTPClient();
        $body = $client->get($this->apiBase . $path);
        $this->assertNotFalse($body, 'Mailpit API request to ' . $path . ' failed: ' . $client->error);
        return json_decode($body, true);
    }
}
