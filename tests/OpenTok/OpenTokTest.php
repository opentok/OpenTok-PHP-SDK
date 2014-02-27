<?php

use Guzzle\Plugin\Mock\MockPlugin;
use Guzzle\Http\Client;

use OpenTok\OpenTok;
use OpenTok\Session;

use OpenTok\TestHelpers;

class OpenTokTest extends PHPUnit_Framework_TestCase
{
    protected $API_KEY;
    protected $API_SECRET;
    protected $opentok;
    protected $client;
    protected static $mockBasePath;

    public static function setUpBeforeClass()
    {
        self::$mockBasePath = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'mock' . DIRECTORY_SEPARATOR;
    }

    public function setUp()
    {
        // TODO: define the fake credentials somewhere outside the test code
        $this->API_KEY = (null !== API_KEY) ? API_KEY : '12345678';
        $this->API_SECRET = (null !== API_SECRET) ? API_SECRET : '0123456789abcdef0123456789abcdef0123456789';

        $this->client = new Client();
        $this->opentok = new OpenTok($this->API_KEY, $this->API_SECRET, array('client' => $this->client));

    }

    public function testCanBeInitialized()
    {
        // Arrange
        // Act
        // Assert
        $this->assertInstanceOf('OpenTok\OpenTok', $this->opentok);
    }

    /**
     * @expectedException PHPUnit_Framework_Error_Warning
     */
    public function testFailsOnInvalidInitialization()
    {
        // Arrange
        $opentok = new OpenTok();
        // Act
        // Assert
    }

    public function testCreatesSession()
    {
        // Arrange
        $mock = new MockPlugin();
        $response = MockPlugin::getMockFile(
            self::$mockBasePath . 'session/create/no-p2p_location-127.0.0.1'
        );
        $mock->addResponse($response);
        $this->client->addSubscriber($mock);

        // Act
        $session = $this->opentok->createSession('127.0.0.1', array(
            'p2p.preference' => 'disabled'
        ));

        // Assert
        $requests = $mock->getReceivedRequests();
        $this->assertCount(1, $requests);

        $request = $requests[0];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/session/create', $request->getPath());
        $this->assertEquals('api.opentok.com', $request->getHost());
        $this->assertEquals('https', $request->getScheme());

        $authString = $request->getHeader('X-TB-PARTNER-AUTH');
        $this->assertNotEmpty($authString);
        $this->assertEquals($this->API_KEY.':'.$this->API_SECRET, $authString);

        // TODO: test the dynamically built User Agent string
        $userAgent = $request->getHeader('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/2.0.0-beta', $userAgent->__toString());

        $location = $request->getPostField('location');
        $this->assertEquals('127.0.0.1', $location);

        $p2p_preference = $request->getPostField('p2p.preference');
        $this->assertEquals('disabled', $p2p_preference);

        $this->assertInstanceOf('OpenTok\Session', $session);
        // NOTE: this is an actual sessionId from the recorded response, this doesn't correspond to
        // the API Key and API Secret used to create the session.
        $this->assertEquals(
            '2_MX4xNzAxMjYzMX4xMjcuMC4wLjF-V2VkIEZlYiAyNiAxODo1NzoyNCBQU1QgMjAxNH4wLjU0MDU4ODc0fg',
            $session->sessionId
        );

        return $session;
    }

    public function testGeneratesToken() {
        // Arrange
        // This sessionId is a fixture designed by using a known but bogus apiKey and apiSecret
        $sessionId = '1_MX4xMjM0NTY3OH4-VGh1IEZlYiAyNyAwNDozODozMSBQU1QgMjAxNH4wLjI0NDgyMjI';
        $bogusApiKey = '12345678';
        $bogusApiSecret = '0123456789abcdef0123456789abcdef0123456789';
        $opentok = new OpenTok($bogusApiKey, $bogusApiSecret);

        // Act
        $token = $opentok->generateToken($sessionId);

        // Assert
        $this->assertInternalType('string', $token);

        $decodedToken = TestHelpers::decodeToken($token);
        $this->assertEquals($sessionId, $decodedToken['session_id']);
        $this->assertEquals($bogusApiKey, $decodedToken['partner_id']);
        $this->assertNotEmpty($decodedToken['nonce']);
        $this->assertNotEmpty($decodedToken['create_time']);
        // TODO: should all tokens have a role of publisher even if this wasn't specified?
        //$this->assertNotEmpty($decodedToken['role']);
        // TODO: should all tokens have a default expire time even if it wasn't specified?
        //$this->assertNotEmpty($decodedToken['expire_time']);

        $this->assertNotEmpty($decodedToken['sig']);
        $this->assertEquals(hash_hmac('sha1', $decodedToken['dataString'], $bogusApiSecret), $decodedToken['sig']);
    }
}
/* vim: set ts=4 sw=4 tw=100 sts=4 et :*/
