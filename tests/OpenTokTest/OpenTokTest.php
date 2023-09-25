<?php

namespace OpenTokTest;

use OpenTok\Render;
use OpenTok\Role;
use OpenTok\Layout;
use OpenTok\OpenTok;
use OpenTok\MediaMode;
use ArgumentCountError;
use DomainException;
use OpenTok\OutputMode;
use OpenTok\ArchiveMode;
use OpenTok\StreamMode;
use OpenTok\Util\Client;
use GuzzleHttp\Middleware;
use GuzzleHttp\HandlerStack;
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Handler\MockHandler;
use OpenTok\Exception\InvalidArgumentException;
use OpenTok\Session;

define('OPENTOK_DEBUG', true);

class OpenTokTest extends TestCase
{
    protected $API_KEY;
    protected $API_SECRET;

    /**
     * @var OpenTok
     */
    protected $opentok;
    protected $client;

    protected static $mockBasePath;

    public $historyContainer;

    public static function setUpBeforeClass(): void
    {
        self::$mockBasePath = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'mock' . DIRECTORY_SEPARATOR;
    }

    private function setupOTWithMocks($mocks, $customAgent = false): void
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

        if ($customAgent) {
            $clientOptions = array_merge($clientOptions, $customAgent);
        }

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

    private function setupOT(): void
    {
        $this->setupOTWithMocks([]);
    }

    public function testCanBeInitialized(): void
    {
        $this->setupOT();
        $this->assertInstanceOf('OpenTok\OpenTok', $this->opentok);
    }

    public function testFailsOnInvalidInitialization(): void
    {
        // Arrange
        $this->setupOT();
        if (class_exists('ArgumentCountError')) {
            $this->expectException(ArgumentCountError::class);
        } else {
            $this->expectException(PHPUnit_Framework_Error_Warning::class);
        }
        $opentok = new OpenTok();
    }

