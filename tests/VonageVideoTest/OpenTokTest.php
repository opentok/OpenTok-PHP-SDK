<?php

namespace VonageVideoTest;

use OpenTok\Role;
use OpenTok\Layout;
use OpenTok\OpenTok;
use OpenTok\MediaMode;
use ArgumentCountError;
use DomainException;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use OpenTok\OutputMode;
use OpenTok\ArchiveMode;
use OpenTok\Util\Client;
use GuzzleHttp\Middleware;
use GuzzleHttp\HandlerStack;
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Handler\MockHandler;
use InvalidArgumentException as GlobalInvalidArgumentException;
use OpenTok\Exception\AuthenticationException;
use OpenTok\Exception\DomainException as ExceptionDomainException;
use OpenTok\Exception\InvalidArgumentException;
use RuntimeException;
use OpenTok\Exception\UnexpectedValueException;
use OpenTok\Archive;

define('OPENTOK_DEBUG', true);

class OpenTokTest extends TestCase
{
    /**
     * @var string
     */
    protected $API_KEY;

    /**
     * @var string
     */
    protected $API_SECRET;

    /**
     * @var OpenTok
     */
    protected $opentok;

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var string
     */
    protected static $mockBasePath;

    public static function setUpBeforeClass(): void
    {
        self::$mockBasePath = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'mock' . DIRECTORY_SEPARATOR;
    }

    /**
     * @param array<array> $mocks
     */
    private function setupVonageVideoApiWithMocks(array $mocks): void
    {
        $this->API_KEY = defined('API_KEY') ? API_KEY : '12345678';
        $this->API_SECRET = defined('API_SECRET') ? API_SECRET : '0123456789abcdef0123456789abcdef0123456789';
        $responses = TestHelpers::mocksToResponses($mocks, self::$mockBasePath);

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

        $this->opentok = new OpenTok($this->API_KEY, $this->API_SECRET, ['client' => $this->client]);
    }

    private function setupVonageVideoApi(): void
    {
        $this->setupVonageVideoApiWithMocks([]);
    }

    public function testCanBeInitialized(): void
    {
        $this->setupVonageVideoApi();
        $this->assertInstanceOf('OpenTok\OpenTok', $this->opentok);
    }

    public function testFailsOnInvalidInitialization(): void
    {
        $this->setupVonageVideoApi();

        if (class_exists('ArgumentCountError')) {
            $this->expectException(ArgumentCountError::class);
        } else {
            $this->expectException(PHPUnit_Framework_Error_Warning::class);
        }

        $opentok = new OpenTok();
    }

