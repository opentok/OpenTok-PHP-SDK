<?php

namespace OpenTokTest;

use OpenTok\OpenTok;
use OpenTok\Session;
use OpenTok\MediaMode;
use OpenTok\ArchiveMode;
use PHPUnit\Framework\TestCase;

class SessionTest extends TestCase
{
    protected $API_KEY;
    protected $API_SECRET;
    protected $opentok;

    protected static $mockBasePath;

    public static function setUpBeforeClass(): void
    {
        self::$mockBasePath = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'mock' . DIRECTORY_SEPARATOR;
    }

    public function setUp(): void
    {
        $this->API_KEY = defined('API_KEY') ? API_KEY : '12345678';
        $this->API_SECRET = defined('API_SECRET') ? API_SECRET : 'b60d0b2568f3ea9731bd9d3f71be263ce19f802f';
        $this->opentok = new OpenTok($this->API_KEY, $this->API_SECRET);
    }

    public function testSessionWithId(): void
    {
        $sessionId = 'SESSIONID';
        $session = new Session($this->opentok, $sessionId);
        $this->assertEquals($sessionId, $session->getSessionId());
        $this->assertEquals(MediaMode::ROUTED, $session->getMediaMode());
        $this->assertEmpty($session->getLocation());
    }

    public function testSessionWithIdAndLocation(): void
    {
        $sessionId = 'SESSIONID';
        $location = '12.34.56.78';
        $session = new Session($this->opentok, $sessionId, ['location' => $location]);
        $this->assertEquals($sessionId, $session->getSessionId());
        $this->assertEquals(MediaMode::ROUTED, $session->getMediaMode());
        $this->assertEquals($location, $session->getLocation());
    }

    public function testSessionWithIdAndMediaMode(): void
    {
        $sessionId = 'SESSIONID';
        $mediaMode = MediaMode::RELAYED;
        $session = new Session($this->opentok, $sessionId, ['mediaMode' => $mediaMode]);
        $this->assertEquals($sessionId, $session->getSessionId());
        $this->assertEquals($mediaMode, $session->getMediaMode());
        $this->assertEmpty($session->getLocation());

        $mediaMode = MediaMode::ROUTED;
        $session = new Session($this->opentok, $sessionId, ['mediaMode' => $mediaMode]);
        $this->assertEquals($sessionId, $session->getSessionId());
        $this->assertEquals($mediaMode, $session->getMediaMode());
        $this->assertEmpty($session->getLocation());
    }

    public function testSessionWithIdAndLocationAndMediaMode(): void
    {
        $sessionId = 'SESSIONID';
        $location = '12.34.56.78';
        $mediaMode = MediaMode::RELAYED;
        $session = new Session($this->opentok, $sessionId, ['location' => $location, 'mediaMode' => $mediaMode]);
        $this->assertEquals($sessionId, $session->getSessionId());
        $this->assertEquals($mediaMode, $session->getMediaMode());
        $this->assertEquals($location, $session->getLocation());

        $mediaMode = MediaMode::ROUTED;
        $session = new Session($this->opentok, $sessionId, ['location' => $location, 'mediaMode' => $mediaMode]);
        $this->assertEquals($sessionId, $session->getSessionId());
        $this->assertEquals($mediaMode, $session->getMediaMode());
        $this->assertEquals($location, $session->getLocation());
    }

    public function testSessionWithArchiveMode(): void
    {
        $sessionId = 'SESSIONID';
        $archiveMode = ArchiveMode::ALWAYS;
        $session = new Session($this->opentok, $sessionId, ['archiveMode' => $archiveMode]);
        $this->assertEquals($sessionId, $session->getSessionId());
        $this->assertEquals($archiveMode, $session->getArchiveMode());

        $archiveMode = ArchiveMode::MANUAL;
        $session = new Session($this->opentok, $sessionId, ['archiveMode' => $archiveMode]);
        $this->assertEquals($sessionId, $session->getSessionId());
        $this->assertEquals($archiveMode, $session->getArchiveMode());
    }

    /**
     * @dataProvider badParameterProvider
     */
    public function testInitializationWithBadParams(string|array $sessionId, array $props): void
    {
        $this->expectException('InvalidArgumentException');
        if (!$props || $props === []) {
            $session = new Session($this->opentok, $sessionId);
        } else {
            $session = new Session($this->opentok, $sessionId, $props);
        }
    }

    public function badParameterProvider(): array
    {
        return [[[], []], ['SESSIONID', ['location' => 'NOTALOCATION']], ['SESSIONID', ['mediaMode' => 'NOTAMODE']], ['SESSIONID', ['location' => '127.0.0.1', 'mediaMode' => 'NOTAMODE']], ['SESSIONID', ['location' => 'NOTALOCATION', 'mediaMode' => MediaMode::RELAYED]]];
    }

    public function testInitialzationWithoutE2ee(): void
    {
        $sessionId = 'SESSIONID';
        $session = new Session($this->opentok, $sessionId);
        $this->assertEquals(false, $session->getE2EE());
    }

    public function testInitialzationWithE2ee(): void
    {
        $sessionId = 'SESSIONID';
        $session = new Session($this->opentok, $sessionId, ['e2ee' => true]);
        $this->assertEquals(true, $session->getE2EE());
    }

    public function testInitializationWithExtraneousParams(): void
    {
        $sessionId = 'SESSIONID';
        $session = new Session($this->opentok, $sessionId, ['notrealproperty' => 'notrealvalue']);
        $this->assertEquals($sessionId, $session->getSessionId());
        $this->assertEmpty($session->getLocation());
        $this->assertEquals(MediaMode::ROUTED, $session->getMediaMode());
    }

    public function testCastingToString(): void
    {
        $sessionId = 'SESSIONID';
        $session = new Session($this->opentok, $sessionId);
        $this->assertEquals($sessionId, (string)$session);
    }

    public function testGeneratesToken(): void
    {
        $sessionId = '1_MX4xMjM0NTY3OH4-VGh1IEZlYiAyNyAwNDozODozMSBQU1QgMjAxNH4wLjI0NDgyMjI';
        $bogusApiKey = '12345678';
        $bogusApiSecret = 'b60d0b2568f3ea9731bd9d3f71be263ce19f802f';
        $opentok = new OpenTok($bogusApiKey, $bogusApiSecret);
        $session = new Session($opentok, $sessionId);

        $token = $session->generateToken([], true);

        $this->assertIsString($token);
        $decodedToken = TestHelpers::decodeToken($token);
        $this->assertEquals($sessionId, $decodedToken['session_id']);
        $this->assertEquals($bogusApiKey, $decodedToken['partner_id']);
        $this->assertNotEmpty($decodedToken['nonce']);
        $this->assertNotEmpty($decodedToken['create_time']);
        $this->assertArrayNotHasKey('connection_data', $decodedToken);
        // TODO: should all tokens have a role of publisher even if this wasn't specified?
        //$this->assertNotEmpty($decodedToken['role']);
        // TODO: should all tokens have a default expire time even if it wasn't specified?
        //$this->assertNotEmpty($decodedToken['expire_time']);

        $this->assertNotEmpty($decodedToken['sig']);
        $this->assertEquals(hash_hmac('sha1', (string) $decodedToken['dataString'], $bogusApiSecret), $decodedToken['sig']);
    }
}