    public function testCanStartRender(): void
    {
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/render/render_start'
        ]]);

        $render = $this->opentok->startRender(
            '2_MX4xMDBfjE0Mzc2NzY1NDgwMTJ-TjMzfn4',
            'e2343f23456g34709d2443a234',
            'https://webapp.customer.com',
            2900,
            '1280x720',
            'https://sendcallbacks.to.me',
            [
                'name' => 'Composed stream for live event'
            ]
        );
        $this->assertInstanceOf(Render::class, $render);
        $this->assertEquals('2_MX4xMDBfjE0Mzc2NzY1NDgwMTJ-TjMzfn4', $render->sessionId);
        $this->assertEquals('started', $render->status);
    }

    public function testCanGetRender(): void
    {
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/render/render_get'
        ]]);

        $render = $this->opentok->getRender('80abaf0d-25a3-4efc-968f-6268d620668d');

        $this->assertInstanceOf(Render::class, $render);
        $this->assertEquals('1_MX4yNzA4NjYxMn5-MTU0NzA4MDUyMTEzNn5sOXU5ZnlWYXplRnZGblV4RUo3dXJpZk1-fg', $render->sessionId);
        $this->assertEquals('failed', $render->status);
    }

    public function testCanStopRender(): void
    {
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/render/render_get'
        ]]);

        $response = $this->opentok->stopRender('80abaf0d-25a3-4efc-968f-6268d620668d');

        $this->assertTrue($response);
    }

    public function testCannotStopUnknownRender(): void
    {
        $this->setupOTWithMocks([[
            'code' => 404,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/render/render_get'
        ]]);

        $response = $this->opentok->stopRender('80abaf0d-25a3-4efc-968f-6268d620668d');

        $this->assertFalse($response);
    }

    public function testCanListRenders(): void
    {
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/render/render_list'
        ]]);

        $response = $this->opentok->listRenders();
        $this->assertEquals('2', $response['count']);
        $this->assertEquals('80abaf0d-25a3-4efc-968f-6268d620668d', $response['items'][0]['id']);
    }

    public function testCreatesDefaultSession(): void
    {
        // Arrange
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'text/xml'
            ],
            'path' => 'session/create/relayed'
        ]]);

        // Act
        $session = $this->opentok->createSession();

        // Assert
        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/session/create', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $p2p_preference = $this->getPostField($request, 'p2p.preference');
        $this->assertEquals('enabled', $p2p_preference);

        $e2ee = $this->getPostField($request, 'e2ee');
        $this->assertEquals('false', $e2ee);

        $this->assertInstanceOf('OpenTok\Session', $session);
        // NOTE: this is an actual sessionId from the recorded response, this doesn't correspond to
        // the API Key and API Secret used to create the session.
        $this->assertEquals(
            '2_MX4xNzAxMjYzMX4xMjcuMC4wLjF-V2VkIEZlYiAyNiAxODo1NzoyNCBQU1QgMjAxNH4wLjU0MDU4ODc0fg',
            $session->getSessionId()
        );
    }

    public function testCanStartAutoSessionWithArchiveNameAndResolution(): void
    {
        // Arrange
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'text/xml'
            ],
            'path' => 'session/create/alwaysarchived'
        ]]);

        $options = [
            'archiveMode' => ArchiveMode::ALWAYS,
            'archiveName' => 'testAutoArchives',
            'archiveResolution' => '640x480'
        ];

        // Act
        $session = $this->opentok->createSession($options);

        // Assert
        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/session/create', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $e2ee = $this->getPostField($request, 'e2ee');
        $this->assertEquals('false', $e2ee);

        $this->assertEquals('always', $this->getPostField($request, 'archiveMode'));
        $this->assertEquals('testAutoArchives', $this->getPostField($request, 'archiveName'));
        $this->assertEquals('640x480', $this->getPostField($request, 'archiveResolution'));


        $this->assertInstanceOf('OpenTok\Session', $session);
    }

    public function testCannotStartSessionWithArchiveNameInManualMode(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // Arrange
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'text/xml'
            ],
            'path' => 'session/create/relayed'
        ]]);

        $options = [
            'archiveName' => 'testAutoArchives',
        ];

        // Act
        $session = $this->opentok->createSession($options);
    }

    public function testCannotStartSessionWithArchiveResolutionInManualMode(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // Arrange
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'text/xml'
            ],
            'path' => 'session/create/relayed'
        ]]);

        $options = [
            'archiveResolution' => '640x480'
        ];

        // Act
        $session = $this->opentok->createSession($options);
    }

    public function testCannotStartSessionWithInvalidResolutionInAutoArchive(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // Arrange
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'text/xml'
            ],
            'path' => 'session/create/relayed'
        ]]);

        $options = [
            'archiveMode' => ArchiveMode::ALWAYS,
            'archiveResolution' => '680x440',
        ];

        // Act
        $session = $this->opentok->createSession($options);
    }

    public function testCreatesE2EESession(): void
    {
        // Arrange
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'text/xml'
            ],
            'path' => 'session/create/relayed'
        ]]);

        $session = $this->opentok->createSession(['e2ee' => true]);

        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/session/create', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $p2p_preference = $this->getPostField($request, 'p2p.preference');
        $this->assertEquals('enabled', $p2p_preference);

        $e2ee = $this->getPostField($request, 'e2ee');
        $this->assertEquals('true', $e2ee);

        $this->assertInstanceOf(Session::class, $session);
        $this->assertEquals(
            '2_MX4xNzAxMjYzMX4xMjcuMC4wLjF-V2VkIEZlYiAyNiAxODo1NzoyNCBQU1QgMjAxNH4wLjU0MDU4ODc0fg',
            $session->getSessionId()
        );
    }

    public function testCannotStartE2EESessionWithWrongMediaMode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectErrorMessage('MediaMode must be routed in order to enable E2EE');

        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'text/xml'
            ],
            'path' => 'session/create/relayed'
        ]]);

        $session = $this->opentok->createSession(
            [
                'mediaMode' => MediaMode::RELAYED,
                'e2ee' => true
            ]
        );
    }

    public function testCannotStartE2EESessionWithWrongArchiveMode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectErrorMessage('ArchiveMode cannot be set to always when using E2EE');

        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'text/xml'
            ],
            'path' => 'session/create/relayed'
        ]]);

        $session = $this->opentok->createSession(
            [
                'archiveMode' => ArchiveMode::ALWAYS,
                'e2ee' => true
            ]
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
        // Arrange
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'text/xml'
            ],
            'path' => 'session/create/routed_location-12.34.56.78'
        ]]);

        // Act
        $session = $this->opentok->createSession(array(
            'location' => '12.34.56.78',
            'mediaMode' => MediaMode::ROUTED
        ));

        // Assert
        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/session/create', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

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
        // Arrange
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'text/xml'
            ],
            'path' => 'session/create/relayed'
        ]]);

        // Act
        $session = $this->opentok->createSession(array(
            'mediaMode' => MediaMode::RELAYED
        ));

        // Assert
        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/session/create', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

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
        // Arrange
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'text/xml'
            ],
            'path' => 'session/create/alwaysarchived'
        ]]);

        // Act
        $session = $this->opentok->createSession(array(
            'archiveMode' => ArchiveMode::ALWAYS
        ));

        // Assert
        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/session/create', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

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
        // Arrange
        $this->setupOT();

        // Act
        $session = $this->opentok->createSession(array(
            'mediaMode' => MediaMode::RELAYED,
            'archiveMode' => ArchiveMode::ALWAYS
        ));

        // Assert
    }

    public function testGeneratesToken(): void
    {
        // Arrange
        $this->setupOT();
        // This sessionId is a fixture designed by using a known but bogus apiKey and apiSecret
        $sessionId = '1_MX4xMjM0NTY3OH4-VGh1IEZlYiAyNyAwNDozODozMSBQU1QgMjAxNH4wLjI0NDgyMjI';
        $bogusApiKey = '12345678';
        $bogusApiSecret = '0123456789abcdef0123456789abcdef0123456789';
        $opentok = new OpenTok($bogusApiKey, $bogusApiSecret);

        // Act
        $token = $opentok->generateToken($sessionId);

        // Assert
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
        $this->assertEquals(hash_hmac('sha1', $decodedToken['dataString'], $bogusApiSecret), $decodedToken['sig']);
    }

    public function testGeneratesTokenWithRole(): void
    {
        // Arrange
        $this->setupOT();
        // This sessionId is a fixture designed by using a known but bogus apiKey and apiSecret
        $sessionId = '1_MX4xMjM0NTY3OH4-VGh1IEZlYiAyNyAwNDozODozMSBQU1QgMjAxNH4wLjI0NDgyMjI';
        $bogusApiKey = '12345678';
        $bogusApiSecret = '0123456789abcdef0123456789abcdef0123456789';
        $opentok = new OpenTok($bogusApiKey, $bogusApiSecret);

        // Act
        $token = $opentok->generateToken($sessionId, array('role' => Role::MODERATOR));

        // Assert
        $this->assertIsString($token);

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

    public function testGeneratesTokenWithExpireTime(): void
    {
        // Arrange
        $this->setupOT();
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

    public function testGeneratesTokenWithData(): void
    {
        // Arrange
        $this->setupOT();
        // This sessionId is a fixture designed by using a known but bogus apiKey and apiSecret
        $sessionId = '1_MX4xMjM0NTY3OH4-VGh1IEZlYiAyNyAwNDozODozMSBQU1QgMjAxNH4wLjI0NDgyMjI';
        $bogusApiKey = '12345678';
        $bogusApiSecret = '0123456789abcdef0123456789abcdef0123456789';
        $opentok = new OpenTok($bogusApiKey, $bogusApiSecret);

        // Act
        $userStatus = '{nick:"johnny",status:"hey there fellas!"}';
        $token = $opentok->generateToken($sessionId, array('data' => $userStatus ));

        // Assert
        $this->assertIsString($token);

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

    public function testGeneratesTokenWithInitialLayoutClassList(): void
    {
        // Arrange
        $this->setupOT();
        // This sessionId is a fixture designed by using a known but bogus apiKey and apiSecret
        $sessionId = '1_MX4xMjM0NTY3OH4-VGh1IEZlYiAyNyAwNDozODozMSBQU1QgMjAxNH4wLjI0NDgyMjI';
        $bogusApiKey = '12345678';
        $bogusApiSecret = '0123456789abcdef0123456789abcdef0123456789';
        $opentok = new OpenTok($bogusApiKey, $bogusApiSecret);

        $initialLayouClassList = array('focus', 'main');

        // Act
        $token = $opentok->generateToken($sessionId, array(
            'initialLayoutClassList' => $initialLayouClassList
        ));

        // Assert
        $this->assertIsString($token);

        $decodedToken = TestHelpers::decodeToken($token);
        $this->assertEquals($sessionId, $decodedToken['session_id']);
        $this->assertEquals($bogusApiKey, $decodedToken['partner_id']);
        $this->assertNotEmpty($decodedToken['nonce']);
        $this->assertNotEmpty($decodedToken['create_time']);
        $this->assertArrayNotHasKey('connection_data', $decodedToken);
        $this->assertEquals('publisher', $decodedToken['role']);
        $this->assertEquals(join(" ", $initialLayouClassList), $decodedToken['initial_layout_class_list']);

        // TODO: should all tokens have a default expire time even if it wasn't specified?
        //$this->assertNotEmpty($decodedToken['expire_time']);

        $this->assertNotEmpty($decodedToken['sig']);
        $this->assertEquals(hash_hmac('sha1', $decodedToken['dataString'], $bogusApiSecret), $decodedToken['sig']);
    }

    public function testFailsWhenGeneratingTokenUsingInvalidRole(): void
    {
        $this->expectException('InvalidArgumentException');
        $this->setupOT();
        $token = $this->opentok->generateToken('SESSIONID', array('role' => 'notarole'));
    }

    public function testStartsArchive(): void
    {
        // Arrange
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/archive/session'
        ]]);

        // This sessionId was generated using a different apiKey, but this method doesn't do any
        // decoding to check, so it's fine.
        $sessionId = '2_MX44NTQ1MTF-flR1ZSBOb3YgMTIgMDk6NDA6NTkgUFNUIDIwMTN-MC43NjU0Nzh-';

        // Act
        $archive = $this->opentok->startArchive($sessionId);

        // Assert
        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/'.$this->API_KEY.'/archive', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $contentType = $request->getHeaderLine('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $this->assertInstanceOf('OpenTok\Archive', $archive);
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

    public function testCustomUserAgent(): void
    {
        $customAgent = [
            'app' => [
                'name' => 'my-php-app',
                'version' => '1.0.2'
            ]
        ];

        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/archive/session'
        ]], $customAgent);

        // This sessionId was generated using a different apiKey, but this method doesn't do any
        // decoding to check, so it's fine.
        $sessionId = '2_MX44NTQ1MTF-flR1ZSBOb3YgMTIgMDk6NDA6NTkgUFNUIDIwMTN-MC43NjU0Nzh-';

        $archive = $this->opentok->startArchive($sessionId);

        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $userAgent = $request->getHeaders()['User-Agent'];
        $this->assertStringContainsString(' my-php-app/1.0.2', $userAgent[0]);
    }

    public function testStartsArchiveInMultiTagMode(): void
    {
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/archive/session'
        ]]);

        $sessionId = '2_MX44NTQ1MTF-flR1ZSBOb3YgMTIgMDk6NDA6NTkgUFNUIDIwMTN-MC43NjU0Nzh-';

        $archive = $this->opentok->startArchive($sessionId, ['multiArchiveTag' => 'my-key']);

        // Assert
        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/'.$this->API_KEY.'/archive', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $this->assertInstanceOf('OpenTok\Archive', $archive);
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

    public function testStartsArchiveInManualMode(): void
    {
        // Arrange
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/archive/manual_mode_session'
        ]]);

        // This sessionId was generated using a different apiKey, but this method doesn't do any
        // decoding to check, so it's fine.
        $sessionId = '2_MX44NTQ1MTF-flR1ZSBOb3YgMTIgMDk6NDA6NTkgUFNUIDIwMTN-MC43NjU0Nzh-';

        // Act
        $archive = $this->opentok->startArchive($sessionId, ['streamMode' => StreamMode::MANUAL]);

        // Assert
        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/'.$this->API_KEY.'/archive', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $contentType = $request->getHeaderLine('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $this->assertInstanceOf('OpenTok\Archive', $archive);
        $this->assertEquals(0, $archive->duration);
        $this->assertEquals('', $archive->reason);
        $this->assertEquals('started', $archive->status);
        $this->assertEquals(OutputMode::COMPOSED, $archive->outputMode);
        $this->assertNull($archive->name);
        $this->assertNull($archive->url);
        $this->assertTrue($archive->hasVideo);
        $this->assertTrue($archive->hasAudio);
        $this->assertEquals('manual', $archive->streamMode);
    }

    public function testCannotStartArchiveWithInvalidStreamMode(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // Arrange
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/archive/manual_mode_session'
        ]]);

        // This sessionId was generated using a different apiKey, but this method doesn't do any
        // decoding to check, so it's fine.
        $sessionId = '2_MX44NTQ1MTF-flR1ZSBOb3YgMTIgMDk6NDA6NTkgUFNUIDIwMTN-MC43NjU0Nzh-';

        // Act
        $archive = $this->opentok->startArchive($sessionId, ['streamMode' => 'broadcast']);
    }

    public function testStartsArchiveNamed(): void
    {
        // Arrange
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/archive/session_name-showtime'
        ]]);

        // This sessionId was generated using a different apiKey, but this method doesn't do any
        // decoding to check, so it's fine.
        $sessionId = '2_MX44NTQ1MTF-flR1ZSBOb3YgMTIgMDk6NDA6NTkgUFNUIDIwMTN-MC43NjU0Nzh-';

        // Act
        $archive = $this->opentok->startArchive($sessionId, array( 'name' => 'showtime' ));

        // Assert
        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/'.$this->API_KEY.'/archive', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $contentType = $request->getHeaderLine('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

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
        // Arrange
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/archive/session_name-showtime'
        ]]);

        // This sessionId was generated using a different apiKey, but this method doesn't do any
        // decoding to check, so it's fine.
        $sessionId = '2_MX44NTQ1MTF-flR1ZSBOb3YgMTIgMDk6NDA6NTkgUFNUIDIwMTN-MC43NjU0Nzh-';

        // Act
        @$archive = $this->opentok->startArchive($sessionId, 'showtime');

        // Assert
        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/'.$this->API_KEY.'/archive', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $contentType = $request->getHeaderLine('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $body = json_decode($request->getBody());
        $this->assertEquals($sessionId, $body->sessionId);
        $this->assertEquals('showtime', $body->name);

        $this->assertInstanceOf('OpenTok\Archive', $archive);
        // TODO: test the properties of the actual archive object
    }

    public function testStartsArchiveAudioOnly(): void
    {
        // Arrange
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/archive/session_hasVideo-false'
        ]]);

        // This sessionId was generated using a different apiKey, but this method doesn't do any
        // decoding to check, so it's fine.
        $sessionId = '2_MX44NTQ1MTF-flR1ZSBOb3YgMTIgMDk6NDA6NTkgUFNUIDIwMTN-MC43NjU0Nzh-';

        // Act
        $archive = $this->opentok->startArchive($sessionId, array( 'hasVideo' => false ));

        // Assert
        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/'.$this->API_KEY.'/archive', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $contentType = $request->getHeaderLine('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $body = json_decode($request->getBody());
        $this->assertEquals($sessionId, $body->sessionId);
        $this->assertEquals(false, $body->hasVideo);
        $this->assertEquals(true, $body->hasAudio);

        $this->assertInstanceOf('OpenTok\Archive', $archive);
        // TODO: test the properties of the actual archive object
    }

    public function testStartsArchiveIndividualOutput(): void
    {
        // Arrange
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/archive/session_outputMode-individual'
        ]]);

        // This sessionId was generated using a different apiKey, but this method doesn't do any
        // decoding to check, so it's fine.
        $sessionId = '2_MX44NTQ1MTF-flR1ZSBOb3YgMTIgMDk6NDA6NTkgUFNUIDIwMTN-MC43NjU0Nzh-';

        // Act
        $archive = $this->opentok->startArchive($sessionId, array(
            'outputMode' => OutputMode::INDIVIDUAL
        ));

        // Assert
        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/'.$this->API_KEY.'/archive', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $contentType = $request->getHeaderLine('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $body = json_decode($request->getBody());
        $this->assertEquals($sessionId, $body->sessionId);
        $this->assertEquals('individual', $body->outputMode);

        $this->assertInstanceOf('OpenTok\Archive', $archive);
        $this->assertEquals(OutputMode::INDIVIDUAL, $archive->outputMode);
    }

    public function testStartsArchiveResolutionSD(): void
    {
        // Arrange
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/archive/session_resolution-sd'
        ]]);

        // This sessionId was generated using a different apiKey, but this method doesn't do any
        // decoding to check, so it's fine.
        $sessionId = '2_MX44NTQ1MTF-flR1ZSBOb3YgMTIgMDk6NDA6NTkgUFNUIDIwMTN-MC43NjU0Nzh-';

        // Act
        $archive = $this->opentok->startArchive($sessionId, array(
            'resolution' => '640x480'
        ));

        // Assert
        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/'.$this->API_KEY.'/archive', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $contentType = $request->getHeaderLine('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $body = json_decode($request->getBody());
        $this->assertEquals($sessionId, $body->sessionId);
        $this->assertEquals('640x480', $body->resolution);

        $this->assertInstanceOf('OpenTok\Archive', $archive);
    }

    public function testStartsArchiveResolutionHD(): void
    {
        // Arrange
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/archive/session_resolution-hd'
        ]]);

        // This sessionId was generated using a different apiKey, but this method doesn't do any
        // decoding to check, so it's fine.
        $sessionId = '2_MX44NTQ1MTF-flR1ZSBOb3YgMTIgMDk6NDA6NTkgUFNUIDIwMTN-MC43NjU0Nzh-';

        // Act
        $archive = $this->opentok->startArchive($sessionId, array(
            'resolution' => '1280x720'
        ));

        // Assert
        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/'.$this->API_KEY.'/archive', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $contentType = $request->getHeaderLine('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $body = json_decode($request->getBody());
        $this->assertEquals($sessionId, $body->sessionId);
        $this->assertEquals('1280x720', $body->resolution);

        $this->assertInstanceOf('OpenTok\Archive', $archive);
    }

    public function testStopsArchive(): void
    {
        // Arrange
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/archive/ARCHIVEID/stop'
        ]]);

        $archiveId = '063e72a4-64b4-43c8-9da5-eca071daab89';

        // Act
        $archive = $this->opentok->stopArchive($archiveId);

        // Assert
        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/'.$this->API_KEY.'/archive/'.$archiveId.'/stop', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $contentType = $request->getHeaderLine('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $this->assertInstanceOf('OpenTok\Archive', $archive);
        // TODO: test the properties of the actual archive object
    }

    public function testGetsArchive(): void
    {
        // Arrange
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/archive/ARCHIVEID/get'
        ]]);

        $archiveId = '063e72a4-64b4-43c8-9da5-eca071daab89';

        // Act
        $archive = $this->opentok->getArchive($archiveId);

        // Assert
        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('GET', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/'.$this->API_KEY.'/archive/'.$archiveId, $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        // TODO: this doesn't require Content-Type: application/json, but delete does?

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $this->assertInstanceOf('OpenTok\Archive', $archive);
        // TODO: test the properties of the actual archive object
    }

    public function testDeletesArchive(): void
    {
        // Arrange
        $this->setupOTWithMocks([[
            'code' => 204
        ]]);

        $archiveId = '063e72a4-64b4-43c8-9da5-eca071daab89';

        // Act
        $success = $this->opentok->deleteArchive($archiveId);

        // Assert
        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('DELETE', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/'.$this->API_KEY.'/archive/'.$archiveId, $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $contentType = $request->getHeaderLine('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $this->assertTrue($success);
        // TODO: test the properties of the actual archive object
    }

    public function testListsArchives(): void
    {
        // Arrange
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/archive/get'
        ]]);

        // Act
        $archiveList = $this->opentok->listArchives();

        // Assert
        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('GET', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/'.$this->API_KEY.'/archive', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $this->assertInstanceOf('OpenTok\ArchiveList', $archiveList);
        // TODO: test the properties of the actual archiveList object and its contained archive
        // objects
    }

    public function testListsArchivesWithOffsetAndCount(): void
    {
        // Arrange
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/archive/get_second'
        ]]);

        // Act
        $archiveList = $this->opentok->listArchives(1, 1);

        // Assert
        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('GET', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/'.$this->API_KEY.'/archive', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));
        $this->assertInstanceOf('OpenTok\ArchiveList', $archiveList);
        $this->assertEquals(1, $archiveList->totalCount());
        $this->assertEquals('832641bf-5dbf-41a1-ad94-fea213e59a92', $archiveList->getItems()[0]->id);
    }

    public function testListsArchivesWithSessionId(): void
    {
        // Arrange
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/archive/get_third'
        ]]);

        $sessionId = '1_MX4xMjM0NTY3OH4-VGh1IEZlYiAyNyAwNDozODozMSBQU1QgMjAxNH4wLjI0NDgyMjI';
        $bogusApiKey = '12345678';
        $bogusApiSecret = '0123456789abcdef0123456789abcdef0123456789';
        $opentok = new OpenTok($bogusApiKey, $bogusApiSecret);

        // Act
        $archiveList = $this->opentok->listArchives(0, null, $sessionId);

        // Assert
        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('GET', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/'.$this->API_KEY.'/archive', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));
        $this->assertInstanceOf('OpenTok\ArchiveList', $archiveList);
        $this->assertEquals(2, $archiveList->totalCount());
        $this->assertEquals($sessionId, $archiveList->getItems()[0]->sessionId);
        $this->assertEquals($sessionId, $archiveList->getItems()[1]->sessionId);        
        $this->assertEquals('b8f64de1-e218-4091-9544-4cbf369fc238', $archiveList->getItems()[0]->id);
        $this->assertEquals('832641bf-5dbf-41a1-ad94-fea213e59a92', $archiveList->getItems()[1]->id);        
    }

    public function testFailsWhenListingArchivesWithTooLargeCount(): void
    {
        $this->expectException('InvalidArgumentException');
        // Arrange
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/archive/get'
        ]]);

        // Act
        $archiveList = $this->opentok->listArchives(0, 1001);

        // Assert
        $this->assertCount(0, $this->historyContainer);
    }

    // TODO: sloppy test in a pinch
    public function testGetsExpiredArchive(): void
    {
        // Arrange
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/archive/ARCHIVEID/get-expired'
        ]]);

        $archiveId = '063e72a4-64b4-43c8-9da5-eca071daab89';

        // Act
        $archive = $this->opentok->getArchive($archiveId);

        // Assert
        $this->assertInstanceOf('OpenTok\Archive', $archive);
        $this->assertEquals("expired", $archive->status);
    }

    public function testForceDisconnect(): void
    {
        // Arrange
        $this->setupOTWithMocks([[
            'code' => 204
        ]]);

        $sessionId = '1_MX4xMjM0NTY3OH4-VGh1IEZlYiAyNyAwNDozODozMSBQU1QgMjAxNH4wLjI0NDgyMjI';

        $connectionId = '063e72a4-64b4-43c8-9da5-eca071daab89';

        // Act
        $success = $this->opentok->forceDisconnect($sessionId, $connectionId);

        // Assert
        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('DELETE', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/'.$this->API_KEY.'/session/'.$sessionId.'/connection/'.$connectionId, $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $contentType = $request->getHeaderLine('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));
        $this->assertTrue($success);
    }


    public function testForceDisconnectConnectionException(): void
    {
        // Arrange
        $this->setupOTWithMocks([[
            'code' => 404
        ]]);

        $sessionId = '1_MX4xMjM0NTY3OH4-VGh1IEZlYiAyNyAwNDozODozMSBQU1QgMjAxNH4wLjI0NDgyMjI';

        $connectionId = '063e72a4-64b4-43c8-9da5-eca071daab89';

        $this->expectException('OpenTok\Exception\ForceDisconnectConnectionException');        

        // Act
        $this->opentok->forceDisconnect($sessionId, $connectionId);
    }

    public function testCannotConnectAudioStreamWithInvalidSessionId()
    {
        $this->setupOTWithMocks([[
            'code' => 200
        ]]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectErrorMessage('Null or empty session ID is not valid: ');
        $this->opentok->connectAudio('', '2398523', []);
    }

    public function testCannotConnectAudioStreamWithoutWebsocketUri()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectErrorMessage('Websocket configuration must have a uri');
        $this->setupOTWithMocks([[
            'code' => 200
        ]]);

        $badPayload = [
            'streams' => ['333425', 'asfasrst'],
            'headers' => ['key' => 'value']
        ];

        $this->opentok->connectAudio('9999', 'wrwetg', $badPayload);
    }

    public function testCanConnectAudioStreamWithWebsocket()
    {
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => '/v2/project/APIKEY/connect'
        ]]);

        $sessionId = '1_MX4xMjM0NTY3OH4-VGh1IEZlYiAyNyAwNDozODozMSBQU1QgMjAxNH4wLjI0NDgyMjI';
        $token = '063e72a4-64b4-43c8-9da5-eca071daab89';
        $websocketConfig = [
            'uri' => 'ws://service.com/wsendpoint',
            'streams' => [
                'we9r885',
                '9238fujs'
            ],
            'headers' => [
                'key1' => 'value'
            ]
        ];

        $response = $this->opentok->connectAudio($sessionId, $token, $websocketConfig);
        $this->assertEquals('063e72a4-64b4-43c8-9da5-eca071daab89', $response['id']);
        $this->assertEquals('7aebb3a4-3d86-4962-b317-afb73e05439d', $response['connectionId']);
    }

	public function testCanStartBroadcastWithRmtp()
	{
		$this->setupOTWithMocks([[
			'code' => 200,
			'headers' => [
				'Content-Type' => 'application/json'
			],
			'path' => '/v2/project/APIKEY/broadcast/BROADCASTID/start_default'
		]]);

		$sessionId = '2_MX44NTQ1MTF-fjE0NzI0MzU2MDUyMjN-eVgwNFJhZmR6MjdockFHanpxNzBXaEFXfn4';

		$options = [
			'outputs' => [
				'hls' => [
					'dvr' => true,
					'lowLatency' => false
				],
				'rtmp' => [
					[
						'id' => 'foo',
						'serverUrl' => 'rtmps://myfooserver/myfooapp',
						'streamName' => 'myfoostream'
					],
					[
						'id' => 'bar',
						'serverUrl' => 'rtmps://myfooserver/mybarapp',
						'streamName' => 'mybarstream'
					],
				]
			]
		];

		$broadcast = $this->opentok->startBroadcast($sessionId, $options);
		$this->assertTrue($broadcast->isHls);
		$this->assertFalse($broadcast->isDvr);
		$this->assertFalse($broadcast->isLowLatency);
		$this->assertTrue(array_key_exists('rtmp', $broadcast->broadcastUrls));
	}

	public function testCannotStartBroadcastWithOver5RtmpChannels(): void
	{
		$this->expectException(InvalidArgumentException::class);

		$this->setupOTWithMocks([[
			'code' => 200,
			'headers' => [
				'Content-Type' => 'application/json'
			],
			'path' => '/v2/project/APIKEY/broadcast/BROADCASTID/start_default'
		]]);

		$sessionId = '2_MX44NTQ1MTF-fjE0NzI0MzU2MDUyMjN-eVgwNFJhZmR6MjdockFHanpxNzBXaEFXfn4';

		$options = [
			'outputs' => [
				'hls' => [
					'dvr' => true,
					'lowLatency' => false
				],
				'rtmp' => [
					[
						'id' => 'one',
						'serverUrl' => 'rtmps://myfooserver/one',
						'streamName' => 'one'
					],
					[
						'id' => 'two',
						'serverUrl' => 'rtmps://myfooserver/two',
						'streamName' => 'two'
					],
					[
						'id' => 'three',
						'serverUrl' => 'rtmps://myfooserver/three',
						'streamName' => 'three'
					],
					[
						'id' => 'four',
						'serverUrl' => 'rtmps://myfooserver/four',
						'streamName' => 'four'
					],
					[
						'id' => 'five',
						'serverUrl' => 'rtmps://myfooserver/five',
						'streamName' => 'five'
					],
					[
						'id' => 'six',
						'serverUrl' => 'rtmps://myfooserver/six',
						'streamName' => 'six'
					],
				]
			]
		];

		$broadcast = $this->opentok->startBroadcast($sessionId, $options);
	}

	public function testCanStartBroadcastWithDefaultHlsOptions(): void
    {
		$this->setupOTWithMocks([[
			'code' => 200,
			'headers' => [
				'Content-Type' => 'application/json'
			],
			'path' => '/v2/project/APIKEY/broadcast/BROADCASTID/start_default'
		]]);

		$sessionId = '2_MX44NTQ1MTF-fjE0NzI0MzU2MDUyMjN-eVgwNFJhZmR6MjdockFHanpxNzBXaEFXfn4';

		$broadcast = $this->opentok->startBroadcast($sessionId);
		$this->assertTrue($broadcast->isHls);
		$this->assertFalse($broadcast->isDvr);
		$this->assertFalse($broadcast->isLowLatency);
        $this->assertEquals('live', $broadcast->broadcastUrls['rtmp']['foo']['status']);
	}

	public function testCanStartBroadcastWithDvrEnabled(): void
    {
		$this->setupOTWithMocks([[
			'code' => 200,
			'headers' => [
				'Content-Type' => 'application/json'
			],
			'path' => '/v2/project/APIKEY/broadcast/BROADCASTID/start_dvr'
		]]);

		$sessionId = '2_MX44NTQ1MTF-fjE0NzI0MzU2MDUyMjN-eVgwNFJhZmR6MjdockFHanpxNzBXaEFXfn4';

		$options = [
			'outputs' => [
				'hls' => [
					'dvr' => true,
					'lowLatency' => false
				]
			]
		];

		$broadcast = $this->opentok->startBroadcast($sessionId, $options);
		$this->assertTrue($broadcast->isHls);
		$this->assertTrue($broadcast->isDvr);
		$this->assertFalse($broadcast->isLowLatency);
	}

	public function testCanStartBroadcastWithLowLatencyEnabled(): void
    {
		$this->setupOTWithMocks([[
			'code' => 200,
			'headers' => [
				'Content-Type' => 'application/json'
			],
			'path' => '/v2/project/APIKEY/broadcast/BROADCASTID/start_ll'
		]]);

		$sessionId = '2_MX44NTQ1MTF-fjE0NzI0MzU2MDUyMjN-eVgwNFJhZmR6MjdockFHanpxNzBXaEFXfn4';

		$options = [
			'outputs' => [
				'hls' => [
					'dvr' => true,
					'lowLatency' => false
				]
			]
		];

		$broadcast = $this->opentok->startBroadcast($sessionId, $options);
		$this->assertTrue($broadcast->isHls);
		$this->assertFalse($broadcast->isDvr);
		$this->assertTrue($broadcast->isLowLatency);
	}

	public function testCannotStartBroadcastWithBothHlsAndDvrEnabled(): void
    {
		$this->expectException(InvalidArgumentException::class);

		$this->setupOTWithMocks([[
			'code' => 200,
			'headers' => [
				'Content-Type' => 'application/json'
			],
			'path' => '/v2/project/APIKEY/broadcast/BROADCASTID/start_ll'
		]]);

		$sessionId = '2_MX44NTQ1MTF-fjE0NzI0MzU2MDUyMjN-eVgwNFJhZmR6MjdockFHanpxNzBXaEFXfn4';

		$options = [
			'outputs' => [
				'hls' => [
					'dvr' => true,
					'lowLatency' => true
				]
			]
		];

		$broadcast = $this->opentok->startBroadcast($sessionId, $options);
	}

    public function testStartsBroadcast(): void
    {
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => '/v2/project/APIKEY/broadcast/session_layout-bestfit'
        ]]);

        $sessionId = '2_MX44NTQ1MTF-fjE0NzI0MzU2MDUyMjN-eVgwNFJhZmR6MjdockFHanpxNzBXaEFXfn4';

        $broadcast = $this->opentok->startBroadcast($sessionId);

        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/'.$this->API_KEY.'/broadcast', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $contentType = $request->getHeaderLine('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));
        $this->assertInstanceOf('OpenTok\Broadcast', $broadcast);
        $this->assertIsString($broadcast->id);
        $this->assertEquals($sessionId, $broadcast->sessionId);
        $this->assertIsArray($broadcast->broadcastUrls);
        $this->assertArrayHasKey('hls', $broadcast->broadcastUrls);
        $this->assertIsString($broadcast->broadcastUrls['hls']);
        $this->assertIsString($broadcast->hlsUrl);
        $this->assertFalse($broadcast->isStopped);
        $this->assertEquals('auto', $broadcast->streamMode);
    }

    public function testStartsBroadcastWithMaxBitrate(): void
    {
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => '/v2/project/APIKEY/broadcast/session_layout-bestfit'
        ]]);

        $sessionId = '2_MX44NTQ1MTF-fjE0NzI0MzU2MDUyMjN-eVgwNFJhZmR6MjdockFHanpxNzBXaEFXfn4';

        $broadcast = $this->opentok->startBroadcast($sessionId, [
            'maxBitRate' => 2000000
        ]);

        $this->assertIsString($broadcast->id);
        $this->assertEquals($sessionId, $broadcast->sessionId);
        $this->assertIsArray($broadcast->broadcastUrls);
        $this->assertArrayHasKey('hls', $broadcast->broadcastUrls);
        $this->assertIsString($broadcast->broadcastUrls['hls']);
        $this->assertIsString($broadcast->hlsUrl);
        $this->assertFalse($broadcast->isStopped);
        $this->assertEquals(2000000, $broadcast->maxBitRate);
    }

    public function testStartsBroadcastWithMultiBroadcastTag(): void
    {
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => '/v2/project/APIKEY/broadcast/session_layout-bestfit'
        ]]);

        $sessionId = '2_MX44NTQ1MTF-fjE0NzI0MzU2MDUyMjN-eVgwNFJhZmR6MjdockFHanpxNzBXaEFXfn4';

        $broadcast = $this->opentok->startBroadcast($sessionId, ['multiBroadcastTag' => 'my-broadcast-tag']);

        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/'.$this->API_KEY.'/broadcast', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $contentType = $request->getHeaderLine('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $this->assertInstanceOf('OpenTok\Broadcast', $broadcast);
        $this->assertIsString($broadcast->id);
        $this->assertEquals($sessionId, $broadcast->sessionId);
        $this->assertIsArray($broadcast->broadcastUrls);
        $this->assertArrayHasKey('hls', $broadcast->broadcastUrls);
        $this->assertIsString($broadcast->broadcastUrls['hls']);
        $this->assertIsString($broadcast->hlsUrl);
        $this->assertFalse($broadcast->isStopped);
        $this->assertEquals('auto', $broadcast->streamMode);
    }

    public function testCannotStartBroadcastWithInvalidStreamMode(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => '/v2/project/APIKEY/broadcast/session_manual_stream'
        ]]);

        // This sessionId was generated using a different apiKey, but this method doesn't do any
        // decoding to check, so it's fine.
        $sessionId = '2_MX44NTQ1MTF-fjE0NzI0MzU2MDUyMjN-eVgwNFJhZmR6MjdockFHanpxNzBXaEFXfn4';

        // Act
        $broadcast = $this->opentok->startBroadcast($sessionId, ['streamMode' => 'stop']);
    }

    public function testStartsBroadcastInManualStreamMode(): void
    {
        // Arrange
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => '/v2/project/APIKEY/broadcast/session_manual_stream'
        ]]);

        // This sessionId was generated using a different apiKey, but this method doesn't do any
        // decoding to check, so it's fine.
        $sessionId = '2_MX44NTQ1MTF-fjE0NzI0MzU2MDUyMjN-eVgwNFJhZmR6MjdockFHanpxNzBXaEFXfn4';

        // Act
        $broadcast = $this->opentok->startBroadcast($sessionId, ['streamMode' => StreamMode::MANUAL]);

        // Assert
        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/'.$this->API_KEY.'/broadcast', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $contentType = $request->getHeaderLine('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $this->assertInstanceOf('OpenTok\Broadcast', $broadcast);
        $this->assertIsString($broadcast->id);
        $this->assertEquals($sessionId, $broadcast->sessionId);
        $this->assertIsArray($broadcast->broadcastUrls);
        $this->assertArrayHasKey('hls', $broadcast->broadcastUrls);
        $this->assertIsString($broadcast->broadcastUrls['hls']);
        $this->assertIsString($broadcast->hlsUrl);
        $this->assertFalse($broadcast->isStopped);
        $this->assertEquals('manual', $broadcast->streamMode);
    }

    public function testStartBroadcastWithOptions(): void
    {
        // Arrange
        $this->setupOTWithMocks([[
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

        // Act
        $broadcast = $this->opentok->startBroadcast($sessionId, $broadcastOptions);

        // Assert
        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/'.$this->API_KEY.'/broadcast', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $contentType = $request->getHeaderLine('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));
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

    // TODO: test startBroadcast with layout

    public function testStopsBroadcast(): void
    {
        // Arrange
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => '/v2/project/APIKEY/broadcast/BROADCASTID/stop'
        ]]);

        $broadcastId = 'BROADCASTID';

        // Act
        $broadcast = $this->opentok->stopBroadcast($broadcastId);

        // Assert
        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/'.$this->API_KEY.'/broadcast/'.$broadcastId.'/stop', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $this->assertInstanceOf('OpenTok\Broadcast', $broadcast);
        $this->assertTrue($broadcast->isStopped);
    }

    public function testGetsBroadcast(): void
    {
        // Arrange
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => '/v2/project/APIKEY/broadcast/BROADCASTID/get'
        ]]);

        $broadcastId = 'BROADCASTID';

        // Act
        $broadcast = $this->opentok->getBroadcast($broadcastId);

        // Assert
        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('GET', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/'.$this->API_KEY.'/broadcast/'.$broadcastId, $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));
        $this->assertInstanceOf('OpenTok\Broadcast', $broadcast);
    }

    public function testCanMuteStream(): void
    {
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => '/v2/project/APIKEY/session/SESSIONID/mute'
        ]]);

        $sessionId = 'SESSIONID';
        $streamId = 'STREAMID';

        $result = $this->opentok->forceMuteStream($sessionId, $streamId);
        $this->assertTrue($result);
    }

    public function testCanMuteStreams(): void
    {
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => '/v2/project/APIKEY/session/SESSIONID/mute'
        ]]);

        $streamIds = ['TEST1', 'TEST2'];
        $sessionId = 'SESSIONID';

        $result = $this->opentok->forceMuteAll($sessionId, $streamIds);
        $this->assertTrue($result);
    }

    public function testThrowsExceptionWhenInvalidStreamId(): void
    {
        $this->setupOTWithMocks([[
            'code' => 404,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => '/v2/project/APIKEY/session/SESSIONID/mute'
        ]]);

        $streamId = 'TEST1';
        $sessionId = 'SESSIONID';

        $result = $this->opentok->forceMuteStream($sessionId, $streamId);
        $this->assertFalse($result);
    }

    public function testThrowsExceptionWhenInvalidStreamIds(): void
    {
        $this->setupOTWithMocks([[
            'code' => 404,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => '/v2/project/APIKEY/session/SESSIONID/mute'
        ]]);

        $streamIds = ['TEST1', 'TEST2'];
        $sessionId = 'SESSIONID';

        $result = $this->opentok->forceMuteAll($sessionId, $streamIds);
        $this->assertFalse($result);
    }

    public function testCannotMuteStreamsWithWrongTypePayload(): void
    {
        $this->expectException(\TypeError::class);

        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => '/v2/project/APIKEY/session/SESSIONID/mute'
        ]]);

        $streamIdsString = implode(',', ['TEST1', 'TEST2']);
        $sessionId = 'SESSIONID';

        $result = $this->opentok->forceMuteAll($sessionId, $streamIdsString);
        $this->assertFalse($result);
    }

    public function testUpdatesBroadcastLayoutWithPredefined(): void
    {
        // Arrange
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => '/v2/project/APIKEY/broadcast/BROADCASTID/layout/type-pip'
        ]]);

        $broadcastId = 'BROADCASTID';
        $layout = Layout::getPIP();

        // Act
        $this->opentok->updateBroadcastLayout($broadcastId, $layout);

        // Assert
        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('PUT', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/'.$this->API_KEY.'/broadcast/'.$broadcastId.'/layout', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $contentType = $request->getHeaderLine('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $body = json_decode($request->getBody());
        $this->assertEquals('pip', $body->type);
    }

    public function testUpdatesBroadcastLayoutWithCustom(): void
    {
        // Arrange
        $this->setupOTWithMocks([[
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

        // Act
        $this->opentok->updateBroadcastLayout($broadcastId, $layout);

        // Assert
        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('PUT', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/'.$this->API_KEY.'/broadcast/'.$broadcastId.'/layout', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $contentType = $request->getHeaderLine('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $body = json_decode($request->getBody());
        $this->assertEquals('custom', $body->type);
        $this->assertEquals($stylesheet, $body->stylesheet);
    }

    public function testUpdatesStreamLayoutClassList(): void
    {
        // Arrange
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => '/v2/project/APIKEY/session/SESSIONID/stream/STREAMID/layoutClassList'
        ]]);

        $sessionId = 'SESSIONID';
        $streamId = 'STREAMID';
        $layoutClassList = array('foo', 'bar');

        // Act
        $this->opentok->updateStream($sessionId, $streamId, array(
            'layoutClassList' => $layoutClassList
        ));

        // Assert
        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('PUT', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/'.$this->API_KEY.'/session/'.$sessionId.'/stream/'.$streamId, $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $contentType = $request->getHeaderLine('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $body = json_decode($request->getBody());
        $this->assertEquals($layoutClassList, $body->layoutClassList);
    }

    public function testGetStream(): void
    {
        // Arrange
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => '/v2/project/APIKEY/session/SESSIONID/stream/STREAMID/get'
        ]]);

        $sessionId = 'SESSIONID';
        $streamId = '8b732909-0a06-46a2-8ea8-074e64d43422';

        // Act
        $streamData = $this->opentok->getStream($sessionId, $streamId);
        // Assert
        $this->assertCount(1, $this->historyContainer);        

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('GET', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/'.$this->API_KEY.'/session/'.$sessionId.'/stream/'.$streamId, $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $this->assertInstanceOf('OpenTok\Stream', $streamData);
        $this->assertNotNull($streamData->id);
        $this->assertNotNull($streamData->name);
        $this->assertNotNull($streamData->videoType);
        $this->assertNotNull($streamData->layoutClassList);
    }

    public function testSipCall(): void
    {
        // Arrange
        $this->setupOTWithMocks([[
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
        $opentok = new OpenTok($bogusApiKey, $bogusApiSecret);

        // Act
        $sipCall = $this->opentok->dial($sessionId, $bogusToken, $bogusSipUri);

        // Assert
        $this->assertInstanceOf('OpenTok\SipCall', $sipCall);
        $this->assertNotNull($sipCall->id);
        $this->assertNotNull($sipCall->connectionId);
        $this->assertNotNull($sipCall->streamId);
    }

    public function testSipCallWithAuth(): void
    {
        // Arrange
        $this->setupOTWithMocks([[
            'code' => 200,
            'path' => 'v2/project/APIKEY/dial'
        ]]);

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

        $this->assertCount(1, $this->historyContainer);
        $request = $this->historyContainer[0]['request'];

        $body = json_decode($request->getBody());
        $this->assertEquals($auth['username'], $body->sip->auth->username);
        $this->assertEquals($auth['password'], $body->sip->auth->password);
    }

    public function testFailedSipCall(): void
    {
        // Arrange
        $this->setupOTWithMocks([[
            'code' => 500
        ]]);

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

    public function testSipCallFrom(): void
    {
        // Arrange
        $this->setupOTWithMocks([[
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

        // Act
        $sipCall = $this->opentok->dial($sessionId, $bogusToken, $bogusSipUri, [
            'from' => $from
        ]);

        // Assert
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
        $this->setupOTWithMocks([[
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

    public function testSipCallVideoWithObserveForceMute(): void
    {
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => 'v2/project/APIKEY/dialForceMute'
        ]]);

        $sessionId = '1_MX4xMjM0NTY3OH4-VGh1IEZlYiAyNyAwNDozODozMSBQU1QgMjAxNH4wLjI0NDgyMjI';
        $bogusToken = 'T1==TEST';
        $bogusSipUri = 'sip:john@doe.com';

        $optionsPayload = [
            'video' => true,
            'observeForceMute' => true
        ];

        $sipCall = $this->opentok->dial($sessionId, $bogusToken, $bogusSipUri, $optionsPayload);

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
        $this->setupOTWithMocks([[
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
        $this->setupOTWithMocks([[
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

        $this->setupOT();
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

        $this->setupOTWithMocks([[
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
        // Arrange
        $this->setupOTWithMocks([[
            'code' => 204
        ]]);

        $sessionId = '1_MX4xMjM0NTY3OH4-VGh1IEZlYiAyNyAwNDozODozMSBQU1QgMjAxNH4wLjI0NDgyMjI';
        $bogusApiKey = '12345678';
        $bogusApiSecret = '0123456789abcdef0123456789abcdef0123456789';

        $opentok = new OpenTok($bogusApiKey, $bogusApiSecret);

        $payload = array(
            'data' => 'apple',
            'type' => 'signal type sample'
        );

        // Act
        $this->opentok->signal($sessionId, $payload);

                // Assert
        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/'.$this->API_KEY.'/session/'.$sessionId.'/signal', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));
        $body = json_decode($request->getBody());
        $this->assertEquals('apple', $body->data);
        $this->assertEquals('signal type sample', $body->type);        
    }

    public function testSignalWithConnectionId(): void
    {
        // Arrange
        $this->setupOTWithMocks([[
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

        $opentok = new OpenTok($bogusApiKey, $bogusApiSecret);

        // Act
        $this->opentok->signal($sessionId, $payload, $connectionId);

        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('POST', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/'.$this->API_KEY.'/session/'.$sessionId.'/connection/'.$connectionId.'/signal', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));
        $body = json_decode($request->getBody());
        $this->assertEquals('random message', $body->data);
        $this->assertEquals('rest', $body->type);        
    }

    /**
     * @todo Fix this test, not even sure what it's supposed to be doing honestly.
     */
    public function testSignalWithEmptyPayload(): void
    {
        // Arrange
        $this->setupOTWithMocks([[
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
        // Arrange
        $this->setupOTWithMocks([[
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

        $opentok = new OpenTok($bogusApiKey, $bogusApiSecret);

        $this->expectException('OpenTok\Exception\SignalConnectionException');
        // Act
        $this->opentok->signal($sessionId, $payload, $connectionId);
    }

    public function testSignalUnexpectedValueException(): void
    {
        // Arrange
        $this->setupOTWithMocks([[
            'code' => 413
        ]]);

        $sessionId = '1_MX4xMjM0NTY3OH4-VGh1IEZlYiAyNyAwNDozODozMSBQU1QgMjAxNH4wLjI0NDgyMjI';
        $bogusApiKey = '12345678';
        $bogusApiSecret = '0123456789abcdef0123456789abcdef0123456789';
        $connectionId = 'da9cb410-e29b-4c2d-ab9e-fe65bf83fcaf';
        $payload = array(
            'type' => 'rest',
            'data' => 'more than 128 bytes'
        );

        $opentok = new OpenTok($bogusApiKey, $bogusApiSecret);

        $this->expectException('OpenTok\Exception\SignalUnexpectedValueException');

        // Act
        $this->opentok->signal($sessionId, $payload, $connectionId);

    }

    public function testListStreams(): void
    {
        // Arrange
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => '/v2/project/APIKEY/session/SESSIONID/stream/get'
        ]]);

        $sessionId = '1_MX4xMjM0NTY3OH4-VGh1IEZlYiAyNyAwNDozODozMSBQU1QgMjAxNH4wLjI0NDgyMjI';
        $bogusApiKey = '12345678';
        $bogusApiSecret = '0123456789abcdef0123456789abcdef0123456789';

        $opentok = new OpenTok($bogusApiKey, $bogusApiSecret);

        // Act
        $streamList = $this->opentok->listStreams($sessionId);

        // Assert
        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('GET', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/'.$this->API_KEY.'/session/'.$sessionId.'/stream/', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $this->assertInstanceOf('OpenTok\StreamList', $streamList);
    }

    public function testsSetArchiveLayoutWithPredefined(): void
    {
        // Arrange
        $this->setupOTWithMocks([[
            'code' => 200,
            'headers' => [
                'Content-Type' => 'application/json'
            ]
        ]]);

        $archiveId = 'ARCHIVEID';
        $layout = Layout::getPIP();

        // Act
        $this->opentok->setArchiveLayout($archiveId, $layout);

        // Assert
        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('PUT', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/'.$this->API_KEY.'/archive/'.$archiveId.'/layout', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $contentType = $request->getHeaderLine('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $body = json_decode($request->getBody());
        $this->assertEquals('pip', $body->type);
    }

    public function testsSetArchiveLayoutWithCustom(): void
    {
        // Arrange
        $this->setupOTWithMocks([[
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

        // Act
        $this->opentok->setArchiveLayout($archiveId, $layout);

        // Assert
        $this->assertCount(1, $this->historyContainer);

        $request = $this->historyContainer[0]['request'];
        $this->assertEquals('PUT', strtoupper($request->getMethod()));
        $this->assertEquals('/v2/project/'.$this->API_KEY.'/archive/'.$archiveId.'/layout', $request->getUri()->getPath());
        $this->assertEquals('api.opentok.com', $request->getUri()->getHost());
        $this->assertEquals('https', $request->getUri()->getScheme());

        $contentType = $request->getHeaderLine('Content-Type');
        $this->assertNotEmpty($contentType);
        $this->assertEquals('application/json', $contentType);

        $authString = $request->getHeaderLine('X-OPENTOK-AUTH');
        $this->assertEquals(true, TestHelpers::validateOpenTokAuthHeader($this->API_KEY, $this->API_SECRET, $authString));

        $body = json_decode($request->getBody());
        $this->assertEquals('custom', $body->type);
        $this->assertEquals($stylesheet, $body->stylesheet);
    }

    /**
     * Makes sure that Guzzle internally keeps a null/indefinate timeout by default
     * This makes sure that internal existing behavior has not changed
     */
    public function testDefaultTimeoutDefaultsToNull(): void
    {
        $this->setupOT();

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

    public function testCanStartCaptions(): void
    {
        $this->setupOTWithMocks([[
            'code' => 202,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => '/v2/project/APIKEY/session/SESSIONID/caption-start'
        ]]);

        $result = $this->opentok->startCaptions('SESSION_ID', 'abc');
        $this->assertEquals('7c0680fc-6274-4de5-a66f-d0648e8d3ac2', $result['captionsId']);
    }

    public function testCanStopCaptions(): void
    {
        $this->setupOTWithMocks([[
            'code' => 202,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'path' => '/v2/project/APIKEY/session/SESSIONID/caption-stop'
        ]]);

        $result = $this->opentok->stopCaptions('7c0680fc-6274-4de5-a66f-d0648e8d3ac2');
        $this->assertTrue($result);
    }
}