    public function testCreatesDefaultSession(): void
    {
        $this->setupVonageVideoApiWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'text/xml'
            ],
            'path' => 'session/create/relayed'
        ]]);

        $session = $this->opentok->createSession();

        $this->assertCount(1, $this->historyContainer);
        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/session/create', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateVonageVideoAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $userAgent = $request->getHeaderLine('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/4.9.1', $userAgent);

        $p2p_preference = $this->getPostField($request, 'p2p.preference');
        $this->assertEquals('enabled', $p2p_preference);

        $this->assertInstanceOf('OpenTok\Session', $session);
        // NOTE: this is an actual sessionId from the recorded response, this doesn't correspond to
        // the API Key and API Secret used to create the session.
        $this->assertEquals(
            '2_MX4xNzAxMjYzMX4xMjcuMC4wLjF-V2VkIEZlYiAyNiAxODo1NzoyNCBQU1QgMjAxNH4wLjU0MDU4ODc0fg',
            $session->getSessionId()
        );
    }

    private function getPostField($request, $targetKey)
    {
        $params = array_map(function ($item) {
            return explode('=', $item);
        }, explode('&', (string) $request->getBody()));

        $found = array_values(array_filter(
            $params,
            function ($item) use ($targetKey) {
                return is_array($item) ? $item[0] === $targetKey : false;
            }
        ));

        return count($found) > 0 ? $found[0][1] : '';
    }

    public function testCreatesMediaRoutedAndLocationSession(): void
    {
        $this->setupVonageVideoApiWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'text/xml'
            ],
            'path' => 'session/create/routed_location-12.34.56.78'
        ]]);

        $session = $this->opentok->createSession(array(
            'location' => '12.34.56.78',
            'mediaMode' => MediaMode::ROUTED
        ));

        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/session/create', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateVonageVideoAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $userAgent = $request->getHeaderLine('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/4.9.1', $userAgent);

        $location = $this->getPostField($request, 'location');
        $this->assertEquals('12.34.56.78', $location);

        $p2p_preference = $this->getPostField($request, 'p2p.preference');
        $this->assertEquals('disabled', $p2p_preference);

        $this->assertInstanceOf('OpenTok\Session', $session);
        // NOTE: this is an actual sessionId from the recorded response, this doesn't correspond to
        // the API Key and API Secret used to create the session.
        $this->assertEquals(
            '2_MX4xNzAxMjYzMX4xMjcuMC4wLjF-V2VkIEZlYiAyNiAxODo1NzoyNCBQU1QgMjAxNH4wLjU0MDU4ODc0fg',
            $session->getSessionId()
        );
    }

    public function testCreatesMediaRelayedSession(): void
    {
        $this->setupVonageVideoApiWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'text/xml'
            ],
            'path' => 'session/create/relayed'
        ]]);

        $session = $this->opentok->createSession(array(
            'mediaMode' => MediaMode::RELAYED
        ));

        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/session/create', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateVonageVideoAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $userAgent = $request->getHeaderLine('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/4.9.1', $userAgent);

        $p2p_preference = $this->getPostField($request, 'p2p.preference');
        $this->assertEquals('enabled', $p2p_preference);

        $this->assertInstanceOf('OpenTok\Session', $session);
        // NOTE: this is an actual sessionId from the recorded response, this doesn't correspond to
        // the API Key and API Secret used to create the session.
        $this->assertEquals(
            '2_MX4xNzAxMjYzMX4xMjcuMC4wLjF-V2VkIEZlYiAyNiAxODo1NzoyNCBQU1QgMjAxNH4wLjU0MDU4ODc0fg',
            $session->getSessionId()
        );
    }

    public function testCreatesAutoArchivedSession(): void
    {
        $this->setupVonageVideoApiWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'text/xml'
            ],
            'path' => 'session/create/alwaysarchived'
        ]]);

        $session = $this->opentok->createSession(array(
            'archiveMode' => ArchiveMode::ALWAYS
        ));

        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/session/create', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateVonageVideoAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $userAgent = $request->getHeaderLine('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/4.9.1', $userAgent);

        $archiveMode = $this->getPostField($request, 'archiveMode');
        $this->assertEquals('always', $archiveMode);

        $mediaMode = $this->getPostField($request, 'p2p.preference');
        $this->assertNotEquals('enabled', $mediaMode);

        $this->assertInstanceOf('OpenTok\Session', $session);
        // NOTE: this is an actual sessionId from the recorded response, this doesn't correspond to
        // the API Key and API Secret used to create the session.
        $this->assertEquals(
            '2_MX4xNzAxMjYzMX4xMjcuMC4wLjF-V2VkIEZlYiAyNiAxODo1NzoyNCBQU1QgMjAxNH4wLjU0MDU4ODc0fg',
            $session->getSessionId()
        );
    }

    public function testFailsWhenCreatingRelayedAutoArchivedSession(): void
    {
        $this->expectException('InvalidArgumentException');
        $this->setupVonageVideoApi();

        $session = $this->opentok->createSession(array(
            'mediaMode' => MediaMode::RELAYED,
            'archiveMode' => ArchiveMode::ALWAYS
        ));
    }

    public function testGeneratesToken(): void
    {
        $this->setupVonageVideoApi();

        // This sessionId is a fixture designed by using a known but bogus apiKey and apiSecret
        $sessionId = '1_MX4xMjM0NTY3OH4-VGh1IEZlYiAyNyAwNDozODozMSBQU1QgMjAxNH4wLjI0NDgyMjI';
        $bogusApiKey = '12345678';
        $bogusApiSecret = '0123456789abcdef0123456789abcdef0123456789';
        $opentok = new OpenTok($bogusApiKey, $bogusApiSecret);

        $token = $opentok->generateToken($sessionId);

        $this->assertIsString($token);
        $decodedToken = TestHelpers::decodeToken($token);
        $this->assertEquals($sessionId, $decodedToken['session_id']);
        $this->assertEquals($bogusApiKey, $decodedToken['partner_id']);
        $this->assertNotEmpty($decodedToken['nonce']);
        $this->assertNotEmpty($decodedToken['create_time']);
        $this->assertArrayNotHasKey('connection_data', $decodedToken);
        $this->assertNotEmpty($decodedToken['sig']);
        $this->assertEquals(hash_hmac('sha1', $decodedToken['dataString'], $bogusApiSecret), $decodedToken['sig']);
    }

    public function testGeneratesTokenWithRole(): void
    {
        $this->setupVonageVideoApi();

        // This sessionId is a fixture designed by using a known but bogus apiKey and apiSecret
        $sessionId = '1_MX4xMjM0NTY3OH4-VGh1IEZlYiAyNyAwNDozODozMSBQU1QgMjAxNH4wLjI0NDgyMjI';
        $bogusApiKey = '12345678';
        $bogusApiSecret = '0123456789abcdef0123456789abcdef0123456789';
        $opentok = new OpenTok($bogusApiKey, $bogusApiSecret);

        $token = $opentok->generateToken($sessionId, array('role' => Role::MODERATOR));

        $this->assertIsString($token);
        $decodedToken = TestHelpers::decodeToken($token);
        $this->assertEquals($sessionId, $decodedToken['session_id']);
        $this->assertEquals($bogusApiKey, $decodedToken['partner_id']);
        $this->assertNotEmpty($decodedToken['nonce']);
        $this->assertNotEmpty($decodedToken['create_time']);
        $this->assertArrayNotHasKey('connection_data', $decodedToken);
        $this->assertEquals('moderator', $decodedToken['role']);
        $this->assertNotEmpty($decodedToken['sig']);
        $this->assertEquals(hash_hmac('sha1', $decodedToken['dataString'], $bogusApiSecret), $decodedToken['sig']);
    }

    public function testGeneratesTokenWithExpireTime()
    {
        $this->setupVonageVideoApi();
        // This sessionId is a fixture designed by using a known but bogus apiKey and apiSecret
        $sessionId = '1_MX4xMjM0NTY3OH4-VGh1IEZlYiAyNyAwNDozODozMSBQU1QgMjAxNH4wLjI0NDgyMjI';
        $bogusApiKey = '12345678';
        $bogusApiSecret = '0123456789abcdef0123456789abcdef0123456789';
        $opentok = new OpenTok($bogusApiKey, $bogusApiSecret);

        $inOneHour = time() + (60 * 60);
        $token = $opentok->generateToken($sessionId, array('expireTime' => $inOneHour ));

        $this->assertIsString($token);
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

    public function testGeneratesTokenWithData()
    {
        $this->setupVonageVideoApi();
        // This sessionId is a fixture designed by using a known but bogus apiKey and apiSecret
        $sessionId = '1_MX4xMjM0NTY3OH4-VGh1IEZlYiAyNyAwNDozODozMSBQU1QgMjAxNH4wLjI0NDgyMjI';
        $bogusApiKey = '12345678';
        $bogusApiSecret = '0123456789abcdef0123456789abcdef0123456789';
        $opentok = new OpenTok($bogusApiKey, $bogusApiSecret);

        $userStatus = '{nick:"johnny",status:"hey there fellas!"}';
        $token = $opentok->generateToken($sessionId, array('data' => $userStatus ));

        $this->assertIsString($token);

        $decodedToken = TestHelpers::decodeToken($token);
        $this->assertEquals($sessionId, $decodedToken['session_id']);
        $this->assertEquals($bogusApiKey, $decodedToken['partner_id']);
        $this->assertNotEmpty($decodedToken['nonce']);
        $this->assertNotEmpty($decodedToken['create_time']);
        $this->assertEquals($userStatus, $decodedToken['connection_data']);
        $this->assertNotEmpty($decodedToken['role']);
        $this->assertNotEmpty($decodedToken['sig']);
        $this->assertEquals(hash_hmac('sha1', $decodedToken['dataString'], $bogusApiSecret), $decodedToken['sig']);
    }

    public function testGeneratesTokenWithInitialLayoutClassList(): void
    {
        $this->setupVonageVideoApi();
        // This sessionId is a fixture designed by using a known but bogus apiKey and apiSecret
        $sessionId = '1_MX4xMjM0NTY3OH4-VGh1IEZlYiAyNyAwNDozODozMSBQU1QgMjAxNH4wLjI0NDgyMjI';
        $bogusApiKey = '12345678';
        $bogusApiSecret = '0123456789abcdef0123456789abcdef0123456789';
        $opentok = new OpenTok($bogusApiKey, $bogusApiSecret);

        $initialLayouClassList = array('focus', 'main');

        $token = $opentok->generateToken($sessionId, array(
            'initialLayoutClassList' => $initialLayouClassList
        ));

        $this->assertIsString($token);

        $decodedToken = TestHelpers::decodeToken($token);
        $this->assertEquals($sessionId, $decodedToken['session_id']);
        $this->assertEquals($bogusApiKey, $decodedToken['partner_id']);
        $this->assertNotEmpty($decodedToken['nonce']);
        $this->assertNotEmpty($decodedToken['create_time']);
        $this->assertArrayNotHasKey('connection_data', $decodedToken);
        $this->assertEquals('publisher', $decodedToken['role']);
        $this->assertEquals(join(" ", $initialLayouClassList), $decodedToken['initial_layout_class_list']);
        $this->assertNotEmpty($decodedToken['sig']);
        $this->assertEquals(hash_hmac('sha1', $decodedToken['dataString'], $bogusApiSecret), $decodedToken['sig']);
    }

    public function testFailsWhenGeneratingTokenUsingInvalidRole(): void
    {
        $this->expectException('InvalidArgumentException');
        $this->setupVonageVideoApi();
        $token = $this->opentok->generateToken('SESSIONID', array('role' => 'notarole'));
    }

    public function testStartsArchive(): void
    {
        $this->setupVonageVideoApiWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/archive/session'
        ]]);

        // This sessionId was generated using a different apiKey, but this method doesn't do any
        // decoding to check, so it's fine.
        $sessionId = '2_MX44NTQ1MTF-flR1ZSBOb3YgMTIgMDk6NDA6NTkgUFNUIDIwMTN-MC43NjU0Nzh-';

        $archive = $this->opentok->startArchive($sessionId);

        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/' . $this->API_KEY . '/archive', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $contentType = $request->getHeaderLine('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateVonageVideoAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $userAgent = $request->getHeaderLine('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/4.9.1', $userAgent);

        $this->assertInstanceOf(Archive::class, $archive);
        $this->assertEquals(0, $archive->duration);
        $this->assertEquals('', $archive->reason);
        $this->assertEquals('started', $archive->status);
        $this->assertEquals(OutputMode::COMPOSED, $archive->outputMode);
        $this->assertNull($archive->name);
        $this->assertNull($archive->url);
        $this->assertTrue($archive->hasVideo);
        $this->assertTrue($archive->hasAudio);
        $this->assertEquals('auto', $archive->streamMode);
    }

    public function testStartsArchiveInManualStreamMode(): void
    {
        $this->setupVonageVideoApiWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/archive/manual_mode'
        ]]);

        // This sessionId was generated using a different apiKey, but this method doesn't do any
        // decoding to check, so it's fine.
        $sessionId = '2_MX44NTQ1MTF-flR1ZSBOb3YgMTIgMDk6NDA6NTkgUFNUIDIwMTN-MC43NjU0Nzh-';

        $archive = $this->opentok->startArchive($sessionId);

        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/' . $this->API_KEY . '/archive', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $contentType = $request->getHeaderLine('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateVonageVideoAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $userAgent = $request->getHeaderLine('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/4.9.1', $userAgent);

        $this->assertInstanceOf(Archive::class, $archive);
        $this->assertEquals(0, $archive->duration);
        $this->assertEquals('', $archive->reason);
        $this->assertEquals('manual', $archive->streamMode);
        $this->assertEquals('started', $archive->status);
        $this->assertEquals(OutputMode::COMPOSED, $archive->outputMode);
        $this->assertNull($archive->name);
        $this->assertNull($archive->url);
        $this->assertTrue($archive->hasVideo);
        $this->assertTrue($archive->hasAudio);
    }

    public function testStartsArchiveNamed(): void
    {
        $this->setupVonageVideoApiWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/archive/session_name-showtime'
        ]]);

        // This sessionId was generated using a different apiKey, but this method doesn't do any
        // decoding to check, so it's fine.
        $sessionId = '2_MX44NTQ1MTF-flR1ZSBOb3YgMTIgMDk6NDA6NTkgUFNUIDIwMTN-MC43NjU0Nzh-';

        $archive = $this->opentok->startArchive($sessionId, array( 'name' => 'showtime' ));

        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/' . $this->API_KEY . '/archive', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $contentType = $request->getHeaderLine('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateVonageVideoAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $userAgent = $request->getHeaderLine('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/4.9.1', $userAgent);

        $body = json_decode($request->getBody());
        $this->assertEquals($sessionId, $body->sessionId);
        $this->assertEquals('showtime', $body->name);

        $this->assertInstanceOf('OpenTok\Archive', $archive);
        // TODO: test the properties of the actual archive object
    }

    /**
     * this is the deprecated method signature, remove in v3.0.0 (and not before)
     * @todo Remove this when `startArchive` removes string support
     */
    public function testStartsArchiveNamedDeprecated(): void
    {
        $this->setupVonageVideoApiWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/archive/session_name-showtime'
        ]]);

        // This sessionId was generated using a different apiKey, but this method doesn't do any
        // decoding to check, so it's fine.
        $sessionId = '2_MX44NTQ1MTF-flR1ZSBOb3YgMTIgMDk6NDA6NTkgUFNUIDIwMTN-MC43NjU0Nzh-';

        @$archive = $this->opentok->startArchive($sessionId, 'showtime');

        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/' . $this->API_KEY . '/archive', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $contentType = $request->getHeaderLine('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateVonageVideoAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $userAgent = $request->getHeaderLine('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/4.9.1', $userAgent);

        $body = json_decode($request->getBody());
        $this->assertEquals($sessionId, $body->sessionId);
        $this->assertEquals('showtime', $body->name);

        $this->assertInstanceOf('OpenTok\Archive', $archive);
    }

    public function testStartsArchiveAudioOnly(): void
    {
        $this->setupVonageVideoApiWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/archive/session_hasVideo-false'
        ]]);

        // This sessionId was generated using a different apiKey, but this method doesn't do any
        // decoding to check, so it's fine.
        $sessionId = '2_MX44NTQ1MTF-flR1ZSBOb3YgMTIgMDk6NDA6NTkgUFNUIDIwMTN-MC43NjU0Nzh-';

        $archive = $this->opentok->startArchive($sessionId, array( 'hasVideo' => false ));

        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/' . $this->API_KEY . '/archive', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $contentType = $request->getHeaderLine('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');

        $this->assertEquals(
            true,
            TestHelpers::validateVonageVideoAuthHeader(
                $this->API_KEY,
                $this->API_SECRET,
                $authString
            )
        );

        $userAgent = $request->getHeaderLine('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/4.9.1', $userAgent);

        $body = json_decode($request->getBody());
        $this->assertEquals($sessionId, $body->sessionId);
        $this->assertEquals(false, $body->hasVideo);
        $this->assertEquals(true, $body->hasAudio);

        $this->assertInstanceOf('OpenTok\Archive', $archive);
    }

    public function testStartsArchiveIndividualOutput(): void
    {
        $this->setupVonageVideoApiWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/archive/session_outputMode-individual'
        ]]);

        // This sessionId was generated using a different apiKey, but this method doesn't do any
        // decoding to check, so it's fine.
        $sessionId = '2_MX44NTQ1MTF-flR1ZSBOb3YgMTIgMDk6NDA6NTkgUFNUIDIwMTN-MC43NjU0Nzh-';

        $archive = $this->opentok->startArchive($sessionId, array(
            'outputMode' => OutputMode::INDIVIDUAL
        ));

        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/' . $this->API_KEY . '/archive', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $contentType = $request->getHeaderLine('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateVonageVideoAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $userAgent = $request->getHeaderLine('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/4.9.1', $userAgent);

        $body = json_decode($request->getBody());
        $this->assertEquals($sessionId, $body->sessionId);
        $this->assertEquals('individual', $body->outputMode);

        $this->assertInstanceOf('OpenTok\Archive', $archive);
        $this->assertEquals(OutputMode::INDIVIDUAL, $archive->outputMode);
    }

    public function testStartsArchiveResolutionSD(): void
    {
        $this->setupVonageVideoApiWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/archive/session_resolution-sd'
        ]]);

        // This sessionId was generated using a different apiKey, but this method doesn't do any
        // decoding to check, so it's fine.
        $sessionId = '2_MX44NTQ1MTF-flR1ZSBOb3YgMTIgMDk6NDA6NTkgUFNUIDIwMTN-MC43NjU0Nzh-';

        $archive = $this->opentok->startArchive($sessionId, array(
            'resolution' => '640x480'
        ));

        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/' . $this->API_KEY . '/archive', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $contentType = $request->getHeaderLine('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateVonageVideoAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $userAgent = $request->getHeaderLine('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/4.9.1', $userAgent);

        $body = json_decode($request->getBody());
        $this->assertEquals($sessionId, $body->sessionId);
        $this->assertEquals('640x480', $body->resolution);

        $this->assertInstanceOf('OpenTok\Archive', $archive);
    }

    public function testStartsArchiveResolutionHD(): void
    {
        $this->setupVonageVideoApiWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/archive/session_resolution-hd'
        ]]);

        // This sessionId was generated using a different apiKey, but this method doesn't do any
        // decoding to check, so it's fine.
        $sessionId = '2_MX44NTQ1MTF-flR1ZSBOb3YgMTIgMDk6NDA6NTkgUFNUIDIwMTN-MC43NjU0Nzh-';

        $archive = $this->opentok->startArchive($sessionId, array(
            'resolution' => '1280x720'
        ));

        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/' . $this->API_KEY . '/archive', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $contentType = $request->getHeaderLine('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateVonageVideoAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $userAgent = $request->getHeaderLine('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/4.9.1', $userAgent);

        $body = json_decode($request->getBody());
        $this->assertEquals($sessionId, $body->sessionId);
        $this->assertEquals('1280x720', $body->resolution);

        $this->assertInstanceOf('OpenTok\Archive', $archive);
    }

    public function testStopsArchive(): void
    {
        $this->setupVonageVideoApiWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/archive/ARCHIVEID/stop'
        ]]);

        $archiveId = '063e72a4-64b4-43c8-9da5-eca071daab89';

        $archive = $this->opentok->stopArchive($archiveId);

        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/' . $this->API_KEY . '/archive/' . $archiveId . '/stop', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $contentType = $request->getHeaderLine('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateVonageVideoAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $userAgent = $request->getHeaderLine('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/4.9.1', $userAgent);

        $this->assertInstanceOf('OpenTok\Archive', $archive);
    }

    public function testGetsArchive(): void
    {
        // Arrange
        $this->setupVonageVideoApiWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/archive/ARCHIVEID/get'
        ]]);

        $archiveId = '063e72a4-64b4-43c8-9da5-eca071daab89';

        $archive = $this->opentok->getArchive($archiveId);

        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('GET', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/' . $this->API_KEY . '/archive/' . $archiveId, $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateVonageVideoAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $userAgent = $request->getHeaderLine('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/4.9.1', $userAgent);

        $this->assertInstanceOf('OpenTok\Archive', $archive);
    }

    public function testDeletesArchive(): void
    {
        $this->setupVonageVideoApiWithMocks([[
            'code' => 204
        ]]);

        $archiveId = '063e72a4-64b4-43c8-9da5-eca071daab89';

        $success = $this->opentok->deleteArchive($archiveId);

        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('DELETE', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/' . $this->API_KEY . '/archive/' . $archiveId, $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $contentType = $request->getHeaderLine('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateVonageVideoAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $userAgent = $request->getHeaderLine('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/4.9.1', $userAgent);

        $this->assertTrue($success);
    }

    public function testListsArchives()
    {
        $this->setupVonageVideoApiWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/archive/get'
        ]]);

        $archiveList = $this->opentok->listArchives();

        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('GET', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/' . $this->API_KEY . '/archive', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateVonageVideoAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $userAgent = $request->getHeaderLine('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/4.9.1', $userAgent);

        $this->assertInstanceOf('OpenTok\ArchiveList', $archiveList);
    }

    public function testListsArchivesWithOffsetAndCount()
    {
        $this->setupVonageVideoApiWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/archive/get_second'
        ]]);

        $archiveList = $this->opentok->listArchives(1, 1);

        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('GET', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/' . $this->API_KEY . '/archive', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateVonageVideoAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $userAgent = $request->getHeaderLine('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/4.9.1', $userAgent);

        $this->assertInstanceOf('OpenTok\ArchiveList', $archiveList);
        $this->assertEquals(1, $archiveList->totalCount());
        $this->assertEquals('832641bf-5dbf-41a1-ad94-fea213e59a92', $archiveList->getItems()[0]->id);
    }

    public function testListsArchivesWithSessionId(): void
    {
        $this->setupVonageVideoApiWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/archive/get_third'
        ]]);

        $sessionId = '1_MX4xMjM0NTY3OH4-VGh1IEZlYiAyNyAwNDozODozMSBQU1QgMjAxNH4wLjI0NDgyMjI';
        $bogusApiKey = '12345678';
        $bogusApiSecret = '0123456789abcdef0123456789abcdef0123456789';

        //@TODO does this work? Looks false positive to me
        $opentok = new OpenTok($bogusApiKey, $bogusApiSecret);
        $archiveList = $this->opentok->listArchives(0, null, $sessionId);

        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('GET', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/' . $this->API_KEY . '/archive', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateVonageVideoAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $userAgent = $request->getHeaderLine('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/4.9.1', $userAgent);

        $this->assertInstanceOf('OpenTok\ArchiveList', $archiveList);
        $this->assertEquals(2, $archiveList->totalCount());
        $this->assertEquals($sessionId, $archiveList->getItems()[0]->sessionId);
        $this->assertEquals($sessionId, $archiveList->getItems()[1]->sessionId);
        $this->assertEquals('b8f64de1-e218-4091-9544-4cbf369fc238', $archiveList->getItems()[0]->id);
        $this->assertEquals('832641bf-5dbf-41a1-ad94-fea213e59a92', $archiveList->getItems()[1]->id);
    }

    public function testFailsWhenListingArchivesWithTooLargeCount()
    {
        $this->expectException('InvalidArgumentException');

        $this->setupVonageVideoApiWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/archive/get'
        ]]);

        $archiveList = $this->opentok->listArchives(0, 1001);

        $this->assertCount(0, $this->historyContainer);
    }

    // TODO: sloppy test in a pinch
    public function testGetsExpiredArchive(): void
    {
        $this->setupVonageVideoApiWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/archive/ARCHIVEID/get-expired'
        ]]);

        $archiveId = '063e72a4-64b4-43c8-9da5-eca071daab89';

        $archive = $this->opentok->getArchive($archiveId);

        $this->assertInstanceOf('OpenTok\Archive', $archive);
        $this->assertEquals("expired", $archive->status);
    }

    public function testForceDisconnect(): void
    {
        $this->setupVonageVideoApiWithMocks([[
            'code' => 204
        ]]);

        $sessionId = '1_MX4xMjM0NTY3OH4-VGh1IEZlYiAyNyAwNDozODozMSBQU1QgMjAxNH4wLjI0NDgyMjI';

        $connectionId = '063e72a4-64b4-43c8-9da5-eca071daab89';

        $success = $this->opentok->forceDisconnect($sessionId, $connectionId);

        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('DELETE', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/' . $this->API_KEY . '/session/' . $sessionId . '/connection/' . $connectionId, $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $contentType = $request->getHeaderLine('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateVonageVideoAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $userAgent = $request->getHeaderLine('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/4.9.1', $userAgent);
        $this->assertTrue($success);
    }


    public function testForceDisconnectConnectionException(): void
    {
        $this->setupVonageVideoApiWithMocks([[
            'code' => 404
        ]]);

        $sessionId = '1_MX4xMjM0NTY3OH4-VGh1IEZlYiAyNyAwNDozODozMSBQU1QgMjAxNH4wLjI0NDgyMjI';

        $connectionId = '063e72a4-64b4-43c8-9da5-eca071daab89';

        $this->expectException('OpenTok\Exception\ForceDisconnectConnectionException');

        $this->opentok->forceDisconnect($sessionId, $connectionId);
    }

    public function testStartsBroadcast(): void
    {
        $this->setupVonageVideoApiWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => '/v2/project/APIKEY/broadcast/session_layout-bestfit'
        ]]);

        // This sessionId was generated using a different apiKey, but this method doesn't do any
        // decoding to check, so it's fine.
        $sessionId = '2_MX44NTQ1MTF-fjE0NzI0MzU2MDUyMjN-eVgwNFJhZmR6MjdockFHanpxNzBXaEFXfn4';

        $broadcast = $this->opentok->startBroadcast($sessionId);

        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/' . $this->API_KEY . '/broadcast', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $contentType = $request->getHeaderLine('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateVonageVideoAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $userAgent = $request->getHeaderLine('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/4.9.1', $userAgent);

        $this->assertInstanceOf('OpenTok\Broadcast', $broadcast);
        $this->assertIsString($broadcast->id);
        $this->assertEquals($sessionId, $broadcast->sessionId);
        $this->assertIsArray($broadcast->broadcastUrls);
        $this->assertArrayHasKey('hls', $broadcast->broadcastUrls);
        $this->assertIsString($broadcast->broadcastUrls['hls']);
        $this->assertIsString($broadcast->hlsUrl);
        $this->assertFalse($broadcast->isStopped);
    }

    public function testStartBroadcastWithOptions(): void
    {
        $this->setupVonageVideoApiWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => '/v2/project/APIKEY/broadcast/session_layout-bestfit'
        ]]);

        // This sessionId was generated using a different apiKey, but this method doesn't do any
        // decoding to check, so it's fine.
        $sessionId = '2_MX44NTQ1MTF-fjE0NzI0MzU2MDUyMjN-eVgwNFJhZmR6MjdockFHanpxNzBXaEFXfn4';

        $maxDuration = 5400;
        $resolution = '1280x720';
        $broadcastOptions = [
            'maxDuration' => $maxDuration,
            'resolution' => $resolution,
        ];

        $broadcast = $this->opentok->startBroadcast($sessionId, $broadcastOptions);

        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/' . $this->API_KEY . '/broadcast', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $contentType = $request->getHeaderLine('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateVonageVideoAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $userAgent = $request->getHeaderLine('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/4.9.1', $userAgent);

        $this->assertInstanceOf('OpenTok\Broadcast', $broadcast);
        $this->assertIsString($broadcast->id);
        $this->assertEquals($sessionId, $broadcast->sessionId);
        $this->assertEquals($maxDuration, $broadcast->maxDuration);
        $this->assertEquals($resolution, $broadcast->resolution);
        $this->assertIsArray($broadcast->broadcastUrls);
        $this->assertArrayHasKey('hls', $broadcast->broadcastUrls);
        $this->assertIsString($broadcast->broadcastUrls['hls']);
        $this->assertIsString($broadcast->hlsUrl);
        $this->assertFalse($broadcast->isStopped);
    }

    public function testStopsBroadcast(): void
    {
        $this->setupVonageVideoApiWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => '/v2/project/APIKEY/broadcast/BROADCASTID/stop'
        ]]);

        $broadcastId = 'BROADCASTID';

        $broadcast = $this->opentok->stopBroadcast($broadcastId);

        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/' . $this->API_KEY . '/broadcast/' . $broadcastId . '/stop', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateVonageVideoAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $userAgent = $request->getHeaderLine('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/4.9.1', $userAgent);

        $this->assertInstanceOf('OpenTok\Broadcast', $broadcast);
        $this->assertTrue($broadcast->isStopped);
    }

    public function testGetsBroadcast(): void
    {
        $this->setupVonageVideoApiWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => '/v2/project/APIKEY/broadcast/BROADCASTID/get'
        ]]);

        $broadcastId = 'BROADCASTID';

        $broadcast = $this->opentok->getBroadcast($broadcastId);

        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('GET', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/' . $this->API_KEY . '/broadcast/' . $broadcastId, $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateVonageVideoAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $userAgent = $request->getHeaderLine('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/4.9.1', $userAgent);

        $this->assertInstanceOf('OpenTok\Broadcast', $broadcast);
    }

    public function testUpdatesBroadcastLayoutWithPredefined(): void
    {
        $this->setupVonageVideoApiWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => '/v2/project/APIKEY/broadcast/BROADCASTID/layout/type-pip'
        ]]);

        $broadcastId = 'BROADCASTID';
        $layout = Layout::getPIP();

        $this->opentok->updateBroadcastLayout($broadcastId, $layout);

        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('PUT', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/' . $this->API_KEY . '/broadcast/' . $broadcastId . '/layout', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $contentType = $request->getHeaderLine('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateVonageVideoAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $body = json_decode($request->getBody());
        $this->assertEquals('pip', $body->type);

        $userAgent = $request->getHeaderLine('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/4.9.1', $userAgent);
    }

    public function testUpdatesBroadcastLayoutWithCustom(): void
    {
        $this->setupVonageVideoApiWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => '/v2/project/APIKEY/broadcast/BROADCASTID/layout/type-custom'
        ]]);

        $broadcastId = 'BROADCASTID';
        $stylesheet = '.classname { height: 1px; width: 1px }';
        $layout = Layout::createCustom(array(
            'stylesheet' => $stylesheet
        ));

        $this->opentok->updateBroadcastLayout($broadcastId, $layout);

        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('PUT', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/' . $this->API_KEY . '/broadcast/' . $broadcastId . '/layout', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $contentType = $request->getHeaderLine('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateVonageVideoAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $body = json_decode($request->getBody());
        $this->assertEquals('custom', $body->type);
        $this->assertEquals($stylesheet, $body->stylesheet);

        $userAgent = $request->getHeaderLine('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/4.9.1', $userAgent);
    }

    public function testUpdatesStreamLayoutClassList()
    {
        $this->setupVonageVideoApiWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => '/v2/project/APIKEY/session/SESSIONID/stream/STREAMID/layoutClassList'
        ]]);

        $sessionId = 'SESSIONID';
        $streamId = 'STREAMID';
        $layoutClassList = ['foo', 'bar'];

        $this->opentok->updateStream($sessionId, $streamId, array(
            'layoutClassList' => $layoutClassList
        ));

        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('PUT', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/' . $this->API_KEY . '/session/' . $sessionId . '/stream/' . $streamId, $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $contentType = $request->getHeaderLine('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateVonageVideoAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $body = json_decode($request->getBody());
        $this->assertEquals($layoutClassList, $body->layoutClassList);

        $userAgent = $request->getHeaderLine('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/4.9.1', $userAgent);
    }

    public function testGetStream(): void
    {
        $this->setupVonageVideoApiWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => '/v2/project/APIKEY/session/SESSIONID/stream/STREAMID/get'
        ]]);

        $sessionId = 'SESSIONID';
        $streamId = '8b732909-0a06-46a2-8ea8-074e64d43422';

        $streamData = $this->opentok->getStream($sessionId, $streamId);
        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('GET', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/' . $this->API_KEY . '/session/' . $sessionId . '/stream/' . $streamId, $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateVonageVideoAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $this->assertInstanceOf('OpenTok\Stream', $streamData);
        $this->assertNotNull($streamData->id);
        $this->assertNotNull($streamData->name);
        $this->assertNotNull($streamData->videoType);
        $this->assertNotNull($streamData->layoutClassList);

        $userAgent = $request->getHeaderLine('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/4.9.1', $userAgent);
    }

    public function testSipCall(): void
    {
        $this->setupVonageVideoApiWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/dial'
        ]]);

        $sessionId = '1_MX4xMjM0NTY3OH4-VGh1IEZlYiAyNyAwNDozODozMSBQU1QgMjAxNH4wLjI0NDgyMjI';
        $bogusApiKey = '12345678';
        $bogusApiSecret = '0123456789abcdef0123456789abcdef0123456789';
        $bogusToken = 'T1==TEST';
        $bogusSipUri = 'sip:john@doe.com';

        // @TODO does this work? Looks like false positive to me
        $opentok = new OpenTok($bogusApiKey, $bogusApiSecret);

        $sipCall = $this->opentok->dial($sessionId, $bogusToken, $bogusSipUri);

        $this->assertInstanceOf('OpenTok\SipCall', $sipCall);
        $this->assertNotNull($sipCall->id);
        $this->assertNotNull($sipCall->connectionId);
        $this->assertNotNull($sipCall->streamId);
    }

    public function testSipCallWithAuth(): void
    {
        $this->setupVonageVideoApiWithMocks([[
            'code' => 200,
            'path' => 'v2/project/APIKEY/dial'
        ]]);

        $sessionId = '1_MX4xMjM0NTY3OH4-VGh1IEZlYiAyNyAwNDozODozMSBQU1QgMjAxNH4wLjI0NDgyMjI';
        $bogusApiKey = '12345678';
        $bogusApiSecret = '0123456789abcdef0123456789abcdef0123456789';
        $bogusToken = 'T1==TEST';
        $bogusSipUri = 'sip:john@doe.com';

        // @TODO does this work? Looks like false positive to me
        $opentok = new OpenTok($bogusApiKey, $bogusApiSecret);

        $auth = [
            'username' => 'john',
            'password' => 'doe'
        ];

        $sipCall = $this->opentok->dial($sessionId, $bogusToken, $bogusSipUri, array(
            'auth' => $auth
        ));

        $this->assertInstanceOf('OpenTok\SipCall', $sipCall);
        $this->assertNotNull($sipCall->id);
        $this->assertNotNull($sipCall->connectionId);
        $this->assertNotNull($sipCall->streamId);

        $this->assertCount(1, $this->historyContainer);
        $request = $this->historyContainer[0]['request'];

        $body = json_decode($request->getBody());
        $this->assertEquals($auth['username'], $body->sip->auth->username);
        $this->assertEquals($auth['password'], $body->sip->auth->password);
    }

    public function testFailedSipCall(): void
    {
        $this->setupVonageVideoApiWithMocks([[
            'code' => 500
        ]]);

        $sessionId = '1_MX4xMjM0NTY3OH4-VGh1IEZlYiAyNyAwNDozODozMSBQU1QgMjAxNH4wLjI0NDgyMjI';
        $bogusApiKey = '12345678';
        $bogusApiSecret = '0123456789abcdef0123456789abcdef0123456789';
        $bogusToken = 'T1==TEST';
        $bogusSipUri = 'sip:john@doe.com';

        // @TODO does this work? Looks like false positive to me
        $opentok = new OpenTok($bogusApiKey, $bogusApiSecret);

        $sipCall = null;
        try {
            $sipCall = $this->opentok->dial($sessionId, $bogusToken, $bogusSipUri);
            $this->assertNull($sipCall);
        } catch (\Exception $e) {
            $this->assertNull($sipCall);
        }
    }

    public function testSipCallFrom(): void
    {
        $this->setupVonageVideoApiWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/dial'
        ]]);

        $sessionId = '1_MX4xMjM0NTY3OH4-VGh1IEZlYiAyNyAwNDozODozMSBQU1QgMjAxNH4wLjI0NDgyMjI';
        $bogusToken = 'T1==TEST';
        $bogusSipUri = 'sip:john@doe.com';

        $from = "+0034123445566@opentok.me";

        $sipCall = $this->opentok->dial($sessionId, $bogusToken, $bogusSipUri, [
            'from' => $from
        ]);

        $this->assertInstanceOf('OpenTok\SipCall', $sipCall);
        $this->assertNotNull($sipCall->id);
        $this->assertNotNull($sipCall->connectionId);
        $this->assertNotNull($sipCall->streamId);

        $this->assertCount(1, $this->historyContainer);
        $request = $this->historyContainer[0]['request'];

        $body = json_decode($request->getBody());
        $this->assertEquals($from, $body->sip->from);
    }

    public function testSipCallVideo(): void
    {
        $this->setupVonageVideoApiWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/dial'
        ]]);

        $sessionId = '1_MX4xMjM0NTY3OH4-VGh1IEZlYiAyNyAwNDozODozMSBQU1QgMjAxNH4wLjI0NDgyMjI';
        $bogusToken = 'T1==TEST';
        $bogusSipUri = 'sip:john@doe.com';

        $sipCall = $this->opentok->dial($sessionId, $bogusToken, $bogusSipUri, ['video' => true]);

        $this->assertInstanceOf('OpenTok\SipCall', $sipCall);
        $this->assertNotNull($sipCall->id);
        $this->assertNotNull($sipCall->connectionId);
        $this->assertNotNull($sipCall->streamId);

        $this->assertCount(1, $this->historyContainer);
        $request = $this->historyContainer[0]['request'];

        $body = json_decode($request->getBody());
        $this->assertEquals(true, $body->sip->video);
    }

    public function testPlayDTMF(): void
    {
        $this->setupVonageVideoApiWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/session/SESSIONID/play-dtmf'
        ]]);

        $sessionId = '1_MX4xMjM0NTY3OH4-VGh1IEZlYiAyNyAwNDozODozMSBQU1QgMjAxNH4wLjI0NDgyMjI';
        $digits = '1p713#';

        $this->opentok->playDTMF($sessionId, $digits);

        $this->assertCount(1, $this->historyContainer);
        $request = $this->historyContainer[0]['request'];

        $body = json_decode($request->getBody());
        $this->assertEquals($digits, $body->digits);
    }

    public function testPlayDTMFIntoConnection(): void
    {
        $this->setupVonageVideoApiWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/session/SESSIONID/play-dtmf'
        ]]);

        $sessionId = '1_MX4xMjM0NTY3OH4-VGh1IEZlYiAyNyAwNDozODozMSBQU1QgMjAxNH4wLjI0NDgyMjI';
        $connectionId = 'da9cb410-e29b-4c2d-ab9e-fe65bf83fcaf';
        $digits = '1p713#';

        $this->opentok->playDTMF($sessionId, $digits, $connectionId);

        $this->assertCount(1, $this->historyContainer);
        $request = $this->historyContainer[0]['request'];

        $body = json_decode($request->getBody());
        $this->assertEquals($digits, $body->digits);
    }

    public function testDTMFFailsValidation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('DTMF digits can only support 0-9, p, #, and * characters');

        $this->setupVonageVideoApi();
        $sessionId = '1_MX4xMjM0NTY3OH4-VGh1IEZlYiAyNyAwNDozODozMSBQU1QgMjAxNH4wLjI0NDgyMjI';
        $this->opentok->playDTMF($sessionId, 'bob');
    }

    /**
     * Tests that we properly handle a 400 error from the API
     * Ideally with the validator we fail before the API request is even made,
     * but this will make sure that we still properly handle a 400 error. For
     * this to work we do send a valid DTMF string however, to satisfy the
     * validator.
     */
    public function testPlayDTMFThrows400(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('The OpenTok API request failed: Invalid DTMF Digits');

        $this->setupVonageVideoApiWithMocks([[
            'code' => 400,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/session/SESSIONID/play-dtmf-400'
        ]]);

        $sessionId = '1_MX4xMjM0NTY3OH4-VGh1IEZlYiAyNyAwNDozODozMSBQU1QgMjAxNH4wLjI0NDgyMjI';
        $digits = '1p713#';

        $this->opentok->playDTMF($sessionId, $digits);
    }

    public function testSignalData(): void
    {
        $this->setupVonageVideoApiWithMocks([[
            'code' => 204
        ]]);

        $sessionId = '1_MX4xMjM0NTY3OH4-VGh1IEZlYiAyNyAwNDozODozMSBQU1QgMjAxNH4wLjI0NDgyMjI';
        $bogusApiKey = '12345678';
        $bogusApiSecret = '0123456789abcdef0123456789abcdef0123456789';

        $opentok = new OpenTok($bogusApiKey, $bogusApiSecret);

        $payload = [
            'data' => 'apple',
            'type' => 'signal type sample'
        ];

        $this->opentok->signal($sessionId, $payload);

        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/' . $this->API_KEY . '/session/' . $sessionId . '/signal', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateVonageVideoAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $userAgent = $request->getHeaderLine('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/4.9.1', $userAgent);

        $body = json_decode($request->getBody());
        $this->assertEquals('apple', $body->data);
        $this->assertEquals('signal type sample', $body->type);
    }

    public function testSignalWithConnectionId(): void
    {
        $this->setupVonageVideoApiWithMocks([[
            'code' => 204
        ]]);

        $sessionId = '1_MX4xMjM0NTY3OH4-VGh1IEZlYiAyNyAwNDozODozMSBQU1QgMjAxNH4wLjI0NDgyMjI';
        $bogusApiKey = '12345678';
        $bogusApiSecret = '0123456789abcdef0123456789abcdef0123456789';
        $connectionId = 'da9cb410-e29b-4c2d-ab9e-fe65bf83fcaf';
        $payload = array(
            'type' => 'rest',
            'data' => 'random message'
        );

        // @TODO does this work? Looks like false positive to me
        $opentok = new OpenTok($bogusApiKey, $bogusApiSecret);

        $this->opentok->signal($sessionId, $payload, $connectionId);

        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/' . $this->API_KEY . '/session/' . $sessionId . '/connection/' . $connectionId . '/signal', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateVonageVideoAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $userAgent = $request->getHeaderLine('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/4.9.1', $userAgent);

        $body = json_decode($request->getBody());
        $this->assertEquals('random message', $body->data);
        $this->assertEquals('rest', $body->type);
    }

    /**
     * @todo Fix this test, not even sure what it's supposed to be doing honestly.
     */
    public function testSignalWithEmptyPayload(): void
    {
        $this->setupVonageVideoApiWithMocks([[
            'code' => 204
        ]]);

        $sessionId = '1_MX4xMjM0NTY3OH4-VGh1IEZlYiAyNyAwNDozODozMSBQU1QgMjAxNH4wLjI0NDgyMjI';
        $bogusApiKey = '12345678';
        $bogusApiSecret = '0123456789abcdef0123456789abcdef0123456789';
        $payload = array();

        $opentok = new OpenTok($bogusApiKey, $bogusApiSecret);

        // Act
        try {
            $this->opentok->signal($sessionId, $payload);
            $this->assertTrue(true);
        } catch (\Exception $e) {
        }
    }

    public function testSignalConnectionException(): void
    {
        $this->setupVonageVideoApiWithMocks([[
            'code' => 404
        ]]);

        $sessionId = '1_MX4xMjM0NTY3OH4-VGh1IEZlYiAyNyAwNDozODozMSBQU1QgMjAxNH4wLjI0NDgyMjI';
        $bogusApiKey = '12345678';
        $bogusApiSecret = '0123456789abcdef0123456789abcdef0123456789';
        $connectionId = 'da9cb410-e29b-4c2d-ab9e-fe65bf83fcaf';
        $payload = array(
            'type' => 'rest',
            'data' => 'random message'
        );

        // @TODO does this work? Looks like false positive to me
        $opentok = new OpenTok($bogusApiKey, $bogusApiSecret);

        $this->expectException('OpenTok\Exception\SignalConnectionException');
        // Act
        $this->opentok->signal($sessionId, $payload, $connectionId);
    }

    public function testSignalUnexpectedValueException()
    {
        $this->setupVonageVideoApiWithMocks([[
            'code' => 413
        ]]);

        $sessionId = '1_MX4xMjM0NTY3OH4-VGh1IEZlYiAyNyAwNDozODozMSBQU1QgMjAxNH4wLjI0NDgyMjI';
        $bogusApiKey = '12345678';
        $bogusApiSecret = '0123456789abcdef0123456789abcdef0123456789';
        $connectionId = 'da9cb410-e29b-4c2d-ab9e-fe65bf83fcaf';
        $payload = [
            'type' => 'rest',
            'data' => 'more than 128 bytes'
        ];

        // @TODO does this work? Looks like false positive to me
        $opentok = new OpenTok($bogusApiKey, $bogusApiSecret);

        $this->expectException('OpenTok\Exception\SignalUnexpectedValueException');

        // Act
        $this->opentok->signal($sessionId, $payload, $connectionId);
    }

    public function testListStreams(): void
    {
        $this->setupVonageVideoApiWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => '/v2/project/APIKEY/session/SESSIONID/stream/get'
        ]]);

        $sessionId = '1_MX4xMjM0NTY3OH4-VGh1IEZlYiAyNyAwNDozODozMSBQU1QgMjAxNH4wLjI0NDgyMjI';
        $bogusApiKey = '12345678';
        $bogusApiSecret = '0123456789abcdef0123456789abcdef0123456789';

        // @TODO does this work? Looks like false positive to me
        $opentok = new OpenTok($bogusApiKey, $bogusApiSecret);

        $streamList = $this->opentok->listStreams($sessionId);

        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('GET', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/' . $this->API_KEY . '/session/' . $sessionId . '/stream/', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateVonageVideoAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $userAgent = $request->getHeaderLine('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/4.9.1', $userAgent);

        $this->assertInstanceOf('OpenTok\StreamList', $streamList);
    }

    public function testsSetArchiveLayoutWithPredefined(): void
    {
        $this->setupVonageVideoApiWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ]
        ]]);

        $archiveId = 'ARCHIVEID';
        $layout = Layout::getPIP();

        $this->opentok->setArchiveLayout($archiveId, $layout);

        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('PUT', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/' . $this->API_KEY . '/archive/' . $archiveId . '/layout', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $contentType = $request->getHeaderLine('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateVonageVideoAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $body = json_decode($request->getBody());
        $this->assertEquals('pip', $body->type);

        $userAgent = $request->getHeaderLine('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/4.9.1', $userAgent);
    }

    public function testsSetArchiveLayoutWithCustom(): void
    {
        $this->setupVonageVideoApiWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ]
        ]]);

        $archiveId = 'ARCHIVEID';
        $stylesheet = '.classname { height: 1px; width: 1px }';
        $options = array(
            'stylesheet' => $stylesheet
        );
        $layout = Layout::createCustom($options);

        $this->opentok->setArchiveLayout($archiveId, $layout);

        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('PUT', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/' . $this->API_KEY . '/archive/' . $archiveId . '/layout', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $contentType = $request->getHeaderLine('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateVonageVideoAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $body = json_decode($request->getBody());
        $this->assertEquals('custom', $body->type);
        $this->assertEquals($stylesheet, $body->stylesheet);

        $userAgent = $request->getHeaderLine('User-Agent');
        $this->assertNotEmpty($userAgent);
        $this->assertStringStartsWith('OpenTok-PHP-SDK/4.9.1', $userAgent);
    }

    /**
     * Makes sure that Guzzle internally keeps a null/indefinate timeout by default
     * This makes sure that internal existing behavior has not changed
     */
    public function testDefaultTimeoutDefaultsToNull(): void
    {
        $this->setupVonageVideoApi();

        $opentokReflection = new \ReflectionClass($this->opentok);
        $opentokClient = $opentokReflection->getProperty('client');
        $opentokClient->setAccessible(true);
        $opentokClient = $opentokClient->getValue($this->opentok);
        $opentokClientReflection = new \ReflectionClass($opentokClient);

        /** @var \GuzzleHttp\Client $guzzleClient */
        $guzzleClient = $opentokClientReflection->getProperty('client');
        $guzzleClient->setAccessible(true);
        $guzzleClient = $guzzleClient->getValue($opentokClient);

        $this->assertSame(null, $guzzleClient->getConfig('timeout'));
    }

    /**
     * Makes sure that Guzzle gets configured with a user defined timeout
     */
    public function testDefaultTimeoutCanBeOverriden(): void
    {
        $opentok = new OpenTok('1234', 'abd', ['timeout' => 400]);

        $opentokReflection = new \ReflectionClass($opentok);
        $opentokClient = $opentokReflection->getProperty('client');
        $opentokClient->setAccessible(true);
        $opentokClient = $opentokClient->getValue($opentok);
        $opentokClientReflection = new \ReflectionClass($opentokClient);

        /** @var \GuzzleHttp\Client $guzzleClient */
        $guzzleClient = $opentokClientReflection->getProperty('client');
        $guzzleClient->setAccessible(true);
        $guzzleClient = $guzzleClient->getValue($opentokClient);

        $this->assertSame(400, $guzzleClient->getConfig('timeout'));
    }

    /**
     * User-provided default timeout must be numeric
     */
    public function testDefaultTimeoutErrorsIfNotNumeric(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Default Timeout must be a number greater than zero');
        new OpenTok('1234', 'abd', ['timeout' => 'bob']);
    }

    /**
     * User-provided default timeout must be greater than 0
     */
    public function testDefaultTimeoutErrorsIfLessThanZero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Default Timeout must be a number greater than zero');
        new OpenTok('1234', 'abd', ['timeout' => -1]);
    }
}
