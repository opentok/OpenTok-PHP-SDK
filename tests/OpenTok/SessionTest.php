<?php

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;

use OpenTok\OpenTok;
use OpenTok\OpenTokTestCase;
use OpenTok\Session;
use OpenTok\Stream;
use OpenTok\StreamList;
use OpenTok\MediaMode;
use OpenTok\ArchiveMode;

use OpenTok\TestHelpers;
use OpenTok\Util\Client;


class SessionTest extends PHPUnit_Framework_TestCase
{
    protected $API_KEY;
    protected $API_SECRET;
    protected $opentok;

    protected static $mockBasePath;
    
    public static function setUpBeforeClass()
    {
        self::$mockBasePath = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'mock' . DIRECTORY_SEPARATOR;
    }
    
    public function setUp()
    {
        $this->API_KEY = defined('API_KEY') ? API_KEY : '12345678';
        $this->API_SECRET = defined('API_SECRET') ? API_SECRET : '0123456789abcdef0123456789abcdef0123456789';
        $this->opentok = new OpenTok($this->API_KEY, $this->API_SECRET);
    }


    private function setupOTWithMocks($mocks)
    {
        $this->API_KEY = defined('API_KEY') ? API_KEY : '12345678';
        $this->API_SECRET = defined('API_SECRET') ? API_SECRET : '0123456789abcdef0123456789abcdef0123456789';

        if (is_array($mocks)) {
            $responses = TestHelpers::mocksToResponses($mocks, self::$mockBasePath);
        } else {
            $responses = [];
        }

        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        $clientOptions = [
            'handler' => $handlerStack
        ];

        $this->client = new Client();
        $this->client->configure(
            $this->API_KEY,
            $this->API_SECRET,
            'https://api.opentok.com',
            $clientOptions
        );

        // Push history onto handler stack *after* configuring client to
        // ensure auth header is added before history handler is invoked
        $this->historyContainer = [];
        $history = Middleware::history($this->historyContainer);
        $handlerStack->push($history);

        $this->opentok = new OpenTok($this->API_KEY, $this->API_SECRET, array('client' => $this->client));
    }

    public function testSessionWithId()
    {
        $sessionId = 'SESSIONID';
        $session = new Session($this->opentok, $sessionId);
        $this->assertEquals($sessionId, $session->getSessionId());
        $this->assertEquals(MediaMode::ROUTED, $session->getMediaMode());
        $this->assertEmpty($session->getLocation());
    }

    public function testSessionWithIdAndLocation()
    {
        $sessionId = 'SESSIONID';
        $location = '12.34.56.78';
        $session = new Session($this->opentok, $sessionId, array( 'location' => $location ));
        $this->assertEquals($sessionId, $session->getSessionId());
        $this->assertEquals(MediaMode::ROUTED, $session->getMediaMode());
        $this->assertEquals($location, $session->getLocation());
    }

    public function testSessionWithIdAndMediaMode()
    {
        $sessionId = 'SESSIONID';
        $mediaMode = MediaMode::RELAYED;
        $session = new Session($this->opentok, $sessionId, array( 'mediaMode' => $mediaMode ));
        $this->assertEquals($sessionId, $session->getSessionId());
        $this->assertEquals($mediaMode, $session->getMediaMode());
        $this->assertEmpty($session->getLocation());

        $mediaMode = MediaMode::ROUTED;
        $session = new Session($this->opentok, $sessionId, array( 'mediaMode' => $mediaMode ));
        $this->assertEquals($sessionId, $session->getSessionId());
        $this->assertEquals($mediaMode, $session->getMediaMode());
        $this->assertEmpty($session->getLocation());
    }

    public function testSessionWithIdAndLocationAndMediaMode()
    {
        $sessionId = 'SESSIONID';
        $location = '12.34.56.78';
        $mediaMode = MediaMode::RELAYED;
        $session = new Session($this->opentok, $sessionId, array( 'location' => $location, 'mediaMode' => $mediaMode ));
        $this->assertEquals($sessionId, $session->getSessionId());
        $this->assertEquals($mediaMode, $session->getMediaMode());
        $this->assertEquals($location, $session->getLocation());

        $mediaMode = MediaMode::ROUTED;
        $session = new Session($this->opentok, $sessionId, array( 'location' => $location, 'mediaMode' => $mediaMode ));
        $this->assertEquals($sessionId, $session->getSessionId());
        $this->assertEquals($mediaMode, $session->getMediaMode());
        $this->assertEquals($location, $session->getLocation());
    }

    public function testSessionWithArchiveMode()
    {
        $sessionId = 'SESSIONID';
        $archiveMode = ArchiveMode::ALWAYS;
        $session = new Session($this->opentok, $sessionId, array( 'archiveMode' => $archiveMode ));
        $this->assertEquals($sessionId, $session->getSessionId());
        $this->assertEquals($archiveMode, $session->getArchiveMode());

        $archiveMode = ArchiveMode::MANUAL;
        $session = new Session($this->opentok, $sessionId, array( 'archiveMode' => $archiveMode ));
        $this->assertEquals($sessionId, $session->getSessionId());
        $this->assertEquals($archiveMode, $session->getArchiveMode());
    }

    /**
     * @dataProvider badParameterProvider
     * @expectedException InvalidArgumentException
     */
    public function testInitializationWithBadParams($sessionId, $props)
    {
        if (!$props || empty($props)) {
            $session = new Session($this->opentok, $sessionId);
        } else {
            $session = new Session($this->opentok, $sessionId, $props);
        }
    }

    public function badParameterProvider()
    {
        return array(
            array(array(), array()),
            array('SESSIONID', array( 'location' => 'NOTALOCATION') ),
            array('SESSIONID', array( 'mediaMode' => 'NOTAMODE' ) ),
            array('SESSIONID', array( 'location' => '127.0.0.1', 'mediaMode' => 'NOTAMODE' ) ),
            array('SESSIONID', array( 'location' => 'NOTALOCATION', 'mediaMode' => MediaMode::RELAYED ) )
        );
    }

    public function testInitializationWithExtraneousParams()
    {
        $sessionId = 'SESSIONID';
        $session = new Session($this->opentok, $sessionId, array( 'notrealproperty' => 'notrealvalue' ));
        $this->assertEquals($sessionId, $session->getSessionId());
        $this->assertEmpty($session->getLocation());
        $this->assertEquals(MediaMode::ROUTED, $session->getMediaMode());
    }

    public function testCastingToString()
    {
        $sessionId = 'SESSIONID';
        $session = new Session($this->opentok, $sessionId);
        $this->assertEquals($sessionId, (string)$session);
    }

    public function testGeneratesToken()
    {
        $sessionId = '1_MX4xMjM0NTY3OH4-VGh1IEZlYiAyNyAwNDozODozMSBQU1QgMjAxNH4wLjI0NDgyMjI';
        $bogusApiKey = '12345678';
        $bogusApiSecret = '0123456789abcdef0123456789abcdef0123456789';
        $opentok = new OpenTok($bogusApiKey, $bogusApiSecret);
        $session = new Session($opentok, $sessionId);

        $token = $session->generateToken();

        $this->assertInternalType('string', $token);
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
        $this->assertEquals(hash_hmac('sha1', $decodedToken['dataString'], $bogusApiSecret), $decodedToken['sig']);
    }

}
/* vim: set ts=4 sw=4 tw=100 sts=4 et :*/
