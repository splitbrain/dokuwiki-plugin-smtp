<?php

namespace dokuwiki\plugin\smtp\test;

use DokuWikiTest;
use splitbrain\dokuwiki\plugin\smtp\TokenStore;

/**
 * TokenStore tests for the smtp plugin
 *
 * @group plugin_smtp
 * @group plugins
 */
class TokenStoreTest extends DokuWikiTest
{
    protected $pluginsEnabled = ['smtp'];

    /** @var string */
    protected $file;

    public function setUp(): void
    {
        parent::setUp();
        $this->file = tempnam(sys_get_temp_dir(), 'smtp_oauth_');
        // start from a clean slate, the file must not exist yet
        @unlink($this->file);
    }

    public function tearDown(): void
    {
        @unlink($this->file);
        parent::tearDown();
    }

    /**
     * Storing, reading and clearing tokens must survive new instances
     */
    public function testRoundtrip(): void
    {
        $store = new TokenStore($this->file);
        $this->assertFalse($store->isAuthorized());
        $this->assertNull($store->get('access_token'));
        $this->assertSame('fallback', $store->get('missing', 'fallback'));

        $store->set(['refresh_token' => 'R', 'access_token' => 'A', 'expires_at' => 1234567890]);
        $this->assertTrue($store->isAuthorized());

        // a fresh instance must read the persisted data from disk
        $reloaded = new TokenStore($this->file);
        $this->assertTrue($reloaded->isAuthorized());
        $this->assertSame('R', $reloaded->get('refresh_token'));
        $this->assertSame('A', $reloaded->get('access_token'));
        $this->assertSame(1234567890, $reloaded->get('expires_at'));
    }

    /**
     * set() must merge rather than replace
     */
    public function testMerge(): void
    {
        $store = new TokenStore($this->file);
        $store->set(['refresh_token' => 'R', 'access_token' => 'A']);
        $store->set(['access_token' => 'B', 'expires_at' => 42]);

        $reloaded = new TokenStore($this->file);
        $this->assertSame('R', $reloaded->get('refresh_token'));
        $this->assertSame('B', $reloaded->get('access_token'));
        $this->assertSame(42, $reloaded->get('expires_at'));
    }

    /**
     * clear() removes all stored data
     */
    public function testClear(): void
    {
        $store = new TokenStore($this->file);
        $store->set(['refresh_token' => 'R']);
        $this->assertTrue($store->isAuthorized());

        $store->clear();
        $this->assertFalse($store->isAuthorized());
        $this->assertFalse(file_exists($this->file));

        $reloaded = new TokenStore($this->file);
        $this->assertFalse($reloaded->isAuthorized());
    }
}
