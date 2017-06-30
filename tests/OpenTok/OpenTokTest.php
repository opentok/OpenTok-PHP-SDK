<?php

use Guzzle\Plugin\Mock\MockPlugin;
use Guzzle\Plugin\Log\LogPlugin;

use OpenTok\OpenTok;
use OpenTok\OpenTokTestCase;
use OpenTok\Session;
use OpenTok\Role;
use OpenTok\Layout;
use OpenTok\MediaMode;
use OpenTok\ArchiveMode;
use OpenTok\OutputMode;
use OpenTok\Util\Client;

use OpenTok\TestHelpers;

// define('DEBUG', true);

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
        $this->API_KEY = defined('API_KEY') ? API_KEY : '12345678';
        $this->API_SECRET = defined('API_SECRET') ? API_SECRET : '0123456789abcdef0123456789abcdef0123456789';

        $this->client = new Client();
        if (defined('DEBUG')) {
            $this->client->addSubscriber(LogPlugin::getDebugPlugin());
        }

        $this->opentok = new OpenTok($this->API_KEY, $this->API_SECRET, array('client' => $this->client));

    }

    public function testCanBeInitialized()
    {
        // Arrange
        // Act
        // Assert
        $this->assertInstanceOf('OpenTok\OpenTok', $this->opentok);
    }

    public function testFailsOnInvalidInitialization()
    {
        if (class_exists('ArgumentCountError')) {
          $this->expectException(ArgumentCountError::class);
        } else {
          $this->expectException(PHPUnit_Framework_Error_Warning::class);
        }
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
        $this->client->addSubscriber($mock);

        // Act
        $session = $this->opentok->createSession();

        // Assert
        $requests = $mock->getReceivedRequests();
        $this->assertCount(1, $requests);

        $request = $requests[0];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/session/create', $request->getPath());
        $this->assertEquals('api.opentok.com', $request->getHost());
        $this->assertEquals('https', $request->getScheme());

        $authString = $request->getHeader('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        // TODO: test the dynamically built User Agent string
        $userAgent = $request->getHeader('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/3.0.0', $userAgent->__toString());

        $p2p_preference = $request->getPostField('p2p.preference');
        $this->assertEquals('enabled', $p2p_preference);

        $this->assertInstanceOf('OpenTok\Session', $session);
        // NOTE: this is an actual sessionId from the recorded response, this doesn't correspond to
        // the API Key and API Secret used to create the session.
        $this->assertEquals(
            '2_MX4xNzAxMjYzMX4xMjcuMC4wLjF-V2VkIEZlYiAyNiAxODo1NzoyNCBQU1QgMjAxNH4wLjU0MDU4ODc0fg',
            $session->getSessionId()
        );
    }

    public function testCreatesMediaRoutedAndLocationSession()
    {
        // Arrange
        $mock = new MockPlugin();
        $response = MockPlugin::getMockFile(
            self::$mockBasePath . 'session/create/routed_location-12.34.56.78'
        );
        $mock->addResponse($response);
        $this->client->addSubscriber($mock);

        // Act
        $session = $this->opentok->createSession(array(
            'location' => '12.34.56.78',
            'mediaMode' => MediaMode::ROUTED
        ));

        // Assert
        $requests = $mock->getReceivedRequests();
        $this->assertCount(1, $requests);

        $request = $requests[0];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/session/create', $request->getPath());
        $this->assertEquals('api.opentok.com', $request->getHost());
        $this->assertEquals('https', $request->getScheme());

        $authString = $request->getHeader('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        // TODO: test the dynamically built User Agent string
        $userAgent = $request->getHeader('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/3.0.0', $userAgent->__toString());

        $location = $request->getPostField('location');
        $this->assertEquals('12.34.56.78', $location);

        $p2p_preference = $request->getPostField('p2p.preference');
        $this->assertEquals('disabled', $p2p_preference);

        $this->assertInstanceOf('OpenTok\Session', $session);
        // NOTE: this is an actual sessionId from the recorded response, this doesn't correspond to
        // the API Key and API Secret used to create the session.
        $this->assertEquals(
            '2_MX4xNzAxMjYzMX4xMjcuMC4wLjF-V2VkIEZlYiAyNiAxODo1NzoyNCBQU1QgMjAxNH4wLjU0MDU4ODc0fg',
            $session->getSessionId()
        );
    }

    public function testCreatesMediaRelayedSession()
    {
        // Arrange
        $mock = new MockPlugin();
        $response = MockPlugin::getMockFile(
            self::$mockBasePath . 'session/create/relayed'
        );
        $mock->addResponse($response);
        $this->client->addSubscriber($mock);

        // Act
        $session = $this->opentok->createSession(array(
            'mediaMode' => MediaMode::RELAYED
        ));

        // Assert
        $requests = $mock->getReceivedRequests();
        $this->assertCount(1, $requests);

        $request = $requests[0];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/session/create', $request->getPath());
        $this->assertEquals('api.opentok.com', $request->getHost());
        $this->assertEquals('https', $request->getScheme());

        $authString = $request->getHeader('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        // TODO: test the dynamically built User Agent string
        $userAgent = $request->getHeader('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/3.0.0', $userAgent->__toString());

        $p2p_preference = $request->getPostField('p2p.preference');
        $this->assertEquals('enabled', $p2p_preference);

        $this->assertInstanceOf('OpenTok\Session', $session);
        // NOTE: this is an actual sessionId from the recorded response, this doesn't correspond to
        // the API Key and API Secret used to create the session.
        $this->assertEquals(
            '2_MX4xNzAxMjYzMX4xMjcuMC4wLjF-V2VkIEZlYiAyNiAxODo1NzoyNCBQU1QgMjAxNH4wLjU0MDU4ODc0fg',
            $session->getSessionId()
        );
    }

    public function testCreatesAutoArchivedSession()
    {
        // Arrange
        $mock = new MockPlugin();
        $response = MockPlugin::getMockFile(
            self::$mockBasePath . 'session/create/alwaysarchived'
        );
        $mock->addResponse($response);
        $this->client->addSubscriber($mock);

        // Act
        $session = $this->opentok->createSession(array(
            'archiveMode' => ArchiveMode::ALWAYS
        ));

        // Assert
        $requests = $mock->getReceivedRequests();
        $this->assertCount(1, $requests);

        $request = $requests[0];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/session/create', $request->getPath());
        $this->assertEquals('api.opentok.com', $request->getHost());
        $this->assertEquals('https', $request->getScheme());

        $authString = $request->getHeader('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        // TODO: test the dynamically built User Agent string
        $userAgent = $request->getHeader('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/3.0.0', $userAgent->__toString());

        $archiveMode = $request->getPostField('archiveMode');
        $this->assertEquals('always', $archiveMode);

        $mediaMode = $request->getPostField('p2p.preference');
        $this->assertNotEquals('enabled', $mediaMode);

        $this->assertInstanceOf('OpenTok\Session', $session);
        // NOTE: this is an actual sessionId from the recorded response, this doesn't correspond to
        // the API Key and API Secret used to create the session.
        $this->assertEquals(
            '2_MX4xNzAxMjYzMX4xMjcuMC4wLjF-V2VkIEZlYiAyNiAxODo1NzoyNCBQU1QgMjAxNH4wLjU0MDU4ODc0fg',
            $session->getSessionId()
        );
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testFailsWhenCreatingRelayedAutoArchivedSession()
    {
        // Arrange

        // Act
        $session = $this->opentok->createSession(array(
            'mediaMode' => MediaMode::RELAYED,
            'archiveMode' => ArchiveMode::ALWAYS
        ));

        // Assert
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

    public function testStartsArchive()
    {
        // Arrange
        $mock = new MockPlugin();
        $response = MockPlugin::getMockFile(
            self::$mockBasePath . 'v2/project/APIKEY/archive/session'
        );
        $mock->addResponse($response);
        $this->client->addSubscriber($mock);

        // This sessionId was generated using a different apiKey, but this method doesn't do any
        // decoding to check, so its fine.
        $sessionId = '2_MX44NTQ1MTF-flR1ZSBOb3YgMTIgMDk6NDA6NTkgUFNUIDIwMTN-MC43NjU0Nzh-';

        // Act
        $archive = $this->opentok->startArchive($sessionId);

        // Assert
        $requests = $mock->getReceivedRequests();
        $this->assertCount(1, $requests);

        $request = $requests[0];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/'.$this->API_KEY.'/archive', $request->getPath());
        $this->assertEquals('api.opentok.com', $request->getHost());
        $this->assertEquals('https', $request->getScheme());

        $contentType = $request->getHeader('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeader('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        // TODO: test the dynamically built User Agent string
        $userAgent = $request->getHeader('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/3.0.0', $userAgent->__toString());

        $this->assertInstanceOf('OpenTok\Archive', $archive);
        $this->assertEquals(0, $archive->duration);
        $this->assertEquals('', $archive->reason);
        $this->assertEquals('started', $archive->status);
        $this->assertEquals(OutputMode::COMPOSED, $archive->outputMode);
        $this->assertNull($archive->name);
        $this->assertNull($archive->url);
        $this->assertTrue($archive->hasVideo);
        $this->assertTrue($archive->hasAudio);
    }

    public function testStartsArchiveNamed()
    {
        // Arrange
        $mock = new MockPlugin();
        $response = MockPlugin::getMockFile(
            self::$mockBasePath . 'v2/project/APIKEY/archive/session_name-showtime'
        );
        $mock->addResponse($response);
        $this->client->addSubscriber($mock);

        // This sessionId was generated using a different apiKey, but this method doesn't do any
        // decoding to check, so its fine.
        $sessionId = '2_MX44NTQ1MTF-flR1ZSBOb3YgMTIgMDk6NDA6NTkgUFNUIDIwMTN-MC43NjU0Nzh-';

        // Act
        $archive = $this->opentok->startArchive($sessionId, array( 'name' => 'showtime' ));

        // Assert
        $requests = $mock->getReceivedRequests();
        $this->assertCount(1, $requests);

        $request = $requests[0];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/'.$this->API_KEY.'/archive', $request->getPath());
        $this->assertEquals('api.opentok.com', $request->getHost());
        $this->assertEquals('https', $request->getScheme());

        $contentType = $request->getHeader('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeader('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        // TODO: test the dynamically built User Agent string
        $userAgent = $request->getHeader('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/3.0.0', $userAgent->__toString());

        $body = json_decode($request->getBody());
        $this->assertEquals($sessionId, $body->sessionId);
        $this->assertEquals('showtime', $body->name);

        $this->assertInstanceOf('OpenTok\Archive', $archive);
        // TODO: test the properties of the actual archive object
    }

    // this is the deprecated method signature, remove in v3.0.0 (and not before)
    public function testStartsArchiveNamedDeprecated()
    {
        // Arrange
        $mock = new MockPlugin();
        $response = MockPlugin::getMockFile(
            self::$mockBasePath . 'v2/project/APIKEY/archive/session_name-showtime'
        );
        $mock->addResponse($response);
        $this->client->addSubscriber($mock);

        // This sessionId was generated using a different apiKey, but this method doesn't do any
        // decoding to check, so its fine.
        $sessionId = '2_MX44NTQ1MTF-flR1ZSBOb3YgMTIgMDk6NDA6NTkgUFNUIDIwMTN-MC43NjU0Nzh-';

        // Act
        $archive = $this->opentok->startArchive($sessionId, 'showtime');

        // Assert
        $requests = $mock->getReceivedRequests();
        $this->assertCount(1, $requests);

        $request = $requests[0];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/'.$this->API_KEY.'/archive', $request->getPath());
        $this->assertEquals('api.opentok.com', $request->getHost());
        $this->assertEquals('https', $request->getScheme());

        $contentType = $request->getHeader('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeader('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        // TODO: test the dynamically built User Agent string
        $userAgent = $request->getHeader('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/3.0.0', $userAgent->__toString());

        $body = json_decode($request->getBody());
        $this->assertEquals($sessionId, $body->sessionId);
        $this->assertEquals('showtime', $body->name);

        $this->assertInstanceOf('OpenTok\Archive', $archive);
        // TODO: test the properties of the actual archive object
    }

    public function testStartsArchiveAudioOnly()
    {
        // Arrange
        $mock = new MockPlugin();
        $response = MockPlugin::getMockFile(
            self::$mockBasePath . 'v2/project/APIKEY/archive/session_hasVideo-false'
        );
        $mock->addResponse($response);
        $this->client->addSubscriber($mock);

        // This sessionId was generated using a different apiKey, but this method doesn't do any
        // decoding to check, so its fine.
        $sessionId = '2_MX44NTQ1MTF-flR1ZSBOb3YgMTIgMDk6NDA6NTkgUFNUIDIwMTN-MC43NjU0Nzh-';

        // Act
        $archive = $this->opentok->startArchive($sessionId, array( 'hasVideo' => false ));

        // Assert
        $requests = $mock->getReceivedRequests();
        $this->assertCount(1, $requests);

        $request = $requests[0];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/'.$this->API_KEY.'/archive', $request->getPath());
        $this->assertEquals('api.opentok.com', $request->getHost());
        $this->assertEquals('https', $request->getScheme());

        $contentType = $request->getHeader('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeader('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        // TODO: test the dynamically built User Agent string
        $userAgent = $request->getHeader('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/3.0.0', $userAgent->__toString());

        $body = json_decode($request->getBody());
        $this->assertEquals($sessionId, $body->sessionId);
        $this->assertEquals(false, $body->hasVideo);
        $this->assertEquals(true, $body->hasAudio);

        $this->assertInstanceOf('OpenTok\Archive', $archive);
        // TODO: test the properties of the actual archive object
    }

    public function testStartsArchiveIndividualOutput()
    {
      // Arrange
      $mock = new MockPlugin();
      $response = MockPlugin::getMockFile(
          self::$mockBasePath . 'v2/project/APIKEY/archive/session_outputMode-individual'
      );
      $mock->addResponse($response);
      $this->client->addSubscriber($mock);

      // This sessionId was generated using a different apiKey, but this method doesn't do any
      // decoding to check, so its fine.
      $sessionId = '2_MX44NTQ1MTF-flR1ZSBOb3YgMTIgMDk6NDA6NTkgUFNUIDIwMTN-MC43NjU0Nzh-';

      // Act
      $archive = $this->opentok->startArchive($sessionId, array(
          'outputMode' => OutputMode::INDIVIDUAL
      ));

      // Assert
      $requests = $mock->getReceivedRequests();
      $this->assertCount(1, $requests);

      $request = $requests[0];
      $this->assertEquals('POST', strtoupper($request->getMethod()));
      $this->assertEquals('/v2/project/'.$this->API_KEY.'/archive', $request->getPath());
      $this->assertEquals('api.opentok.com', $request->getHost());
      $this->assertEquals('https', $request->getScheme());

      $contentType = $request->getHeader('Content-Type');
      $this->assertNotEmpty($contentType);
      $this->assertEquals('application/json', $contentType);

      $authString = $request->getHeader('X-OPENTOK-AUTH');
      $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

      // TODO: test the dynamically built User Agent string
      $userAgent = $request->getHeader('User-Agent');
      $this->assertNotEmpty($userAgent);
      $this->assertStringStartsWith('OpenTok-PHP-SDK/3.0.0', $userAgent->__toString());

      $body = json_decode($request->getBody());
      $this->assertEquals($sessionId, $body->sessionId);
      $this->assertEquals('individual', $body->outputMode);

      $this->assertInstanceOf('OpenTok\Archive', $archive);
      $this->assertEquals(OutputMode::INDIVIDUAL, $archive->outputMode);
    }

    public function testStopsArchive()
    {
        // Arrange
        $mock = new MockPlugin();
        $response = MockPlugin::getMockFile(
            self::$mockBasePath . 'v2/project/APIKEY/archive/ARCHIVEID/stop'
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
        $this->assertEquals('/v2/project/'.$this->API_KEY.'/archive/'.$archiveId.'/stop', $request->getPath());
        $this->assertEquals('api.opentok.com', $request->getHost());
        $this->assertEquals('https', $request->getScheme());

        $contentType = $request->getHeader('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeader('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        // TODO: test the dynamically built User Agent string
        $userAgent = $request->getHeader('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/3.0.0', $userAgent->__toString());

        $this->assertInstanceOf('OpenTok\Archive', $archive);
        // TODO: test the properties of the actual archive object
    }

    public function testGetsArchive()
    {
        // Arrange
        $mock = new MockPlugin();
        $response = MockPlugin::getMockFile(
            self::$mockBasePath . 'v2/project/APIKEY/archive/ARCHIVEID/get'
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
        $this->assertEquals('/v2/project/'.$this->API_KEY.'/archive/'.$archiveId, $request->getPath());
        $this->assertEquals('api.opentok.com', $request->getHost());
        $this->assertEquals('https', $request->getScheme());

        // TODO: this doesn't require Content-Type: application/json, but delete does?

        $authString = $request->getHeader('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        // TODO: test the dynamically built User Agent string
        $userAgent = $request->getHeader('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/3.0.0', $userAgent->__toString());

        $this->assertInstanceOf('OpenTok\Archive', $archive);
        // TODO: test the properties of the actual archive object
    }

    public function testDeletesArchive()
    {
        // Arrange
        $mock = new MockPlugin();
        $response = MockPlugin::getMockFile(
            self::$mockBasePath . 'v2/project/APIKEY/archive/ARCHIVEID/delete'
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
        $this->assertEquals('/v2/project/'.$this->API_KEY.'/archive/'.$archiveId, $request->getPath());
        $this->assertEquals('api.opentok.com', $request->getHost());
        $this->assertEquals('https', $request->getScheme());

        $contentType = $request->getHeader('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeader('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        // TODO: test the dynamically built User Agent string
        $userAgent = $request->getHeader('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/3.0.0', $userAgent->__toString());

        $this->assertTrue($success);
        // TODO: test the properties of the actual archive object
    }

    public function testListsArchives()
    {
        // Arrange
        $mock = new MockPlugin();
        $response = MockPlugin::getMockFile(
            self::$mockBasePath . 'v2/project/APIKEY/archive/get'
        );
        $mock->addResponse($response);
        $this->client->addSubscriber($mock);

        // Act
        $archiveList = $this->opentok->listArchives();

        // Assert
        $requests = $mock->getReceivedRequests();
        $this->assertCount(1, $requests);

        $request = $requests[0];
        $this->assertEquals('GET', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/'.$this->API_KEY.'/archive', $request->getPath());
        $this->assertEquals('api.opentok.com', $request->getHost());
        $this->assertEquals('https', $request->getScheme());

        $authString = $request->getHeader('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        // TODO: test the dynamically built User Agent string
        $userAgent = $request->getHeader('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/3.0.0', $userAgent->__toString());

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
            self::$mockBasePath . 'v2/project/APIKEY/archive/get'
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
            self::$mockBasePath . 'v2/project/APIKEY/archive/ARCHIVEID/get-expired'
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

	public function testForceDisconnect()
	{
        // Arrange
        $mock = new MockPlugin();
        $response = MockPlugin::getMockFile(
            self::$mockBasePath . 'v2/project/APIKEY/session/SESSIONID/connection/CONNECTIONID/delete'
        );
        $mock->addResponse($response);
        $this->client->addSubscriber($mock);

        // This sessionId was generated using a different apiKey, but this method doesn't do any
        // decoding to check, so its fine.
        $sessionId = '2_MX44NTQ1MTF-flR1ZSBOb3YgMTIgMDk6NDA6NTkgUFNUIDIwMTN-MC43NjU0Nzh-';

        $connectionId = '063e72a4-64b4-43c8-9da5-eca071daab89';

        // Act
        $success = $this->opentok->forceDisconnect($sessionId, $connectionId);

        // Assert
        $requests = $mock->getReceivedRequests();
        $this->assertCount(1, $requests);

        $request = $requests[0];
        $this->assertEquals('DELETE', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/'.$this->API_KEY.'/session/'.$sessionId.'/connection/'.$connectionId, $request->getPath());
        $this->assertEquals('api.opentok.com', $request->getHost());
        $this->assertEquals('https', $request->getScheme());

        $contentType = $request->getHeader('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeader('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        // TODO: test the dynamically built User Agent string
        $userAgent = $request->getHeader('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/3.0.0', $userAgent->__toString());

        $this->assertTrue($success);
    }

    public function testStartsBroadcast()
    {
        // Arrange
        $mock = new MockPlugin();
        $response = MockPlugin::getMockFile(
            self::$mockBasePath . '/v2/project/APIKEY/broadcast/session_layout-bestfit'
        );
        $mock->addResponse($response);
        $this->client->addSubscriber($mock);

        // This sessionId was generated using a different apiKey, but this method doesn't do any
        // decoding to check, so its fine.
        $sessionId = '2_MX44NTQ1MTF-fjE0NzI0MzU2MDUyMjN-eVgwNFJhZmR6MjdockFHanpxNzBXaEFXfn4';

        // Act
        $broadcast = $this->opentok->startBroadcast($sessionId);

        // Assert
        $requests = $mock->getReceivedRequests();
        $this->assertCount(1, $requests);

        $request = $requests[0];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/'.$this->API_KEY.'/broadcast', $request->getPath());
        $this->assertEquals('api.opentok.com', $request->getHost());
        $this->assertEquals('https', $request->getScheme());

        $contentType = $request->getHeader('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeader('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        // TODO: test the dynamically built User Agent string
        $userAgent = $request->getHeader('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/3.0.0', $userAgent->__toString());

        $this->assertInstanceOf('OpenTok\Broadcast', $broadcast);
        $this->assertInternalType('string', $broadcast->id);
        $this->assertEquals($sessionId, $broadcast->sessionId);
        $this->assertInternalType('array', $broadcast->broadcastUrls);
        $this->assertArrayHasKey('hls', $broadcast->broadcastUrls);
        $this->assertInternalType('string', $broadcast->broadcastUrls['hls']);
        $this->assertInternalType('string', $broadcast->hlsUrl);
        $this->assertFalse($broadcast->isStopped);
    }

    // TODO: test startBroadcast with layout

    public function testStopsBroadcast()
    {
        // Arrange
        $mock = new MockPlugin();
        $response = MockPlugin::getMockFile(
            self::$mockBasePath . '/v2/project/APIKEY/broadcast/BROADCASTID/stop'
        );
        $mock->addResponse($response);
        $this->client->addSubscriber($mock);

        $broadcastId = 'BROADCASTID';

        // Act
        $broadcast = $this->opentok->stopBroadcast($broadcastId);

        // Assert
        $requests = $mock->getReceivedRequests();
        $this->assertCount(1, $requests);

        $request = $requests[0];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/'.$this->API_KEY.'/broadcast/'.$broadcastId.'/stop', $request->getPath());
        $this->assertEquals('api.opentok.com', $request->getHost());
        $this->assertEquals('https', $request->getScheme());

        $authString = $request->getHeader('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        // TODO: test the dynamically built User Agent string
        $userAgent = $request->getHeader('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/3.0.0', $userAgent->__toString());

        $this->assertInstanceOf('OpenTok\Broadcast', $broadcast);
        $this->assertTrue($broadcast->isStopped);
    }

    public function testGetsBroadcast()
    {
        // Arrange
        $mock = new MockPlugin();
        $response = MockPlugin::getMockFile(
            self::$mockBasePath . '/v2/project/APIKEY/broadcast/BROADCASTID/get'
        );
        $mock->addResponse($response);
        $this->client->addSubscriber($mock);

        $broadcastId = 'BROADCASTID';

        // Act
        $broadcast = $this->opentok->getBroadcast($broadcastId);

        // Assert
        $requests = $mock->getReceivedRequests();
        $this->assertCount(1, $requests);

        $request = $requests[0];
        $this->assertEquals('GET', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/'.$this->API_KEY.'/broadcast/'.$broadcastId, $request->getPath());
        $this->assertEquals('api.opentok.com', $request->getHost());
        $this->assertEquals('https', $request->getScheme());

        $authString = $request->getHeader('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        // TODO: test the dynamically built User Agent string
        $userAgent = $request->getHeader('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/3.0.0', $userAgent->__toString());

        $this->assertInstanceOf('OpenTok\Broadcast', $broadcast);
    }

    public function testUpdatesBroadcastLayoutWithPredefined()
    {
        // Arrange
        $mock = new MockPlugin();
        $response = MockPlugin::getMockFile(
            self::$mockBasePath . '/v2/project/APIKEY/broadcast/BROADCASTID/layout/type-pip'
        );
        $mock->addResponse($response);
        $this->client->addSubscriber($mock);

        $broadcastId = 'BROADCASTID';
        $layout = Layout::getPIP();

        // Act
        $this->opentok->updateBroadcastLayout($broadcastId, $layout);

        // Assert
        $requests = $mock->getReceivedRequests();
        $this->assertCount(1, $requests);

        $request = $requests[0];
        $this->assertEquals('PUT', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/'.$this->API_KEY.'/broadcast/'.$broadcastId.'/layout', $request->getPath());
        $this->assertEquals('api.opentok.com', $request->getHost());
        $this->assertEquals('https', $request->getScheme());

        $contentType = $request->getHeader('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeader('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $body = json_decode($request->getBody());
        $this->assertEquals('pip', $body->type);

        // TODO: test the dynamically built User Agent string
        $userAgent = $request->getHeader('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/3.0.0', $userAgent->__toString());
    }

    public function testUpdatesBroadcastLayoutWithCustom()
    {
        // Arrange
        $mock = new MockPlugin();
        $response = MockPlugin::getMockFile(
            self::$mockBasePath . '/v2/project/APIKEY/broadcast/BROADCASTID/layout/type-custom'
        );
        $mock->addResponse($response);
        $this->client->addSubscriber($mock);

        $broadcastId = 'BROADCASTID';
        $stylesheet = '.classname { height: 1px; width: 1px }';
        $layout = Layout::createCustom(array(
            'stylesheet' => $stylesheet
        ));

        // Act
        $this->opentok->updateBroadcastLayout($broadcastId, $layout);

        // Assert
        $requests = $mock->getReceivedRequests();
        $this->assertCount(1, $requests);

        $request = $requests[0];
        $this->assertEquals('PUT', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/'.$this->API_KEY.'/broadcast/'.$broadcastId.'/layout', $request->getPath());
        $this->assertEquals('api.opentok.com', $request->getHost());
        $this->assertEquals('https', $request->getScheme());

        $contentType = $request->getHeader('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeader('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $body = json_decode($request->getBody());
        $this->assertEquals('custom', $body->type);
        $this->assertEquals($stylesheet, $body->stylesheet);

        // TODO: test the dynamically built User Agent string
        $userAgent = $request->getHeader('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/3.0.0', $userAgent->__toString());
    }

    public function testUpdatesStreamLayoutClassList()
    {
        // Arrange
        $mock = new MockPlugin();
        $response = MockPlugin::getMockFile(
            self::$mockBasePath . '/v2/project/APIKEY/session/SESSIONID/stream/STREAMID/layoutClassList'
        );
        $mock->addResponse($response);
        $this->client->addSubscriber($mock);

        $sessionId = 'SESSIONID';
        $streamId = 'STREAMID';
        $layoutClassList = array('foo', 'bar');

        // Act
        $this->opentok->updateStream($sessionId, $streamId, array(
            'layoutClassList' => $layoutClassList
        ));

        // Assert
        $requests = $mock->getReceivedRequests();
        $this->assertCount(1, $requests);

        $request = $requests[0];
        $this->assertEquals('PUT', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/'.$this->API_KEY.'/session/'.$sessionId.'/stream/'.$streamId, $request->getPath());
        $this->assertEquals('api.opentok.com', $request->getHost());
        $this->assertEquals('https', $request->getScheme());

        $contentType = $request->getHeader('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeader('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $body = json_decode($request->getBody());
        $this->assertEquals($layoutClassList, $body->layoutClassList);

        // TODO: test the dynamically built User Agent string
        $userAgent = $request->getHeader('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/3.0.0', $userAgent->__toString());
    }


    public function testSipCall()
    {
      // Arrange
      $mock = new MockPlugin();
      $response = MockPlugin::getMockFile(
          self::$mockBasePath . 'v2/project/APIKEY/dial'
      );
      $mock->addResponse($response);
      $this->client->addSubscriber($mock);

      $sessionId = '1_MX4xMjM0NTY3OH4-VGh1IEZlYiAyNyAwNDozODozMSBQU1QgMjAxNH4wLjI0NDgyMjI';
      $bogusApiKey = '12345678';
      $bogusApiSecret = '0123456789abcdef0123456789abcdef0123456789';
      $bogusToken = 'T1==TEST';
      $bogusSipUri = 'sip:john@doe.com';
      $opentok = new OpenTok($bogusApiKey, $bogusApiSecret);

      // Act
      $sipCall = $this->opentok->dial($sessionId, $bogusToken, $bogusSipUri);

      // Assert
      $this->assertInstanceOf('OpenTok\SipCall', $sipCall);
      $this->assertNotNull($sipCall->id);
      $this->assertNotNull($sipCall->connectionId);
      $this->assertNotNull($sipCall->streamId);
    }

    public function testSipCallWithAuth()
    {
      // Arrange
      $mock = new MockPlugin();
      $response = MockPlugin::getMockFile(
          self::$mockBasePath . 'v2/project/APIKEY/dial'
      );
      $mock->addResponse($response);
      $this->client->addSubscriber($mock);

      $sessionId = '1_MX4xMjM0NTY3OH4-VGh1IEZlYiAyNyAwNDozODozMSBQU1QgMjAxNH4wLjI0NDgyMjI';
      $bogusApiKey = '12345678';
      $bogusApiSecret = '0123456789abcdef0123456789abcdef0123456789';
      $bogusToken = 'T1==TEST';
      $bogusSipUri = 'sip:john@doe.com';
      $opentok = new OpenTok($bogusApiKey, $bogusApiSecret);

      $auth = array(
        'username' => 'john',
        'password' => 'doe'
      );

      // Act
      $sipCall = $this->opentok->dial($sessionId, $bogusToken, $bogusSipUri, array(
        'auth' => $auth
      ));

      // Assert
      $this->assertInstanceOf('OpenTok\SipCall', $sipCall);
      $this->assertNotNull($sipCall->id);
      $this->assertNotNull($sipCall->connectionId);
      $this->assertNotNull($sipCall->streamId);

      $requests = $mock->getReceivedRequests();
      $this->assertCount(1, $requests);
      $request = $requests[0];

      $body = json_decode($request->getBody());
      $this->assertEquals($auth['username'], $body->sip->auth->username);
      $this->assertEquals($auth['password'], $body->sip->auth->password);
    }

    public function testFailedSipCall()
    {
      // Arrange
      $mock = new MockPlugin();
      $response = MockPlugin::getMockFile(
          self::$mockBasePath . 'v2/project/APIKEY/dial-failed'
      );
      $mock->addResponse($response);
      $this->client->addSubscriber($mock);

      $sessionId = '1_MX4xMjM0NTY3OH4-VGh1IEZlYiAyNyAwNDozODozMSBQU1QgMjAxNH4wLjI0NDgyMjI';
      $bogusApiKey = '12345678';
      $bogusApiSecret = '0123456789abcdef0123456789abcdef0123456789';
      $bogusToken = 'T1==TEST';
      $bogusSipUri = 'sip:john@doe.com';
      $opentok = new OpenTok($bogusApiKey, $bogusApiSecret);

      $sipCall = null;
      // Act
      try {
          $sipCall = $this->opentok->dial($sessionId, $bogusToken, $bogusSipUri);
          $this->assertNull($sipCall);
      } catch (\Exception $e) {
          $this->assertNull($sipCall);
      }
    }
}
/* vim: set ts=4 sw=4 tw=100 sts=4 et :*/
