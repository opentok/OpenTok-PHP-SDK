<?php

use Guzzle\Plugin\Mock\MockPlugin;

use OpenTok\OpenTok;
use OpenTok\Session;
use OpenTok\Role;
use OpenTok\MediaMode;
use OpenTok\Util\Client;
use OpenTok\Exception\ArchiveAuthenticationException;

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
        $default_api_key = '12345678';
        $default_api_secret = '0123456789abcdef0123456789abcdef0123456789';
        $default_api_url = 'https://api.opentok.com';
        $default_session_id = '2_MX44NTQ1MTF-flR1ZSBOb3YgMTIgMDk6NDA6NTkgUFNUIDIwMTN-MC43NjU0Nzh-';

        $this->API_KEY = getenv('API_KEY') == '' ? $default_api_key : getenv('API_KEY');
        $this->API_SECRET = getenv('API_SECRET') == '' ? $default_api_secret : getenv('API_SECRET');
        $this->API_URL = getenv('API_URL') == '' ? $default_api_url : getenv('API_URL');
        $this->MOCK = getenv('NETWORK') == '' ? true: false;
        $this->SESSION_ID = $default_session_id;
        if ($this->MOCK) {
            if ($default_api_key != $this->API_KEY ||
                $default_api_secret != $this->API_SECRET ||
                $default_api_url != $this->API_URL)
            {
                throw new Exception('If NETWORK is set, API_KEY, API_SECRET and API_URL must not be set');
            }
        } else {
            if ($default_api_key == $this->API_KEY ||
                $default_api_secret == $this->API_SECRET)
            {
                throw new Exception('If NETWORK is not set, API_KEY and API_SECRET must not be set');
            }
        }
        $this->client = new Client();
        $this->opentok = new OpenTok($this->API_KEY, $this->API_SECRET,
            array('client' => $this->client, 'apiUrl' => $this->API_URL));
        if (!$this->MOCK) {
            $session = $this->opentok->createSession();
            $this->SESSION_ID = $session->getSessionId();
        }
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

    public function testCreatesDefaultSession()
    {
        // Arrange
        $mock = new MockPlugin();
        $response = MockPlugin::getMockFile(
            self::$mockBasePath . 'session/create/relayed'
        );
        $mock->addResponse($response);
        if ($this->MOCK)
        {
            $this->client->addSubscriber($mock);
        }

        // Act
        $session = $this->opentok->createSession();

        // Assert
        if ($this->MOCK)
        {
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
            $this->assertStringStartsWith('OpenTok-PHP-SDK/2.2.3-alpha.1', $userAgent->__toString());

            $p2p_preference = $request->getPostField('p2p.preference');
            $this->assertEquals('enabled', $p2p_preference);

            // NOTE: this is an actual sessionId from the recorded response, this doesn't correspond to
            // the API Key and API Secret used to create the session.
            $this->assertEquals(
                $this->SESSION_ID,
                $session->getSessionId()
            );
        } else {
            $decodedSession = TestHelpers::decodeSessionId($session->getSessionId());
            $this->assertEquals($decodedSession[1], $this->API_KEY);
        }
        // TODO: sessionId must be decoded and validated
        $this->assertInstanceOf('OpenTok\Session', $session);
    }

    public function testCreatesMediaRoutedAndLocationSession()
    {
        // Arrange
        $mock = new MockPlugin();
        $response = MockPlugin::getMockFile(
            self::$mockBasePath . 'session/create/routed_location-12.34.56.78'
        );
        $mock->addResponse($response);
        if ($this->MOCK)
        {
            $this->client->addSubscriber($mock);
        }

        // Act
        $session = $this->opentok->createSession(array(
            'location' => '12.34.56.78',
            'mediaMode' => MediaMode::ROUTED
        ));

        // Assert
        if ($this->MOCK)
        {
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
            $this->assertStringStartsWith('OpenTok-PHP-SDK/2.2.3-alpha.1', $userAgent->__toString());

            $location = $request->getPostField('location');
            $this->assertEquals('12.34.56.78', $location);

            $p2p_preference = $request->getPostField('p2p.preference');
            $this->assertEquals('disabled', $p2p_preference);

            // NOTE: this is an actual sessionId from the recorded response, this doesn't correspond to
            // the API Key and API Secret used to create the session.
            $this->assertEquals(
                $this->SESSION_ID,
                $session->getSessionId()
            );
        } else {
            $decodedSession = TestHelpers::decodeSessionId($session->getSessionId());
            $this->assertEquals($decodedSession[1], $this->API_KEY);
        }

        $this->assertInstanceOf('OpenTok\Session', $session);
    }

    public function testCreatesMediaRelayedSession()
    {
        // Arrange
        $mock = new MockPlugin();
        $response = MockPlugin::getMockFile(
            self::$mockBasePath . 'session/create/relayed'
        );
        $mock->addResponse($response);
        if ($this->MOCK)
        {
            $this->client->addSubscriber($mock);
        }
        // Act
        $session = $this->opentok->createSession(array(
            'mediaMode' => MediaMode::RELAYED
        ));

        // Assert
        if ($this->MOCK)
        {
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
            $this->assertStringStartsWith('OpenTok-PHP-SDK/2.2.3-alpha.1', $userAgent->__toString());

            $p2p_preference = $request->getPostField('p2p.preference');
            $this->assertEquals('enabled', $p2p_preference);

            
            // NOTE: this is an actual sessionId from the recorded response, this doesn't correspond to
            // the API Key and API Secret used to create the session.
            $this->assertEquals(
                $this->SESSION_ID,
                $session->getSessionId()
            );
        } else {
            $decodedSession = TestHelpers::decodeSessionId($session->getSessionId());
            $this->assertEquals($decodedSession[1], $this->API_KEY);
        }

        $this->assertInstanceOf('OpenTok\Session', $session);
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
        $this->assertArrayNotHasKey('connection_data', $decodedToken);
        // TODO: should all tokens have a role of publisher even if this wasn't specified?
        //$this->assertNotEmpty($decodedToken['role']);
        // TODO: should all tokens have a default expire time even if it wasn't specified?
        //$this->assertNotEmpty($decodedToken['expire_time']);

        $this->assertNotEmpty($decodedToken['sig']);
        $this->assertEquals(hash_hmac('sha1', $decodedToken['dataString'], $bogusApiSecret), $decodedToken['sig']);
    }

    public function testGeneratesTokenWithRole() {
        // Arrange
        // This sessionId is a fixture designed by using a known but bogus apiKey and apiSecret
        $sessionId = '1_MX4xMjM0NTY3OH4-VGh1IEZlYiAyNyAwNDozODozMSBQU1QgMjAxNH4wLjI0NDgyMjI';
        $bogusApiKey = '12345678';
        $bogusApiSecret = '0123456789abcdef0123456789abcdef0123456789';
        $opentok = new OpenTok($bogusApiKey, $bogusApiSecret);

        // Act
        $token = $opentok->generateToken($sessionId, array('role' => Role::MODERATOR));

        // Assert
        $this->assertInternalType('string', $token);

        $decodedToken = TestHelpers::decodeToken($token);
        $this->assertEquals($sessionId, $decodedToken['session_id']);
        $this->assertEquals($bogusApiKey, $decodedToken['partner_id']);
        $this->assertNotEmpty($decodedToken['nonce']);
        $this->assertNotEmpty($decodedToken['create_time']);
        $this->assertArrayNotHasKey('connection_data', $decodedToken);
        $this->assertEquals('moderator', $decodedToken['role']);
        // TODO: should all tokens have a default expire time even if it wasn't specified?
        //$this->assertNotEmpty($decodedToken['expire_time']);

        $this->assertNotEmpty($decodedToken['sig']);
        $this->assertEquals(hash_hmac('sha1', $decodedToken['dataString'], $bogusApiSecret), $decodedToken['sig']);
    }

    public function testGeneratesTokenWithExpireTime() {
        // Arrange
        // This sessionId is a fixture designed by using a known but bogus apiKey and apiSecret
        $sessionId = '1_MX4xMjM0NTY3OH4-VGh1IEZlYiAyNyAwNDozODozMSBQU1QgMjAxNH4wLjI0NDgyMjI';
        $bogusApiKey = '12345678';
        $bogusApiSecret = '0123456789abcdef0123456789abcdef0123456789';
        $opentok = new OpenTok($bogusApiKey, $bogusApiSecret);

        // Act
        // expires in one hour (60 seconds * 60 minutes)
        $inOneHour = time() + (60 * 60);
        $token = $opentok->generateToken($sessionId, array('expireTime' => $inOneHour ));

        // Assert
        $this->assertInternalType('string', $token);

        $decodedToken = TestHelpers::decodeToken($token);
        $this->assertEquals($sessionId, $decodedToken['session_id']);
        $this->assertEquals($bogusApiKey, $decodedToken['partner_id']);
        $this->assertNotEmpty($decodedToken['nonce']);
        $this->assertNotEmpty($decodedToken['create_time']);
        $this->assertArrayNotHasKey('connection_data', $decodedToken);
        $this->assertNotEmpty($decodedToken['role']);
        $this->assertEquals($inOneHour, $decodedToken['expire_time']);

        $this->assertNotEmpty($decodedToken['sig']);
        $this->assertEquals(hash_hmac('sha1', $decodedToken['dataString'], $bogusApiSecret), $decodedToken['sig']);
    }

    public function testGeneratesTokenWithData() {
        // Arrange
        // This sessionId is a fixture designed by using a known but bogus apiKey and apiSecret
        $sessionId = '1_MX4xMjM0NTY3OH4-VGh1IEZlYiAyNyAwNDozODozMSBQU1QgMjAxNH4wLjI0NDgyMjI';
        $bogusApiKey = '12345678';
        $bogusApiSecret = '0123456789abcdef0123456789abcdef0123456789';
        $opentok = new OpenTok($bogusApiKey, $bogusApiSecret);

        // Act
        $userStatus = '{nick:"johnny",status:"hey there fellas!"}';
        $token = $opentok->generateToken($sessionId, array('data' => $userStatus ));

        // Assert
        $this->assertInternalType('string', $token);

        $decodedToken = TestHelpers::decodeToken($token);
        $this->assertEquals($sessionId, $decodedToken['session_id']);
        $this->assertEquals($bogusApiKey, $decodedToken['partner_id']);
        $this->assertNotEmpty($decodedToken['nonce']);
        $this->assertNotEmpty($decodedToken['create_time']);
        $this->assertEquals($userStatus, $decodedToken['connection_data']);
        $this->assertNotEmpty($decodedToken['role']);
        // TODO: should all tokens have a default expire time even if it wasn't specified?
        //$this->assertNotEmpty($decodedToken['expire_time']);

        $this->assertNotEmpty($decodedToken['sig']);
        $this->assertEquals(hash_hmac('sha1', $decodedToken['dataString'], $bogusApiSecret), $decodedToken['sig']);
    }

    // TODO: write tests for passing invalid $expireTime and $data to generateToken
    // TODO: write tests for passing extraneous properties to generateToken

    /**
     * @expectedException InvalidArgumentException
     */
    public function testFailsWhenGeneratingTokenUsingInvalidRole()
    {
        $token = $this->opentok->generateToken('SESSIONID', array('role' => 'notarole'));
    }

    public function testStartsArchiveFails()
    {

        $mock = new MockPlugin();
        $response = MockPlugin::getMockFile(
            self::$mockBasePath . 'v2/partner/APIKEY/archive/start_fails'
        );
        $mock->addResponse($response);
        if ($this->MOCK)
        {
            $this->client->addSubscriber($mock);
        }

        // This sessionId was generated using a different apiKey, but this method doesn't do any
        // decoding to check, so its fine.
        $this->setExpectedException('OpenTok\Exception\ArchiveDomainException');
        $sessionId = $this->SESSION_ID;

        $archive = $this->opentok->startArchive($sessionId, 'showtime');
    }

    public function testStartsArchive()
    {
        // Arrange
        $mock = new MockPlugin();
        $response = MockPlugin::getMockFile(
            self::$mockBasePath . 'v2/partner/APIKEY/archive/session_name-showtime'
        );
        $mock->addResponse($response);
        $this->client->addSubscriber($mock);

        // This sessionId was generated using a different apiKey, but this method doesn't do any
        // decoding to check, so its fine.
        $sessionId = $this->SESSION_ID;

        // Act
        $archive = $this->opentok->startArchive($sessionId, 'showtime');

        // Assert
        $requests = $mock->getReceivedRequests();
        $this->assertCount(1, $requests);

        $request = $requests[0];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/partner/'.$this->API_KEY.'/archive', $request->getPath());
        $this->assertEquals($this->API_URL, $request->getScheme(). '://'.$request->getHost());
        $this->assertEquals('https', $request->getScheme());

        $contentType = $request->getHeader('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeader('X-TB-PARTNER-AUTH');
        $this->assertNotEmpty($authString);
        $this->assertEquals($this->API_KEY.':'.$this->API_SECRET, $authString);

        // TODO: test the dynamically built User Agent string
        $userAgent = $request->getHeader('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/2.2.3-alpha.1', $userAgent->__toString());

        $body = json_decode($request->getBody());
        $this->assertEquals($sessionId, $body->sessionId);
        $this->assertEquals('showtime', $body->name);

        $this->assertInstanceOf('OpenTok\Archive', $archive);
        // TODO: test the properties of the actual archive object
    }


    public function testStopsArchiveFails()
    {

        $mock = new MockPlugin();
        $response = MockPlugin::getMockFile(
            self::$mockBasePath . 'v2/partner/APIKEY/archive/ARCHIVEID/stop_fails'
        );
        $mock->addResponse($response);
        if ($this->MOCK)
        {
            $this->client->addSubscriber($mock);
        }

        $this->setExpectedException('OpenTok\Exception\ArchiveDomainException');
        $archiveId = 'ARCHIVEID';

        $archive = $this->opentok->stopArchive($archiveId);
    }

    public function testStopsArchive()
    {
        // Arrange
        $mock = new MockPlugin();
        $response = MockPlugin::getMockFile(
            self::$mockBasePath . 'v2/partner/APIKEY/archive/ARCHIVEID/stop'
        );
        $mock->addResponse($response);
        $this->client->addSubscriber($mock);

        $archiveId = '063e72a4-64b4-43c8-9da5-eca071daab89';

        // Act
        $archive = $this->opentok->stopArchive($archiveId);

        // Assert
        $requests = $mock->getReceivedRequests();
        $this->assertCount(1, $requests);

        $request = $requests[0];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/partner/'.$this->API_KEY.'/archive/'.$archiveId.'/stop', $request->getPath());
        $this->assertEquals($this->API_URL, $request->getScheme(). '://'.$request->getHost());
        $this->assertEquals('https', $request->getScheme());

        $contentType = $request->getHeader('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeader('X-TB-PARTNER-AUTH');
        $this->assertNotEmpty($authString);
        $this->assertEquals($this->API_KEY.':'.$this->API_SECRET, $authString);

        // TODO: test the dynamically built User Agent string
        $userAgent = $request->getHeader('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/2.2.3-alpha.1', $userAgent->__toString());

        $this->assertInstanceOf('OpenTok\Archive', $archive);
        // TODO: test the properties of the actual archive object
    }

    public function testGetsArchiveFails()
    {

        $mock = new MockPlugin();
        $response = MockPlugin::getMockFile(
            self::$mockBasePath . 'v2/partner/APIKEY/archive/ARCHIVEID/stop_fails'
        );
        $mock->addResponse($response);
        if ($this->MOCK)
        {
            $this->client->addSubscriber($mock);
        }

        $this->setExpectedException('OpenTok\Exception\DomainException');
        $archiveId = 'ARCHIVEID';

        $archive = $this->opentok->getArchive($archiveId);
    }

    public function testGetsArchive()
    {
        // Arrange
        $mock = new MockPlugin();
        $response = MockPlugin::getMockFile(
            self::$mockBasePath . 'v2/partner/APIKEY/archive/ARCHIVEID/get'
        );
        $mock->addResponse($response);
        $this->client->addSubscriber($mock);

        $archiveId = '063e72a4-64b4-43c8-9da5-eca071daab89';

        // Act
        $archive = $this->opentok->getArchive($archiveId);

        // Assert
        $requests = $mock->getReceivedRequests();
        $this->assertCount(1, $requests);

        $request = $requests[0];
        $this->assertEquals('GET', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/partner/'.$this->API_KEY.'/archive/'.$archiveId, $request->getPath());
        $this->assertEquals($this->API_URL, $request->getScheme(). '://'.$request->getHost());
        $this->assertEquals('https', $request->getScheme());

        // TODO: this doesn't require Content-Type: application/json, but delete does?

        $authString = $request->getHeader('X-TB-PARTNER-AUTH');
        $this->assertNotEmpty($authString);
        $this->assertEquals($this->API_KEY.':'.$this->API_SECRET, $authString);

        // TODO: test the dynamically built User Agent string
        $userAgent = $request->getHeader('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/2.2.3-alpha.1', $userAgent->__toString());

        $this->assertInstanceOf('OpenTok\Archive', $archive);
        // TODO: test the properties of the actual archive object
    }


    public function testDeletesArchiveFails()
    {

        $mock = new MockPlugin();
        $response = MockPlugin::getMockFile(
            self::$mockBasePath . 'v2/partner/APIKEY/archive/ARCHIVEID/delete_fails'
        );
        $mock->addResponse($response);
        if ($this->MOCK)
        {
            $this->client->addSubscriber($mock);
        }

        $this->setExpectedException('OpenTok\Exception\DomainException');
        $archiveId = 'ARCHIVEID';

        $archive = $this->opentok->deleteArchive($archiveId);
    }

    public function testDeletesArchive()
    {
        // Arrange
        $mock = new MockPlugin();
        $response = MockPlugin::getMockFile(
            self::$mockBasePath . 'v2/partner/APIKEY/archive/ARCHIVEID/delete'
        );
        $mock->addResponse($response);
        $this->client->addSubscriber($mock);

        $archiveId = '063e72a4-64b4-43c8-9da5-eca071daab89';

        // Act
        $success = $this->opentok->deleteArchive($archiveId);

        // Assert
        $requests = $mock->getReceivedRequests();
        $this->assertCount(1, $requests);

        $request = $requests[0];
        $this->assertEquals('DELETE', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/partner/'.$this->API_KEY.'/archive/'.$archiveId, $request->getPath());
        $this->assertEquals($this->API_URL, $request->getScheme(). '://'.$request->getHost());
        $this->assertEquals('https', $request->getScheme());

        $contentType = $request->getHeader('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeader('X-TB-PARTNER-AUTH');
        $this->assertNotEmpty($authString);
        $this->assertEquals($this->API_KEY.':'.$this->API_SECRET, $authString);

        // TODO: test the dynamically built User Agent string
        $userAgent = $request->getHeader('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/2.2.3-alpha.1', $userAgent->__toString());

        $this->assertTrue($success);
        // TODO: test the properties of the actual archive object
    }

    public function testListsArchives()
    {
        // Arrange
        $mock = new MockPlugin();
        $response = MockPlugin::getMockFile(
            self::$mockBasePath . 'v2/partner/APIKEY/archive/get'
        );
        $mock->addResponse($response);
        if ($this->MOCK)
        {
            $this->client->addSubscriber($mock);
        }
        

        // Act
        $archiveList = $this->opentok->listArchives();

        if ($this->MOCK)
        {
            // Assert
            $requests = $mock->getReceivedRequests();
            $this->assertCount(1, $requests);

            $request = $requests[0];
            $this->assertEquals('GET', strtoupper($request->getMethod()));
            $this->assertEquals('/v2/partner/'.$this->API_KEY.'/archive', $request->getPath());
            $this->assertEquals('api.opentok.com', $request->getHost());
            $this->assertEquals('https', $request->getScheme());

            $authString = $request->getHeader('X-TB-PARTNER-AUTH');
            $this->assertNotEmpty($authString);
            $this->assertEquals($this->API_KEY.':'.$this->API_SECRET, $authString);

            // TODO: test the dynamically built User Agent string
            $userAgent = $request->getHeader('User-Agent');
            $this->assertNotEmpty($userAgent);
            $this->assertStringStartsWith('OpenTok-PHP-SDK/2.2.3-alpha.1', $userAgent->__toString());
        }

        $this->assertInstanceOf('OpenTok\ArchiveList', $archiveList);
        // TODO: test the properties of the actual archiveList object and its contained archive
        // objects
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testFailsWhenListingArchivesWithTooLargeCount()
    {
        // Arrange
        $mock = new MockPlugin();
        $response = MockPlugin::getMockFile(
            self::$mockBasePath . 'v2/partner/APIKEY/archive/get'
        );
        $mock->addResponse($response);
        $this->client->addSubscriber($mock);

        // Act
        $archiveList = $this->opentok->listArchives(0, 1001);

        // Assert
        $requests = $mock->getReceivedRequests();
        $this->assertCount(0, $requests);
    }

    // TODO: sloppy test in a pinch
    public function testGetsExpiredArchive()
    {
        // Arrange
        $mock = new MockPlugin();
        $response = MockPlugin::getMockFile(
            self::$mockBasePath . 'v2/partner/APIKEY/archive/ARCHIVEID/get-expired'
        );
        $mock->addResponse($response);
        $this->client->addSubscriber($mock);

        $archiveId = '063e72a4-64b4-43c8-9da5-eca071daab89';

        // Act
        $archive = $this->opentok->getArchive($archiveId);

        // Assert
        $this->assertInstanceOf('OpenTok\Archive', $archive);
        $this->assertEquals("expired", $archive->status);
    }
}
/* vim: set ts=4 sw=4 tw=100 sts=4 et :*/
